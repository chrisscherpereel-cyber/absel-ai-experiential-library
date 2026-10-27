<?php
/**
 * Exercise submissions from members: storage, validation and the REST API the
 * submission page talks to.
 *
 * A submission is an "absel_submission" post (a non-public post type) whose
 * manifest is kept in post meta and whose files sit in
 * ABSEL_AIL_CONTENT_DIR/_submissions/<post ID>/. Names starting with "_" are
 * never served by the file route, so submitted files can't be reached from the web.
 *
 * Flow: POST /submissions (manifest) -> POST /submissions/<id>/files (one file
 * per request, so large packages stay under PHP's upload limits) ->
 * POST /submissions/<id>/finish. A submission can revise an earlier one, in
 * which case files the author doesn't upload again are carried over.
 *
 * States (meta _absel_sub_state): uploading, submitted, returned, accepted, declined.
 */

defined( 'ABSPATH' ) || exit;

const ABSEL_AIL_MAX_FILE_BYTES = 100 * 1024 * 1024;

add_action( 'init', 'absel_ail_register_submissions' );

function absel_ail_register_submissions() {
	register_post_type( 'absel_submission', array(
		'labels'          => array(
			'name'          => __( 'Submissions', 'absel-ai-library' ),
			'singular_name' => __( 'Submission', 'absel-ai-library' ),
			'edit_item'     => __( 'Review submission', 'absel-ai-library' ),
			'search_items'  => __( 'Search submissions', 'absel-ai-library' ),
			'not_found'     => __( 'No submissions yet.', 'absel-ai-library' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'edit.php?post_type=absel_activity',
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'    => true,
	) );
}

function absel_ail_submission_states() {
	return array(
		'uploading' => __( 'Not finished', 'absel-ai-library' ),
		'submitted' => __( 'Waiting for review', 'absel-ai-library' ),
		'returned'  => __( 'Returned for changes', 'absel-ai-library' ),
		'accepted'  => __( 'Accepted', 'absel-ai-library' ),
		'declined'  => __( 'Declined', 'absel-ai-library' ),
	);
}

function absel_ail_submission_state( $id ) {
	return (string) get_post_meta( $id, '_absel_sub_state', true );
}

function absel_ail_submission_dir( $id ) {
	return ABSEL_AIL_CONTENT_DIR . '/_submissions/' . (int) $id;
}

/**
 * The submission's manifest as decoded JSON (objects as stdClass), or null.
 */
function absel_ail_submission_manifest( $id ) {
	$manifest = json_decode( (string) get_post_meta( $id, '_absel_sub_manifest', true ) );
	return is_object( $manifest ) ? $manifest : null;
}

/**
 * File names the manifest lists (links excluded).
 *
 * @return string[]
 */
function absel_ail_manifest_files( $manifest ) {
	$names = array();
	foreach ( (array) ( isset( $manifest->materials ) ? $manifest->materials : array() ) as $item ) {
		if ( is_object( $item ) && isset( $item->file ) && is_string( $item->file ) ) {
			$names[] = $item->file;
		}
	}
	return $names;
}

/**
 * Files already stored for a submission, name => size.
 *
 * @return array<string,int>
 */
function absel_ail_submission_files( $id ) {
	$dir   = absel_ail_submission_dir( $id );
	$files = array();
	if ( is_dir( $dir ) ) {
		foreach ( scandir( $dir ) as $name ) {
			if ( '.' !== $name[0] && is_file( $dir . '/' . $name ) ) {
				$files[ $name ] = filesize( $dir . '/' . $name );
			}
		}
	}
	return $files;
}

function absel_ail_max_upload() {
	return (int) min( ABSEL_AIL_MAX_FILE_BYTES, wp_max_upload_size() );
}

function absel_ail_submit_page_url() {
	$url = get_option( 'absel_ail_submit_page' );
	return $url ? $url : home_url( '/' );
}

/* ---------- validation ---------- */

function absel_ail_schema() {
	static $schema = null;
	if ( null === $schema ) {
		$schema = json_decode( (string) file_get_contents( ABSEL_AIL_DIR . 'assets/exercise.schema.json' ), true );
	}
	return $schema;
}

/**
 * Everything wrong with a manifest submitted for review, as "path: problem" lines.
 * The page checks the same things first; this is the server's own check.
 *
 * @return string[]
 */
function absel_ail_manifest_errors( $manifest ) {
	$errors = array();
	absel_ail_schema_check( absel_ail_schema(), $manifest, '', $errors );
	if ( $errors ) {
		return $errors;
	}
	// Nothing but the title is required. The author's one job is to check the assistant's guesses.
	foreach ( isset( $manifest->assumptions ) ? $manifest->assumptions : array() as $i => $assumption ) {
		if ( empty( $assumption->confirmed ) ) {
			$errors[] = "assumptions.{$i}: not yet checked by the author ({$assumption->field})";
		}
	}
	$names = absel_ail_manifest_files( $manifest );
	if ( count( $names ) !== count( array_unique( array_map( 'strtolower', $names ) ) ) ) {
		$errors[] = 'materials: a file is listed twice';
	}
	if ( in_array( 'manifest.json', array_map( 'strtolower', $names ), true ) ) {
		$errors[] = 'materials: manifest.json is a reserved name';
	}
	return $errors;
}

/**
 * A small JSON Schema validator covering the keywords exercise.schema.json uses.
 * $schema is an associative array; $value is decoded JSON with objects as stdClass.
 */
function absel_ail_schema_check( $schema, $value, $path, array &$errors ) {
	while ( isset( $schema['$ref'] ) ) {
		$schema = absel_ail_schema_ref( $schema['$ref'] );
	}
	$at = '' === $path ? '(manifest)' : $path;
	if ( array_key_exists( 'const', $schema ) && $value !== $schema['const'] ) {
		$errors[] = "{$at}: must be " . wp_json_encode( $schema['const'] );
		return;
	}
	if ( isset( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) {
		$errors[] = "{$at}: not one of the allowed values";
		return;
	}
	if ( isset( $schema['type'] ) ) {
		$ok = false;
		foreach ( (array) $schema['type'] as $type ) {
			$ok = $ok || absel_ail_schema_type_ok( $type, $value );
		}
		if ( ! $ok ) {
			$errors[] = "{$at}: must be " . implode( ' or ', (array) $schema['type'] );
			return;
		}
	}
	if ( is_string( $value ) ) {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		if ( isset( $schema['minLength'] ) && $length < $schema['minLength'] ) {
			$errors[] = "{$at}: too short";
		}
		if ( isset( $schema['maxLength'] ) && $length > $schema['maxLength'] ) {
			$errors[] = "{$at}: too long";
		}
		if ( isset( $schema['pattern'] ) && ! preg_match( "\x01" . $schema['pattern'] . "\x01u", $value ) ) {
			$errors[] = "{$at}: not in the expected format";
		}
		if ( isset( $schema['format'] ) && 'email' === $schema['format'] && ! is_email( $value ) ) {
			$errors[] = "{$at}: not an email address";
		}
		if ( isset( $schema['format'] ) && 'uri' === $schema['format'] && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			$errors[] = "{$at}: not a web address";
		}
	}
	if ( ( is_int( $value ) || is_float( $value ) ) && isset( $schema['minimum'] ) && $value < $schema['minimum'] ) {
		$errors[] = "{$at}: must be at least {$schema['minimum']}";
	}
	if ( is_array( $value ) ) {
		if ( isset( $schema['minItems'] ) && count( $value ) < $schema['minItems'] ) {
			$errors[] = "{$at}: needs at least {$schema['minItems']}";
		}
		if ( isset( $schema['maxItems'] ) && count( $value ) > $schema['maxItems'] ) {
			$errors[] = "{$at}: at most {$schema['maxItems']} allowed";
		}
		if ( ! empty( $schema['uniqueItems'] ) && count( $value ) !== count( array_unique( array_map( 'wp_json_encode', $value ) ) ) ) {
			$errors[] = "{$at}: has duplicates";
		}
		if ( isset( $schema['items'] ) ) {
			foreach ( $value as $i => $item ) {
				absel_ail_schema_check( $schema['items'], $item, ( '' === $path ? '' : $path . '.' ) . $i, $errors );
			}
		}
	}
	if ( is_object( $value ) ) {
		foreach ( isset( $schema['required'] ) ? $schema['required'] : array() as $key ) {
			if ( ! property_exists( $value, $key ) ) {
				$errors[] = ( '' === $path ? '' : $path . '.' ) . $key . ': required';
			}
		}
		foreach ( get_object_vars( $value ) as $key => $item ) {
			$child = ( '' === $path ? '' : $path . '.' ) . $key;
			if ( isset( $schema['properties'][ $key ] ) ) {
				absel_ail_schema_check( $schema['properties'][ $key ], $item, $child, $errors );
			} elseif ( isset( $schema['additionalProperties'] ) && false === $schema['additionalProperties'] ) {
				$errors[] = "{$child}: not an allowed field";
			}
		}
		if ( isset( $schema['anyOf'] ) ) {
			$any = false;
			foreach ( $schema['anyOf'] as $branch ) {
				$branch_errors = array();
				absel_ail_schema_check( $branch, $value, $path, $branch_errors );
				$any = $any || ! $branch_errors;
			}
			if ( ! $any ) {
				$errors[] = "{$at}: needs a file name or a link";
			}
		}
		if ( isset( $schema['if'] ) ) {
			$if_errors = array();
			absel_ail_schema_check( $schema['if'], $value, $path, $if_errors );
			if ( ! $if_errors && isset( $schema['then'] ) ) {
				absel_ail_schema_check( $schema['then'], $value, $path, $errors );
			}
		}
	}
}

function absel_ail_schema_ref( $ref ) {
	$node = absel_ail_schema();
	foreach ( explode( '/', substr( $ref, 2 ) ) as $key ) {
		$node = $node[ $key ];
	}
	return $node;
}

function absel_ail_schema_type_ok( $type, $value ) {
	switch ( $type ) {
		case 'null':
			return null === $value;
		case 'integer':
			return is_int( $value );
		case 'number':
			return is_int( $value ) || is_float( $value );
		case 'string':
			return is_string( $value );
		case 'boolean':
			return is_bool( $value );
		case 'array':
			return is_array( $value );
		case 'object':
			return is_object( $value );
	}
	return false;
}

/* ---------- REST API ---------- */

add_action( 'rest_api_init', function () {
	$ns     = 'absel-ail/v1';
	$member = array( 'permission_callback' => 'absel_ail_rest_permission' );
	register_rest_route( $ns, '/submissions', array(
		array( 'methods' => 'GET', 'callback' => 'absel_ail_rest_list' ) + $member,
		array( 'methods' => 'POST', 'callback' => 'absel_ail_rest_create' ) + $member,
	) );
	register_rest_route( $ns, '/submissions/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => 'absel_ail_rest_get' ) + $member );
	register_rest_route( $ns, '/submissions/(?P<id>\d+)/files', array( 'methods' => 'POST', 'callback' => 'absel_ail_rest_upload' ) + $member );
	register_rest_route( $ns, '/submissions/(?P<id>\d+)/finish', array( 'methods' => 'POST', 'callback' => 'absel_ail_rest_finish' ) + $member );
} );

function absel_ail_rest_permission( WP_REST_Request $request ) {
	if ( ! absel_ail_member_has_access() || '' === absel_ail_current_owner() ) {
		return new WP_Error( 'absel_ail_forbidden', __( 'Log in as an ABSEL member to submit exercises.', 'absel-ai-library' ), array( 'status' => 403 ) );
	}
	if ( ! absel_ail_submit_token_ok( $request->get_header( 'X-Absel-Token' ) ) ) {
		return new WP_Error( 'absel_ail_token', __( 'Your session has expired. Reload the page and try again; your draft is saved.', 'absel-ai-library' ), array( 'status' => 403 ) );
	}
	return true;
}

/**
 * One of the current member's submissions, or an error.
 *
 * @return WP_Post|WP_Error
 */
function absel_ail_own_submission( $id ) {
	$post = get_post( (int) $id );
	if ( ! $post || 'absel_submission' !== $post->post_type || get_post_meta( $post->ID, '_absel_sub_owner', true ) !== absel_ail_current_owner() ) {
		return new WP_Error( 'absel_ail_not_found', __( 'Submission not found.', 'absel-ai-library' ), array( 'status' => 404 ) );
	}
	return $post;
}

function absel_ail_rest_list() {
	$posts = get_posts( array(
		'post_type'      => 'absel_submission',
		'post_status'    => 'any',
		'posts_per_page' => 50,
		'meta_key'       => '_absel_sub_owner', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => absel_ail_current_owner(), // phpcs:ignore WordPress.DB.SlowDBQuery
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	$states = absel_ail_submission_states();
	$list   = array();
	foreach ( $posts as $post ) {
		$state = absel_ail_submission_state( $post->ID );
		if ( 'uploading' === $state ) {
			continue;
		}
		$list[] = array(
			'id'      => $post->ID,
			'title'   => $post->post_title,
			'state'   => $state,
			'label'   => isset( $states[ $state ] ) ? $states[ $state ] : $state,
			'date'    => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) get_post_meta( $post->ID, '_absel_sub_submitted', true ) ), get_option( 'date_format' ) ),
			'message' => in_array( $state, array( 'returned', 'declined', 'accepted' ), true ) ? (string) get_post_meta( $post->ID, '_absel_sub_message', true ) : '',
		);
	}
	return $list;
}

function absel_ail_rest_get( WP_REST_Request $request ) {
	$post = absel_ail_own_submission( $request['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	return array(
		'id'       => $post->ID,
		'manifest' => absel_ail_submission_manifest( $post->ID ),
		'files'    => array_keys( absel_ail_submission_files( $post->ID ) ),
	);
}

function absel_ail_rest_create( WP_REST_Request $request ) {
	$body = json_decode( $request->get_body() );
	if ( ! is_object( $body ) || ! isset( $body->manifest ) || ! is_object( $body->manifest ) ) {
		return new WP_Error( 'absel_ail_bad_request', __( 'No exercise details were received.', 'absel-ai-library' ), array( 'status' => 400 ) );
	}
	$manifest = $body->manifest;
	$problems = absel_ail_manifest_errors( $manifest );
	if ( $problems ) {
		return new WP_Error( 'absel_ail_invalid', __( 'Some details need fixing before this can be submitted.', 'absel-ai-library' ), array( 'status' => 400, 'problems' => $problems ) );
	}
	// Submitting is the author's statement that they can share the materials, and that
	// they've checked the assistant's guesses (enforced above).
	if ( ! isset( $manifest->rights ) || ! is_object( $manifest->rights ) ) {
		$manifest->rights = new stdClass();
	}
	$manifest->rights->author_owns_or_has_permission = true;
	$manifest->author_confirmed                      = true;
	if ( empty( $manifest->slug ) ) {
		$manifest->slug = trim( substr( preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $manifest->title ) ) ), 0, 60 ), '-' );
		if ( '' === $manifest->slug ) {
			unset( $manifest->slug );
		}
	}
	$replaces = isset( $body->replaces ) ? (int) $body->replaces : 0;
	if ( $replaces ) {
		$earlier = absel_ail_own_submission( $replaces );
		if ( is_wp_error( $earlier ) ) {
			return $earlier;
		}
	}

	$id = wp_insert_post( array(
		'post_type'   => 'absel_submission',
		'post_status' => 'publish', // The post type itself is not public.
		'post_title'  => wp_strip_all_tags( $manifest->title ),
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$contact = absel_ail_owner_contact();
	update_post_meta( $id, '_absel_sub_owner', absel_ail_current_owner() );
	update_post_meta( $id, '_absel_sub_name', sanitize_text_field( $contact['name'] ) );
	update_post_meta( $id, '_absel_sub_email', sanitize_email( $contact['email'] ) );
	update_post_meta( $id, '_absel_sub_manifest', wp_slash( wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
	update_post_meta( $id, '_absel_sub_state', 'uploading' );
	if ( $replaces ) {
		update_post_meta( $id, '_absel_sub_replaces', $replaces );
	}
	wp_mkdir_p( absel_ail_submission_dir( $id ) );

	return array(
		'id'      => $id,
		'carried' => $replaces ? array_values( array_intersect( absel_ail_manifest_files( $manifest ), array_keys( absel_ail_submission_files( $replaces ) ) ) ) : array(),
	);
}

function absel_ail_rest_upload( WP_REST_Request $request ) {
	$post = absel_ail_own_submission( $request['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	if ( 'uploading' !== absel_ail_submission_state( $post->ID ) ) {
		return new WP_Error( 'absel_ail_closed', __( 'This submission has already been sent.', 'absel-ai-library' ), array( 'status' => 409 ) );
	}
	$name  = (string) $request->get_param( 'name' );
	$files = $request->get_file_params();
	$file  = isset( $files['file'] ) ? $files['file'] : null;
	if ( ! in_array( $name, absel_ail_manifest_files( absel_ail_submission_manifest( $post->ID ) ), true ) ) {
		return new WP_Error( 'absel_ail_unlisted', __( 'That file is not in the list of files.', 'absel-ai-library' ), array( 'status' => 400 ) );
	}
	$types = absel_ail_mime_types();
	if ( false !== strpos( $name, '/' ) || ! isset( $types[ strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ] ) ) {
		return new WP_Error( 'absel_ail_type', __( 'This type of file can\'t be uploaded.', 'absel-ai-library' ), array( 'status' => 400 ) );
	}
	if ( ! $file || UPLOAD_ERR_OK !== $file['error'] ) {
		$too_big = $file && in_array( $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true );
		return new WP_Error(
			'absel_ail_upload',
			$too_big ? __( 'The file is larger than this server accepts.', 'absel-ai-library' ) : __( 'The file did not arrive. Try again.', 'absel-ai-library' ),
			array( 'status' => $too_big ? 413 : 400 )
		);
	}
	if ( $file['size'] > absel_ail_max_upload() ) {
		return new WP_Error( 'absel_ail_too_big', __( 'The file is too large.', 'absel-ai-library' ), array( 'status' => 413 ) );
	}
	$target = absel_ail_submission_dir( $post->ID ) . '/' . $name;
	if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
		return new WP_Error( 'absel_ail_store', __( 'The file could not be saved on the server.', 'absel-ai-library' ), array( 'status' => 500 ) );
	}
	return array(
		'name' => $name,
		'size' => filesize( $target ),
	);
}

function absel_ail_rest_finish( WP_REST_Request $request ) {
	$post = absel_ail_own_submission( $request['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	if ( 'uploading' !== absel_ail_submission_state( $post->ID ) ) {
		return new WP_Error( 'absel_ail_closed', __( 'This submission has already been sent.', 'absel-ai-library' ), array( 'status' => 409 ) );
	}
	// Files the author listed but didn't attach are allowed; editors see them as not received.
	$dir      = absel_ail_submission_dir( $post->ID );
	$replaces = (int) get_post_meta( $post->ID, '_absel_sub_replaces', true );
	foreach ( absel_ail_manifest_files( absel_ail_submission_manifest( $post->ID ) ) as $name ) {
		$earlier = $replaces ? absel_ail_submission_dir( $replaces ) . '/' . $name : '';
		if ( ! is_file( $dir . '/' . $name ) && $earlier && false === strpos( $name, '/' ) && is_file( $earlier ) ) {
			copy( $earlier, $dir . '/' . $name );
		}
	}
	update_post_meta( $post->ID, '_absel_sub_state', 'submitted' );
	update_post_meta( $post->ID, '_absel_sub_submitted', time() );
	absel_ail_notify_submitted( $post->ID );
	return array( 'id' => $post->ID );
}

/* ---------- email ---------- */

function absel_ail_editor_emails() {
	$list = array_filter( array_map( 'sanitize_email', explode( ',', (string) absel_ail_settings()['editor_emails'] ) ) );
	return $list ? $list : array( get_option( 'admin_email' ) );
}

function absel_ail_notify_submitted( $id ) {
	$title    = get_post_field( 'post_title', $id );
	$name     = get_post_meta( $id, '_absel_sub_name', true );
	$email    = get_post_meta( $id, '_absel_sub_email', true );
	$replaces = (int) get_post_meta( $id, '_absel_sub_replaces', true );
	wp_mail(
		absel_ail_editor_emails(),
		/* translators: %s: exercise title */
		sprintf( __( 'New exercise submission: %s', 'absel-ai-library' ), $title ),
		sprintf(
			/* translators: 1: title, 2: submitter, 3: email, 4: revision note, 5: review link */
			__( "\"%1\$s\" was submitted by %2\$s (%3\$s).%4\$s\n\nReview it here:\n%5\$s", 'absel-ai-library' ),
			$title,
			$name,
			$email,
			/* translators: %d: earlier submission number */
			$replaces ? ' ' . sprintf( __( 'It revises submission #%d.', 'absel-ai-library' ), $replaces ) : '',
			admin_url( 'post.php?post=' . (int) $id . '&action=edit' )
		)
	);
	if ( $email ) {
		wp_mail(
			$email,
			/* translators: %s: exercise title */
			sprintf( __( 'We received your exercise: %s', 'absel-ai-library' ), $title ),
			sprintf(
				/* translators: 1: name, 2: title, 3: reference number, 4: submission page */
				__( "Hello %1\$s,\n\nThank you for submitting \"%2\$s\" to the ABSEL Exercise Library (reference #%3\$d). The editors will review it and email you.\n\nYou can see its status at any time on the submission page:\n%4\$s", 'absel-ai-library' ),
				$name ? $name : __( 'there', 'absel-ai-library' ),
				$title,
				$id,
				absel_ail_submit_page_url()
			)
		);
	}
}

/* ---------- housekeeping ---------- */

// Submissions abandoned part-way through uploading are removed after two days.
add_action( 'absel_ail_cleanup', function () {
	$stale = get_posts( array(
		'post_type'      => 'absel_submission',
		'post_status'    => 'any',
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'meta_key'       => '_absel_sub_state', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => 'uploading', // phpcs:ignore WordPress.DB.SlowDBQuery
		'date_query'     => array( array( 'before' => '2 days ago' ) ),
	) );
	foreach ( $stale as $id ) {
		wp_delete_post( $id, true );
	}
} );

add_action( 'before_delete_post', function ( $id ) {
	if ( 'absel_submission' === get_post_type( $id ) ) {
		absel_ail_remove_dir( absel_ail_submission_dir( $id ) );
	}
} );

/**
 * Deletes a submission folder and its files (submission folders have no subfolders).
 */
function absel_ail_remove_dir( $dir ) {
	$base = realpath( ABSEL_AIL_CONTENT_DIR . '/_submissions' );
	$real = realpath( $dir );
	if ( ! $base || ! $real || 0 !== strpos( $real, $base . DIRECTORY_SEPARATOR ) ) {
		return;
	}
	foreach ( scandir( $real ) as $name ) {
		if ( is_file( $real . '/' . $name ) ) {
			unlink( $real . '/' . $name );
		}
	}
	rmdir( $real );
}
