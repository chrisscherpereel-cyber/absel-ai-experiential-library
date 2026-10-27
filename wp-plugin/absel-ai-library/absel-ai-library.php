<?php
/**
 * Plugin Name:       ABSEL AI Experiential Library
 * Description:       Members-only library of AI experiential learning activities and simulations, gated by Simple Membership.
 * Version:           0.2.0
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Author:            ABSEL
 * License:           GPL-2.0-or-later
 * Text Domain:       absel-ai-library
 */

defined( 'ABSPATH' ) || exit;

define( 'ABSEL_AIL_VERSION', '0.2.0' );
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

register_activation_hook( __FILE__, 'absel_ail_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function absel_ail_activate() {
	absel_ail_register_types();
	absel_ail_register_file_route();
	absel_ail_install_content_dir();
	flush_rewrite_rules();
}

// Simple Membership does the authentication; without it nothing is reachable.
add_action( 'admin_notices', function () {
	if ( ! class_exists( 'SwpmAuth' ) && current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'ABSEL AI Library needs the Simple Membership plugin to be active. Until it is, only administrators can open activities.', 'absel-ai-library' );
		echo '</p></div>';
	}
} );
