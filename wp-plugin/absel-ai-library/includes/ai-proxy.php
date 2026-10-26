<?php
/**
 * Server-side proxy between simulations and the Claude API.
 *
 * POST /wp-json/absel-ail/v1/chat  { activity, token, messages: [{role, content}, ...] }
 *
 * The API key stays on the server (ABSEL_AIL_ANTHROPIC_KEY in wp-config.php).
 * The system prompt comes from the activity's _system-prompt.md, never from the
 * browser, so the endpoint can only run the library's own simulations. Each
 * member, and each student link, gets a daily request allowance, and token
 * usage is totalled by month.
 */

defined( 'ABSPATH' ) || exit;

const ABSEL_AIL_API_URL     = 'https://api.anthropic.com/v1/messages';
const ABSEL_AIL_API_VERSION = '2026-10-21';
const ABSEL_AIL_MAX_TURNS   = 60;
const ABSEL_AIL_MAX_CHARS   = 4000;

add_action( 'rest_api_init', function () {
	register_rest_route( 'absel-ail/v1', '/chat', array(
		'methods'             => 'POST',
		'callback'            => 'absel_ail_chat',
		'permission_callback' => function ( WP_REST_Request $request ) {
			if ( absel_ail_member_has_access() || absel_ail_request_class_pass( $request ) ) {
				return true;
			}
			return new WP_Error( 'absel_ail_forbidden', __( 'Your session or student link has expired. Log in again, or ask your instructor for a new link.', 'absel-ai-library' ), array( 'status' => 403 ) );
		},
	) );
} );

function absel_ail_request_class_pass( WP_REST_Request $request ) {
	$pass = $request->get_param( 'classPass' );
	return $pass ? absel_ail_class_pass_verify( $pass, absel_ail_sanitize_folder( $request->get_param( 'activity' ) ) ) : null;
}

/**
 * HMAC over who is asking, for which activity, on which day.
 */
function absel_ail_request_token( $activity, $day = null, $key = null ) {
	$day = $day ? $day : gmdate( 'Y-m-d' );
	$key = $key ? $key : absel_ail_usage_key();
	return hash_hmac( 'sha256', $key . '|' . $activity . '|' . $day, wp_salt( 'auth' ) );
}

function absel_ail_token_is_valid( $activity, $token, $key ) {
	// Accept yesterday's token so a session running across midnight UTC keeps working.
	foreach ( array( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) ) as $day ) {
		if ( hash_equals( absel_ail_request_token( $activity, $day, $key ), (string) $token ) ) {
			return true;
		}
	}
	return false;
}

function absel_ail_chat( WP_REST_Request $request ) {
	$activity = absel_ail_sanitize_folder( $request->get_param( 'activity' ) );
	// Members are counted individually; everyone on a student link shares that link's allowance.
	$pass      = absel_ail_member_has_access() ? null : absel_ail_request_class_pass( $request );
	$requester = $pass ? $pass['key'] : absel_ail_usage_key();
	if ( ! $activity || ! absel_ail_token_is_valid( $activity, $request->get_param( 'token' ), $requester ) ) {
		return new WP_Error( 'absel_ail_bad_token', __( 'This page has expired. Reload it to continue.', 'absel-ai-library' ), array( 'status' => 403 ) );
	}

	$prompt_file = trailingslashit( ABSEL_AIL_CONTENT_DIR ) . $activity . '/_system-prompt.md';
	if ( ! is_file( $prompt_file ) ) {
		return new WP_Error( 'absel_ail_no_prompt', __( 'This activity has no AI configured.', 'absel-ai-library' ), array( 'status' => 404 ) );
	}

	$messages = absel_ail_clean_messages( $request->get_param( 'messages' ) );
	if ( is_wp_error( $messages ) ) {
		return $messages;
	}

	if ( ! defined( 'ABSEL_AIL_ANTHROPIC_KEY' ) || ! ABSEL_AIL_ANTHROPIC_KEY ) {
		return new WP_Error( 'absel_ail_no_key', __( 'The AI service is not set up yet.', 'absel-ai-library' ), array( 'status' => 503 ) );
	}

	$settings  = absel_ail_settings();
	$limit     = $pass ? $settings['class_daily_limit'] : $settings['daily_limit'];
	$usage_key = 'absel_ail_use_' . $requester . '_' . gmdate( 'Ymd' );
	$used      = (int) get_transient( $usage_key );
	if ( $limit > 0 && $used >= $limit && ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'absel_ail_limit', $pass
			? __( 'This class link has reached today\'s limit for AI responses. It resets at midnight UTC; let your instructor know.', 'absel-ai-library' )
			: __( 'You have reached today\'s limit for AI responses. It resets at midnight UTC.', 'absel-ai-library' ), array( 'status' => 429 ) );
	}

	$result = absel_ail_call_claude( file_get_contents( $prompt_file ), $messages, $settings );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	set_transient( $usage_key, $used + 1, DAY_IN_SECONDS );
	absel_ail_record_tokens( $result['usage'] );

	return rest_ensure_response( array(
		'reply'     => $result['text'],
		'remaining' => $limit > 0 ? max( 0, $limit - $used - 1 ) : null,
	) );
}

