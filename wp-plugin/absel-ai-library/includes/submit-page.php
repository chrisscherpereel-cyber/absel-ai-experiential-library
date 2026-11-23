<?php
/**
 * The members-only "Submit an exercise" page.
 *
 * Put [absel_submit_exercise] on any page. Members see the submission form
 * (assets/submit.html, served from /ail-submit/ and embedded in a frame so the
 * theme's styles can't interfere with it); everyone else sees how to log in.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'absel_ail_register_submit_route' );

function absel_ail_register_submit_route() {
	add_rewrite_rule( '^ail-submit/?$', 'index.php?absel_ail_submit=1', 'top' );
}

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'absel_ail_submit';
	return $vars;
} );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'absel_ail_submit' ) ) {
		// The page holding the shortcode shows different things to members and others.
		if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'absel_submit_exercise' ) ) {
			nocache_headers();
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
		}
		return;
	}
	if ( ! absel_ail_member_has_access() || '' === absel_ail_current_owner() ) {
		wp_safe_redirect( absel_ail_login_url() );
		exit;
	}
	$config = array(
		'api'        => esc_url_raw( rest_url( 'absel-ail/v1/' ) ),
		'wpNonce'    => wp_create_nonce( 'wp_rest' ),
		'token'      => absel_ail_submit_token(),
		'maxUpload'  => absel_ail_max_upload(),
		'extensions' => array_keys( absel_ail_mime_types() ),
		'contact'    => absel_ail_owner_contact(),
	);
	$html = (string) file_get_contents( ABSEL_AIL_DIR . 'assets/submit.html' );
	$html = str_replace(
		'<script type="application/json" id="absel-schema">',
		'<script>window.ABSEL_SUBMIT = ' . wp_json_encode( $config ) . ";</script>\n" . '<script type="application/json" id="absel-schema">',
		$html
	);
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( "Content-Security-Policy: frame-ancestors 'self'" );
	header( 'X-Robots-Tag: noindex, nofollow' );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- the plugin's own page.
	exit;
}, 0 );

add_shortcode( 'absel_submit_exercise', function () {
	$permalink = get_permalink();
	if ( $permalink && get_option( 'absel_ail_submit_page' ) !== $permalink ) {
		update_option( 'absel_ail_submit_page', $permalink, false );
	}
	$banner = '<div class="selar"><div class="selar-banner selar-banner--put">' . absel_ail_shelf_icon( 'put' )
		. '<div><h2>' . esc_html__( 'Put it in the SELAR.', 'absel-ai-library' ) . '</h2>'
		. '<p>' . esc_html__( 'Have a classroom exercise you’d gladly teach again? Anything from an idea to a finished simulation is welcome.', 'absel-ai-library' ) . '</p></div></div></div>';
	if ( ! absel_ail_member_has_access() ) {
		return $banner . '<p class="selar selar-notice">' . sprintf(
			/* translators: 1: login URL, 2: join URL */
			wp_kses_post( __( 'Submitting exercises is open to ABSEL members. <a href="%1$s">Log in</a> or <a href="%2$s">become a member</a>.', 'absel-ai-library' ) ),
			esc_url( absel_ail_login_url() ),
			esc_url( absel_ail_join_url() )
		) . '</p>';
	}
	$src = home_url( '/ail-submit/' );
	// The form reports its height so the frame never needs its own scrollbar.
	return $banner . sprintf(
		'<iframe id="absel-submit-frame" src="%1$s" title="%2$s" style="display:block;width:100%%;min-height:900px;border:0"></iframe>'
		. '<p style="font-size:.9em"><a href="%1$s" target="_blank" rel="noopener">%3$s</a></p>'
		. '<script>(function(){var f=document.getElementById("absel-submit-frame");window.addEventListener("message",function(e){'
		. 'if(e.origin!==location.origin||e.source!==f.contentWindow||!e.data||e.data.type!=="absel-submit-height")return;'
		. 'f.style.height=Math.max(600,+e.data.height||0)+"px";});})();</script>',
		esc_url( $src ),
		esc_attr__( 'Submit an exercise', 'absel-ai-library' ),
		esc_html__( 'Open the form in its own window', 'absel-ai-library' )
	);
} );
