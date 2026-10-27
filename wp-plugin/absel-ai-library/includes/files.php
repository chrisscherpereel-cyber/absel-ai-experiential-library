<?php
/**
 * Serves activity files to members only.
 *
 * Files live in ABSEL_AIL_CONTENT_DIR, where direct web access is denied, and are
 * reached through https://site/ail-files/<folder>/<file>, which runs the
 * membership check first. Paths whose name starts with "_" or "." (such as
 * _notes.md) are server-side only and never served.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'absel_ail_register_file_route' );

function absel_ail_register_file_route() {
	add_rewrite_rule( '^ail-files/(.+)$', 'index.php?absel_ail_file=$matches[1]', 'top' );
}

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'absel_ail_file';
	return $vars;
} );

function absel_ail_file_url( $relative ) {
	return home_url( '/ail-files/' . ltrim( $relative, '/' ) );
}

function absel_ail_install_content_dir() {
	$dir = ABSEL_AIL_CONTENT_DIR;
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	// Belt and braces for a directory inside the web root; harmless outside it.
	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		file_put_contents( $htaccess, "# Direct access is blocked. Members reach these files through /ail-files/.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n" );
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
	}
}

add_action( 'template_redirect', 'absel_ail_serve_file', 0 );

function absel_ail_serve_file() {
	$relative = (string) get_query_var( 'absel_ail_file' );
	if ( '' === $relative ) {
		return;
	}
	$relative = str_replace( '\\', '/', rawurldecode( $relative ) );
	$is_html  = (bool) preg_match( '/\.html?$/i', $relative );
	$folder   = absel_ail_sanitize_folder( strtok( $relative, '/' ) );

	if ( ! absel_ail_member_has_access() ) {
		$pass = absel_ail_class_pass_from_request( $folder );
		// Student links open the simulation and its assets, not instructor materials.
		if ( ! $pass || ! preg_match( '/\.(html?|css|js|json|svg|png|jpe?g|gif|webp|mp4)$/i', $relative ) ) {
			if ( $is_html && ! $pass ) {
				wp_safe_redirect( absel_ail_login_url() );
				exit;
			}
			absel_ail_file_error( 403 );
		}
	}

	foreach ( explode( '/', $relative ) as $segment ) {
		if ( '' === $segment || '.' === $segment[0] || '_' === $segment[0] ) {
			absel_ail_file_error( 404 );
		}
	}
	// The manifest holds author emails and rights details meant for editors.
	if ( 0 === strcasecmp( basename( $relative ), 'manifest.json' ) ) {
		absel_ail_file_error( 404 );
	}
	$base = realpath( ABSEL_AIL_CONTENT_DIR );
	$path = $base ? realpath( $base . '/' . $relative ) : false;
	if ( ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
		absel_ail_file_error( 404 );
	}

	$type = absel_ail_mime_type( $path );
	if ( ! $type ) {
		absel_ail_file_error( 404 );
	}

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: ' . $type );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, nofollow' );

	if ( ! preg_match( '/\.(html?|css|js|json|svg|png|jpe?g|gif|webp|mp4)$/i', $path ) ) {
		header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
	}
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path );
	exit;
}

/**
 * A student link arrives as ?class=... on the simulation URL. It is then kept in
 * a cookie scoped to that activity's folder so the page's CSS, JS and images,
 * requested by relative URL without the query string, load too.
 */
function absel_ail_class_pass_from_request( $folder ) {
	$cookie = 'absel_ail_class_' . $folder;
	// phpcs:ignore WordPress.Security.NonceVerification -- signed link, not a form.
	if ( isset( $_GET['class'] ) ) {
		$raw  = sanitize_text_field( wp_unslash( $_GET['class'] ) );
		$pass = absel_ail_class_pass_verify( $raw, $folder );
		if ( $pass && ! headers_sent() ) {
			$path = trailingslashit( wp_parse_url( absel_ail_file_url( $folder ), PHP_URL_PATH ) );
			setcookie( $cookie, $raw, array(
				'expires'  => $pass['expires'],
				'path'     => $path,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			) );
		}
	} elseif ( isset( $_COOKIE[ $cookie ] ) ) {
		$pass = absel_ail_class_pass_verify( sanitize_text_field( wp_unslash( $_COOKIE[ $cookie ] ) ), $folder );
	} else {
		return null;
	}
	return $pass;
}

function absel_ail_file_error( $code ) {
	status_header( $code );
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo 403 === $code ? 'Members only.' : 'Not found.';
	exit;
}

function absel_ail_mime_type( $path ) {
	$types = absel_ail_mime_types();
	$ext   = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	return isset( $types[ $ext ] ) ? $types[ $ext ] : null;
}

/**
 * File types the library serves, by extension. Submissions accept only these.
 */
function absel_ail_mime_types() {
	return array(
		'html' => 'text/html; charset=utf-8',
		'htm'  => 'text/html; charset=utf-8',
		'css'  => 'text/css; charset=utf-8',
		'js'   => 'text/javascript; charset=utf-8',
		'json' => 'application/json',
		'svg'  => 'image/svg+xml',
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'pdf'  => 'application/pdf',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'csv'  => 'text/csv; charset=utf-8',
		'zip'  => 'application/zip',
		'mp4'  => 'video/mp4',
	);
}
