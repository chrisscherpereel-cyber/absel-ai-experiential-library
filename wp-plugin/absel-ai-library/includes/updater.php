<?php
/**
 * Updates from GitHub releases, so the site updates this plugin from
 * Dashboard > Updates like any other.
 *
 * The plugin's "Update URI" header points at its GitHub repository, so WordPress
 * asks this file (through the update_plugins_github.com filter) whether a newer
 * version exists, instead of asking wordpress.org. A newer version is the latest
 * GitHub release whose tag (for example v0.5.0) is higher than ABSEL_AIL_VERSION
 * and which has absel-ai-library.zip attached. .github/workflows/release.yml
 * builds and attaches it whenever a version tag is pushed.
 *
 * The repository is private, so the site needs a read-only access token in
 * wp-config.php:
 *     define( 'ABSEL_AIL_GITHUB_TOKEN', 'github_pat_...' );
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ABSEL_AIL_GITHUB_REPO' ) ) {
	define( 'ABSEL_AIL_GITHUB_REPO', 'chrisscherpereel-cyber/absel-ai-experiential-library' );
}
if ( ! defined( 'ABSEL_AIL_GITHUB_API' ) ) {
	define( 'ABSEL_AIL_GITHUB_API', 'https://api.github.com' );
}
const ABSEL_AIL_RELEASE_ASSET = 'absel-ai-library.zip';

function absel_ail_github_headers( $accept = 'application/vnd.github+json' ) {
	$headers = array(
		'Accept'               => $accept,
		'User-Agent'           => 'absel-ai-library/' . ABSEL_AIL_VERSION,
		'X-GitHub-Api-Version' => '2022-11-28',
	);
	if ( defined( 'ABSEL_AIL_GITHUB_TOKEN' ) && ABSEL_AIL_GITHUB_TOKEN ) {
		$headers['Authorization'] = 'Bearer ' . ABSEL_AIL_GITHUB_TOKEN;
	}
	return $headers;
}

/**
 * The latest release on GitHub, cached for six hours (one hour after a failure).
 *
 * @return array{version:string,package:string,url:string,notes:string,date:string,checked:int}|WP_Error
 */
function absel_ail_latest_release( $force = false ) {
	$cached = get_site_transient( 'absel_ail_release' );
	if ( ! $force && is_array( $cached ) ) {
		return isset( $cached['error'] ) ? new WP_Error( 'absel_ail_release', $cached['error'] ) : $cached;
	}

	$response = wp_remote_get( ABSEL_AIL_GITHUB_API . '/repos/' . ABSEL_AIL_GITHUB_REPO . '/releases/latest', array(
		'headers' => absel_ail_github_headers(),
		'timeout' => 10,
	) );
	$code  = (int) wp_remote_retrieve_response_code( $response );
	$data  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$token = defined( 'ABSEL_AIL_GITHUB_TOKEN' ) && ABSEL_AIL_GITHUB_TOKEN;
	$error = '';
	if ( is_wp_error( $response ) ) {
		$error = $response->get_error_message();
	} elseif ( 401 === $code ) {
		$error = __( 'GitHub rejected the access token. It may have expired; create a new one and update wp-config.php.', 'absel-ai-library' );
	} elseif ( 404 === $code ) {
		$error = $token
			? __( 'GitHub found no release, or the access token cannot read the repository.', 'absel-ai-library' )
			: __( 'GitHub returned "not found". The repository is private, so add ABSEL_AIL_GITHUB_TOKEN to wp-config.php.', 'absel-ai-library' );
	} elseif ( 200 !== $code || ! is_array( $data ) || empty( $data['tag_name'] ) ) {
		/* translators: %d: HTTP status code */
		$error = sprintf( __( 'Unexpected answer from GitHub (%d).', 'absel-ai-library' ), $code );
	}

	$release = null;
	if ( ! $error ) {
		$asset = '';
		foreach ( isset( $data['assets'] ) ? (array) $data['assets'] : array() as $item ) {
			if ( isset( $item['name'], $item['url'] ) && ABSEL_AIL_RELEASE_ASSET === $item['name'] ) {
				$asset = $item['url'];
			}
		}
		if ( ! $asset ) {
			/* translators: 1: release tag, 2: file name */
			$error = sprintf( __( 'Release %1$s has no %2$s attached.', 'absel-ai-library' ), $data['tag_name'], ABSEL_AIL_RELEASE_ASSET );
		} else {
			$release = array(
				'version' => ltrim( $data['tag_name'], 'vV' ),
				'package' => $asset,
				'url'     => isset( $data['html_url'] ) ? $data['html_url'] : '',
				'notes'   => isset( $data['body'] ) ? (string) $data['body'] : '',
				'date'    => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
				'checked' => time(),
			);
		}
	}

	if ( $error ) {
		set_site_transient( 'absel_ail_release', array( 'error' => $error, 'checked' => time() ), HOUR_IN_SECONDS );
		return new WP_Error( 'absel_ail_release', $error );
	}
	set_site_transient( 'absel_ail_release', $release, 6 * HOUR_IN_SECONDS );
	return $release;
}

