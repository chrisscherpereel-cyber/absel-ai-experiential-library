<?php
/**
 * Plugin Name:       ABSEL AI Experiential Library
 * Description:       Members-only library of experiential learning activities and simulations, gated by Simple Membership, with member submissions and an editor review queue.
 * Version:           0.4.1
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Author:            ABSEL
 * License:           GPL-2.0-or-later
 * Text Domain:       absel-ai-library
 * Update URI:        https://github.com/chrisscherpereel-cyber/absel-ai-experiential-library
 */

defined( 'ABSPATH' ) || exit;

// Keep in step with "Version" above; a release is published by pushing the tag v<version>.
define( 'ABSEL_AIL_VERSION', '0.4.1' );
define( 'ABSEL_AIL_FILE', __FILE__ );
define( 'ABSEL_AIL_DIR', plugin_dir_path( __FILE__ ) );

// Where activity folders (simulations, PDFs, handouts) live on the server.
// Override in wp-config.php to keep them outside the web root, e.g.
// define( 'ABSEL_AIL_CONTENT_DIR', '/home/youraccount/absel-ai-library' );
if ( ! defined( 'ABSEL_AIL_CONTENT_DIR' ) ) {
	define( 'ABSEL_AIL_CONTENT_DIR', WP_CONTENT_DIR . '/absel-ai-library' );
}

require_once ABSEL_AIL_DIR . 'includes/access.php';
require_once ABSEL_AIL_DIR . 'includes/post-types.php';
require_once ABSEL_AIL_DIR . 'includes/files.php';
require_once ABSEL_AIL_DIR . 'includes/settings.php';
require_once ABSEL_AIL_DIR . 'includes/submissions.php';
require_once ABSEL_AIL_DIR . 'includes/review.php';
require_once ABSEL_AIL_DIR . 'includes/submit-page.php';
require_once ABSEL_AIL_DIR . 'includes/updater.php';

register_activation_hook( __FILE__, 'absel_ail_activate' );
register_deactivation_hook( __FILE__, 'absel_ail_deactivate' );

function absel_ail_activate() {
	absel_ail_register_types();
	absel_ail_register_submissions();
	absel_ail_register_file_route();
	absel_ail_register_submit_route();
	absel_ail_install_content_dir();
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'absel_ail_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'absel_ail_cleanup' );
	}
	update_option( 'absel_ail_version', ABSEL_AIL_VERSION );
}

function absel_ail_deactivate() {
	wp_clear_scheduled_hook( 'absel_ail_cleanup' );
	flush_rewrite_rules();
}

// Uploading a new version over the old one doesn't run the activation hook,
// so redo its setup (new URL routes, the cleanup schedule) once per version.
add_action( 'init', function () {
	if ( get_option( 'absel_ail_version' ) !== ABSEL_AIL_VERSION ) {
		absel_ail_activate();
	}
}, 20 );

// Simple Membership does the authentication; without it nothing is reachable.
add_action( 'admin_notices', function () {
	if ( ! class_exists( 'SwpmAuth' ) && current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'ABSEL AI Library needs the Simple Membership plugin to be active. Until it is, only administrators can open activities.', 'absel-ai-library' );
		echo '</p></div>';
	}
} );
