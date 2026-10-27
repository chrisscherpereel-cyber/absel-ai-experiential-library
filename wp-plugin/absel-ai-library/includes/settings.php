<?php
/**
 * Settings > AI Library: setup status and which membership levels get in.
 */

defined( 'ABSPATH' ) || exit;

function absel_ail_settings() {
	return wp_parse_args( (array) get_option( 'absel_ail_settings', array() ), array(
		'allowed_levels' => '',
		'editor_emails'  => '',
	) );
}

/**
 * Simple Membership level IDs allowed in; an empty list means every active member.
 *
 * @return int[]
 */
function absel_ail_allowed_levels() {
	return absel_ail_parse_levels( absel_ail_settings()['allowed_levels'] );
}

function absel_ail_parse_levels( $list ) {
	return array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $list ) ) ) ) );
}

add_action( 'admin_init', function () {
	register_setting( 'absel_ail', 'absel_ail_settings', array(
		'type'              => 'array',
		'sanitize_callback' => function ( $input ) {
			$input = (array) $input;
			$emails = array_filter( array_map( 'sanitize_email', explode( ',', isset( $input['editor_emails'] ) ? (string) $input['editor_emails'] : '' ) ) );
			return array(
				'allowed_levels' => implode( ',', absel_ail_parse_levels( isset( $input['allowed_levels'] ) ? $input['allowed_levels'] : '' ) ),
				'editor_emails'  => implode( ', ', $emails ),
			);
		},
	) );
} );

add_action( 'admin_menu', function () {
	add_options_page( __( 'AI Library', 'absel-ai-library' ), __( 'AI Library', 'absel-ai-library' ), 'manage_options', 'absel-ail', 'absel_ail_settings_page' );
} );

function absel_ail_settings_page() {
	global $wpdb;
	$s      = absel_ail_settings();
	$levels = array();
	if ( class_exists( 'SwpmAuth' ) ) {
		// Level 1 is Simple Membership's internal content-protection record, not a real level.
		$levels = $wpdb->get_results( "SELECT id, alias FROM {$wpdb->prefix}swpm_membership_tbl WHERE id > 1 ORDER BY id" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'ABSEL AI Library', 'absel-ai-library' ); ?></h1>

		<h2><?php esc_html_e( 'Status', 'absel-ai-library' ); ?></h2>
		<ul>
			<li><?php echo class_exists( 'SwpmAuth' ) ? '✅ ' . esc_html__( 'Simple Membership is active.', 'absel-ai-library' ) : '❌ ' . esc_html__( 'Simple Membership is not active.', 'absel-ai-library' ); ?></li>
			<li><?php echo is_dir( ABSEL_AIL_CONTENT_DIR ) ? '✅ ' : '❌ '; ?><?php esc_html_e( 'Activity folder location:', 'absel-ai-library' ); ?> <code><?php echo esc_html( ABSEL_AIL_CONTENT_DIR ); ?></code></li>
			<li><?php
				$submit_page = get_option( 'absel_ail_submit_page' );
				if ( $submit_page ) {
					echo '✅ ' . esc_html__( 'Submission page:', 'absel-ai-library' ) . ' <a href="' . esc_url( $submit_page ) . '">' . esc_html( $submit_page ) . '</a>';
				} else {
					echo '❌ ' . esc_html__( 'No submission page yet. Add the shortcode [absel_submit_exercise] to a page and view it once.', 'absel-ai-library' );
				}
			?></li>
			<li><?php
				/* translators: %s: file size */
				echo 'ℹ️ ' . esc_html( sprintf( __( 'Largest file members can upload: %s (raise PHP\'s upload_max_filesize and post_max_size to allow up to 100 MB).', 'absel-ai-library' ), size_format( absel_ail_max_upload() ) ) );
			?></li>
		</ul>

		<form method="post" action="options.php">
			<?php settings_fields( 'absel_ail' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ail-levels"><?php esc_html_e( 'Membership levels allowed', 'absel-ai-library' ); ?></label></th>
					<td>
						<input id="ail-levels" class="regular-text" name="absel_ail_settings[allowed_levels]" value="<?php echo esc_attr( $s['allowed_levels'] ); ?>" placeholder="2,3">
						<p class="description"><?php esc_html_e( 'Comma-separated level IDs. Leave empty to allow every active member.', 'absel-ai-library' ); ?>
						<?php foreach ( $levels as $level ) : ?>
							<br><code><?php echo (int) $level->id; ?></code> <?php echo esc_html( $level->alias ); ?>
						<?php endforeach; ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ail-editors"><?php esc_html_e( 'Notify editors at', 'absel-ai-library' ); ?></label></th>
					<td>
						<input id="ail-editors" class="regular-text" name="absel_ail_settings[editor_emails]" value="<?php echo esc_attr( $s['editor_emails'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Comma-separated addresses that get an email for each new exercise submission. Leave empty to use the site admin address.', 'absel-ai-library' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