// WordPress compares the version given here with the installed one and offers the update.
add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( ABSEL_AIL_FILE ) !== $plugin_file ) {
		return $update;
	}
	$release = absel_ail_latest_release();
	if ( is_wp_error( $release ) ) {
		return $update;
	}
	return array(
		'slug'         => 'absel-ai-library',
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires_php' => '7.4',
	);
}, 10, 3 );

// The "View version details" window on the Updates and Plugins screens.
add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'absel-ai-library' !== $args->slug ) {
		return $result;
	}
	$release = absel_ail_latest_release();
	if ( is_wp_error( $release ) ) {
		return $result;
	}
	return (object) array(
		'name'          => 'ABSEL AI Experiential Library',
		'slug'          => 'absel-ai-library',
		'version'       => $release['version'],
		'author'        => 'ABSEL',
		'homepage'      => $release['url'],
		'requires_php'  => '7.4',
		'last_updated'  => $release['date'],
		'sections'      => array( 'changelog' => wpautop( esc_html( $release['notes'] ? $release['notes'] : __( 'No release notes.', 'absel-ai-library' ) ) ) ),
		'download_link' => $release['package'],
	);
}, 10, 3 );

/*
 * Downloading the update. For a private repository GitHub answers the asset
 * address with a redirect to a short-lived download link, and that link refuses
 * requests that still carry the token, so the redirect is followed here without it.
 */
add_filter( 'upgrader_pre_download', function ( $reply, $package ) {
	$assets = ABSEL_AIL_GITHUB_API . '/repos/' . ABSEL_AIL_GITHUB_REPO . '/releases/assets/';
	if ( false !== $reply || 0 !== strpos( (string) $package, $assets ) ) {
		return $reply;
	}
	$response = wp_remote_get( $package, array(
		'headers'     => absel_ail_github_headers( 'application/octet-stream' ),
		'redirection' => 0,
		'timeout'     => 15,
	) );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code     = (int) wp_remote_retrieve_response_code( $response );
	$location = wp_remote_retrieve_header( $response, 'location' );
	if ( $code >= 300 && $code < 400 && $location ) {
		return download_url( $location, 300 );
	}
	if ( 200 === $code ) {
		// No redirect (for example a public repository): the body is the zip itself.
		$file = wp_tempnam( ABSEL_AIL_RELEASE_ASSET );
		file_put_contents( $file, wp_remote_retrieve_body( $response ) );
		return $file;
	}
	/* translators: %d: HTTP status code */
	return new WP_Error( 'absel_ail_download', sprintf( __( 'GitHub did not provide the update file (%d). Check the access token.', 'absel-ai-library' ), $code ) );
}, 10, 2 );

// After updating, forget the cached release so the settings page shows the new state.
add_action( 'upgrader_process_complete', function () {
	delete_site_transient( 'absel_ail_release' );
} );

// Settings > AI Library > "Check for updates now".
add_action( 'admin_post_absel_ail_check_updates', function () {
	check_admin_referer( 'absel_ail_check_updates' );
	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'absel-ai-library' ), 403 );
	}
	absel_ail_latest_release( true );
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();
	wp_safe_redirect( admin_url( 'options-general.php?page=absel-ail' ) );
	exit;
} );

/**
 * One line for the settings page about updates.
 */
function absel_ail_update_status_html() {
	$release = absel_ail_latest_release();
	$check   = sprintf(
		' <a href="%s">%s</a>',
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=absel_ail_check_updates' ), 'absel_ail_check_updates' ) ),
		esc_html__( 'Check for updates now', 'absel-ai-library' )
	);
	if ( is_wp_error( $release ) ) {
		return '❌ ' . esc_html__( 'Updates from GitHub:', 'absel-ai-library' ) . ' ' . esc_html( $release->get_error_message() ) . $check;
	}
	if ( version_compare( $release['version'], ABSEL_AIL_VERSION, '>' ) ) {
		return '⬆️ ' . sprintf(
			/* translators: 1: new version, 2: Updates screen URL */
			wp_kses_post( __( 'Version %1$s is available. <a href="%2$s">Update it from Dashboard &rsaquo; Updates</a>.', 'absel-ai-library' ) ),
			esc_html( $release['version'] ),
			esc_url( admin_url( 'update-core.php' ) )
		) . $check;
	}
	/* translators: %s: version number */
	return '✅ ' . esc_html( sprintf( __( 'Up to date with the latest GitHub release (%s).', 'absel-ai-library' ), $release['version'] ) ) . $check;
}
