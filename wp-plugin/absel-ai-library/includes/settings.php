<?php
/**
 * Settings > AI Library: which membership levels get in, the AI model and
 * limits, and this month's usage.
 */

defined( 'ABSPATH' ) || exit;

function absel_ail_settings() {
	$defaults = array(
		'allowed_levels' => '',
		'model'          => 'claude-opus-5-5',
		'effort'         => 'low',
		'max_tokens'     => 2000,
		'daily_limit'       => 60,
		'class_daily_limit' => 1500,
	);
	return wp_parse_args( (array) get_option( 'absel_ail_settings', array() ), $defaults );
}

/**
 * Simple Membership level IDs allowed in; an empty list means every active member.
 *
 * @return int[]
 */
function absel_ail_allowed_levels() {
	$ids = array_filter( array_map( 'absint', explode( ',', absel_ail_settings()['allowed_levels'] ) ) );
	return array_values( $ids );
}

add_action( 'admin_init', function () {
	register_setting( 'absel_ail', 'absel_ail_settings', array(
		'type'              => 'array',
		'sanitize_callback' => function ( $input ) {
			$input = (array) $input;
			return array(
				'allowed_levels' => implode( ',', array_filter( array_map( 'absint', explode( ',', isset( $input['allowed_levels'] ) ? $input['allowed_levels'] : '' ) ) ) ),
				'model'          => preg_replace( '/[^a-z0-9.-]/', '', strtolower( isset( $input['model'] ) ? $input['model'] : '' ) ),
				'effort'         => in_array( isset( $input['effort'] ) ? $input['effort'] : '', array( 'low', 'medium', 'high' ), true ) ? $input['effort'] : '',
				'max_tokens'     => min( 16000, max( 256, absint( isset( $input['max_tokens'] ) ? $input['max_tokens'] : 2000 ) ) ),
				'daily_limit'    => absint( isset( $input['daily_limit'] ) ? $input['daily_limit'] : 0 ),
				'class_daily_limit' => absint( isset( $input['class_daily_limit'] ) ? $input['class_daily_limit'] : 0 ),
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
	$field = function ( $key ) {
		return 'absel_ail_settings[' . $key . ']';
	};
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'ABSEL AI Library', 'absel-ai-library' ); ?></h1>

		<h2><?php esc_html_e( 'Status', 'absel-ai-library' ); ?></h2>
		<ul>
			<li><?php echo class_exists( 'SwpmAuth' ) ? '✅ ' . esc_html__( 'Simple Membership is active.', 'absel-ai-library' ) : '❌ ' . esc_html__( 'Simple Membership is not active.', 'absel-ai-library' ); ?></li>
			<li><?php echo defined( 'ABSEL_AIL_ANTHROPIC_KEY' ) && ABSEL_AIL_ANTHROPIC_KEY ? '✅ ' . esc_html__( 'API key is set in wp-config.php.', 'absel-ai-library' ) : '❌ ' . esc_html__( 'No API key: add define( \'ABSEL_AIL_ANTHROPIC_KEY\', \'...\' ); to wp-config.php.', 'absel-ai-library' ); ?></li>
			<li><?php echo is_dir( ABSEL_AIL_CONTENT_DIR ) ? '✅ ' : '❌ '; ?><?php esc_html_e( 'Activity folder location:', 'absel-ai-library' ); ?> <code><?php echo esc_html( ABSEL_AIL_CONTENT_DIR ); ?></code></li>
		</ul>

		<form method="post" action="options.php">
			<?php settings_fields( 'absel_ail' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ail-levels"><?php esc_html_e( 'Membership levels allowed', 'absel-ai-library' ); ?></label></th>
					<td>
						<input id="ail-levels" class="regular-text" name="<?php echo esc_attr( $field( 'allowed_levels' ) ); ?>" value="<?php echo esc_attr( $s['allowed_levels'] ); ?>" placeholder="2,3">
						<p class="description"><?php esc_html_e( 'Comma-separated level IDs. Leave empty to allow every active member.', 'absel-ai-library' ); ?>
						<?php foreach ( $levels as $level ) : ?>
							<br><code><?php echo (int) $level->id; ?></code> <?php echo esc_html( $level->alias ); ?>
						<?php endforeach; ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ail-model"><?php esc_html_e( 'Claude model', 'absel-ai-library' ); ?></label></th>
					<td>
						<input id="ail-model" class="regular-text" name="<?php echo esc_attr( $field( 'model' ) ); ?>" value="<?php echo esc_attr( $s['model'] ); ?>">
						<p class="description"><?php esc_html_e( 'claude-opus-5-5 (most capable), claude-sonnet-5-5 (about half the cost), or claude-haiku-5-5 (cheapest).', 'absel-ai-library' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ail-effort"><?php esc_html_e( 'Effort', 'absel-ai-library' ); ?></label></th>
					<td>
						<select id="ail-effort" name="<?php echo esc_attr( $field( 'effort' ) ); ?>">
							<?php foreach ( array( 'low', 'medium', 'high', '' ) as $effort ) : ?>
								<option value="<?php echo esc_attr( $effort ); ?>" <?php selected( $s['effort'], $effort ); ?>><?php echo $effort ? esc_html( $effort ) : esc_html__( 'Model default', 'absel-ai-library' ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Low suits conversational role-play and keeps cost and response time down.', 'absel-ai-library' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ail-max"><?php esc_html_e( 'Max tokens per reply', 'absel-ai-library' ); ?></label></th>
					<td><input id="ail-max" type="number" min="256" max="16000" name="<?php echo esc_attr( $field( 'max_tokens' ) ); ?>" value="<?php echo (int) $s['max_tokens']; ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ail-limit"><?php esc_html_e( 'AI replies per member per day', 'absel-ai-library' ); ?></label></th>
					<td><input id="ail-limit" type="number" min="0" name="<?php echo esc_attr( $field( 'daily_limit' ) ); ?>" value="<?php echo (int) $s['daily_limit']; ?>">
					<p class="description"><?php esc_html_e( '0 means no limit. Administrators are never limited.', 'absel-ai-library' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="ail-class-limit"><?php esc_html_e( 'AI replies per student link per day', 'absel-ai-library' ); ?></label></th>
					<td><input id="ail-class-limit" type="number" min="0" name="<?php echo esc_attr( $field( 'class_daily_limit' ) ); ?>" value="<?php echo (int) $s['class_daily_limit']; ?>">
					<p class="description"><?php esc_html_e( 'Shared by everyone using one instructor\'s link. A 40-student class doing a 25-turn negotiation uses about 1,000.', 'absel-ai-library' ); ?></p></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Usage', 'absel-ai-library' ); ?></h2>
		<table class="widefat striped" style="max-width:600px">
			<thead><tr><th><?php esc_html_e( 'Month (UTC)', 'absel-ai-library' ); ?></th><th><?php esc_html_e( 'Replies', 'absel-ai-library' ); ?></th><th><?php esc_html_e( 'Input tokens', 'absel-ai-library' ); ?></th><th><?php esc_html_e( 'Output tokens', 'absel-ai-library' ); ?></th></tr></thead>
			<tbody>
			<?php for ( $i = 0; $i < 6; $i++ ) :
				$month  = gmdate( 'Ym', strtotime( gmdate( 'Y-m-01' ) . " -$i months" ) );
				$totals = get_option( 'absel_ail_tokens_' . $month );
				if ( ! $totals ) {
					continue;
				}
				?>
				<tr><td><?php echo esc_html( substr( $month, 0, 4 ) . '-' . substr( $month, 4 ) ); ?></td><td><?php echo number_format_i18n( $totals['requests'] ); ?></td><td><?php echo number_format_i18n( $totals['input'] ); ?></td><td><?php echo number_format_i18n( $totals['output'] ); ?></td></tr>
			<?php endfor; ?>
			</tbody>
		</table>
	</div>
	<?php
}