/**
 * Accept only plain text turns that alternate user/assistant and end on the user.
 */
function absel_ail_clean_messages( $raw ) {
	$invalid = new WP_Error( 'absel_ail_bad_messages', __( 'The conversation could not be read.', 'absel-ai-library' ), array( 'status' => 400 ) );
	if ( ! is_array( $raw ) || ! $raw || count( $raw ) > ABSEL_AIL_MAX_TURNS ) {
		return count( (array) $raw ) > ABSEL_AIL_MAX_TURNS
			? new WP_Error( 'absel_ail_too_long', __( 'This conversation has reached its length limit. Start a new session.', 'absel-ai-library' ), array( 'status' => 400 ) )
			: $invalid;
	}
	$clean    = array();
	$expected = 'user';
	foreach ( $raw as $turn ) {
		if ( ! is_array( $turn ) || ! isset( $turn['role'], $turn['content'] ) || $turn['role'] !== $expected || ! is_string( $turn['content'] ) ) {
			return $invalid;
		}
		$text = trim( wp_check_invalid_utf8( $turn['content'] ) );
		if ( '' === $text || mb_strlen( $text ) > ABSEL_AIL_MAX_CHARS ) {
			return $invalid;
		}
		$clean[]  = array( 'role' => $expected, 'content' => $text );
		$expected = 'user' === $expected ? 'assistant' : 'user';
	}
	return 'user' === end( $clean )['role'] ? $clean : $invalid;
}

function absel_ail_call_claude( $system, $messages, $settings ) {
	$body = array(
		'model'      => $settings['model'],
		'max_tokens' => $settings['max_tokens'],
		'system'     => $system,
		'messages'   => $messages,
	);
	if ( $settings['effort'] ) {
		$body['output_config'] = array( 'effort' => $settings['effort'] );
	}
	$headers = array(
		'content-type'      => 'application/json',
		'x-api-key'         => ABSEL_AIL_ANTHROPIC_KEY,
		'anthropic-version' => ABSEL_AIL_API_VERSION,
	);
	// If a safety classifier declines a turn, let the API retry it on its
	// recommended fallback model instead of failing the student's session.
	if ( in_array( $settings['model'], array( 'claude-fable-5-1', 'claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5' ), true ) ) {
		$body['fallbacks']        = 'default';
		$headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
	}

	$response = wp_remote_post( ABSEL_AIL_API_URL, array(
		'headers' => $headers,
		'body'    => wp_json_encode( $body ),
		'timeout' => 90,
	) );
	$unavailable = new WP_Error( 'absel_ail_upstream', __( 'The AI partner is unavailable right now. Try again in a minute.', 'absel-ai-library' ), array( 'status' => 502 ) );
	if ( is_wp_error( $response ) ) {
		error_log( 'ABSEL AI Library: request failed: ' . $response->get_error_message() );
		return $unavailable;
	}
	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $code || ! is_array( $data ) ) {
		$detail = isset( $data['error']['message'] ) ? $data['error']['message'] : wp_remote_retrieve_body( $response );
		error_log( sprintf( 'ABSEL AI Library: API returned %d: %s', $code, substr( (string) $detail, 0, 500 ) ) );
		return $unavailable;
	}

	$usage = isset( $data['usage'] ) ? $data['usage'] : array();
	if ( isset( $data['stop_reason'] ) && 'refusal' === $data['stop_reason'] ) {
		return array(
			'text'  => __( '(The AI partner declined to respond to that message. Try rephrasing, or ask your instructor for help.)', 'absel-ai-library' ),
			'usage' => $usage,
		);
	}
	$text = '';
	foreach ( (array) ( isset( $data['content'] ) ? $data['content'] : array() ) as $block ) {
		if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
			$text .= $block['text'];
		}
	}
	if ( '' === trim( $text ) ) {
		error_log( 'ABSEL AI Library: response had no text (stop_reason ' . ( isset( $data['stop_reason'] ) ? $data['stop_reason'] : 'none' ) . ')' );
		return $unavailable;
	}
	return array( 'text' => $text, 'usage' => $usage );
}

/**
 * Monthly totals shown on the settings page, for keeping an eye on cost.
 */
function absel_ail_record_tokens( $usage ) {
	$option = 'absel_ail_tokens_' . gmdate( 'Ym' );
	$totals = get_option( $option, array( 'requests' => 0, 'input' => 0, 'output' => 0 ) );
	$totals['requests']++;
	$totals['input']  += isset( $usage['input_tokens'] ) ? (int) $usage['input_tokens'] : 0;
	$totals['output'] += isset( $usage['output_tokens'] ) ? (int) $usage['output_tokens'] : 0;
	update_option( $option, $totals, false );
}
