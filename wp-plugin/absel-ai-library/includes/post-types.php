<?php
/**
 * The "AI Activity" post type, its taxonomies and details box, and the
 * launch/download panel shown on each activity page.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'absel_ail_register_types' );

function absel_ail_register_types() {
	register_post_type( 'absel_activity', array(
		'labels'       => array(
			'name'          => __( 'AI Activities', 'absel-ai-library' ),
			'singular_name' => __( 'AI Activity', 'absel-ai-library' ),
			'add_new_item'  => __( 'Add New AI Activity', 'absel-ai-library' ),
			'edit_item'     => __( 'Edit AI Activity', 'absel-ai-library' ),
		),
		'public'       => true,
		'has_archive'  => true,
		'rewrite'      => array( 'slug' => 'ai-activities' ),
		'menu_icon'    => 'dashicons-welcome-learn-more',
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
	) );

	$taxonomies = array(
		'ai_topic'        => array( __( 'AI Topics', 'absel-ai-library' ), __( 'AI Topic', 'absel-ai-library' ), true ),
		'activity_format' => array( __( 'Formats', 'absel-ai-library' ), __( 'Format', 'absel-ai-library' ), false ),
		'discipline'      => array( __( 'Disciplines', 'absel-ai-library' ), __( 'Discipline', 'absel-ai-library' ), true ),
	);
	foreach ( $taxonomies as $slug => list( $plural, $singular, $hierarchical ) ) {
		register_taxonomy( $slug, 'absel_activity', array(
			'labels'            => array( 'name' => $plural, 'singular_name' => $singular ),
			'hierarchical'      => $hierarchical,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'ai-activities/' . str_replace( '_', '-', $slug ) ),
		) );
	}

	foreach ( array( '_absel_ail_folder', '_absel_ail_duration', '_absel_ail_group_size' ) as $key ) {
		register_post_meta( 'absel_activity', $key, array(
			'type'          => 'string',
			'single'        => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
}

add_action( 'add_meta_boxes_absel_activity', function () {
	add_meta_box( 'absel_ail_details', __( 'Activity details', 'absel-ai-library' ), 'absel_ail_details_box', 'absel_activity', 'side' );
} );

function absel_ail_details_box( $post ) {
	wp_nonce_field( 'absel_ail_details', 'absel_ail_details_nonce' );
	$fields = array(
		'_absel_ail_folder'     => array( __( 'Activity folder', 'absel-ai-library' ), 'negotiation-supplier' ),
		'_absel_ail_duration'   => array( __( 'Duration', 'absel-ai-library' ), '50–75 minutes' ),
		'_absel_ail_group_size' => array( __( 'Group size', 'absel-ai-library' ), 'Individuals or pairs' ),
	);
	foreach ( $fields as $key => list( $label, $placeholder ) ) {
		printf(
			'<p><label for="%1$s"><strong>%2$s</strong></label><br><input type="text" class="widefat" id="%1$s" name="%1$s" value="%3$s" placeholder="%4$s"></p>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( get_post_meta( $post->ID, $key, true ) ),
			esc_attr( $placeholder )
		);
	}
	$folder = get_post_meta( $post->ID, '_absel_ail_folder', true );
	if ( $folder && ! is_dir( trailingslashit( ABSEL_AIL_CONTENT_DIR ) . $folder ) ) {
		echo '<p style="color:#b32d2e">' . esc_html__( 'No folder with this name exists on the server yet.', 'absel-ai-library' ) . '</p>';
	}
	echo '<p class="description">' . esc_html__( 'The folder name under the protected library directory that holds this activity\'s files.', 'absel-ai-library' ) . '</p>';
}

add_action( 'save_post_absel_activity', function ( $post_id ) {
	if ( ! isset( $_POST['absel_ail_details_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['absel_ail_details_nonce'] ) ), 'absel_ail_details' )
		|| ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['_absel_ail_folder'] ) ) {
		update_post_meta( $post_id, '_absel_ail_folder', absel_ail_sanitize_folder( wp_unslash( $_POST['_absel_ail_folder'] ) ) );
	}
	foreach ( array( '_absel_ail_duration', '_absel_ail_group_size' ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
} );

function absel_ail_sanitize_folder( $folder ) {
	return preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $folder ) );
}

// Members see a launch button and the activity's downloads; everyone else sees
// how to get access. Simple Membership's own protection box can additionally
// hide the page body from non-members.
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'absel_activity' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	return $content . absel_ail_activity_panel( get_the_ID() );
}, 20 );

// The panel differs per visitor (and Simple Membership members are often not
// WordPress users), so keep page caches from serving one member's view to others.
add_action( 'template_redirect', function () {
	if ( is_singular( 'absel_activity' ) ) {
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}
} );

function absel_ail_activity_panel( $post_id ) {
	$folder   = get_post_meta( $post_id, '_absel_ail_folder', true );
	$duration = get_post_meta( $post_id, '_absel_ail_duration', true );
	$group    = get_post_meta( $post_id, '_absel_ail_group_size', true );

	$html = '<div class="absel-ail-panel" style="border:1px solid #ccd0d4;border-radius:6px;padding:1em 1.25em;margin:1.5em 0">';
	if ( $duration || $group ) {
		$html .= '<p>';
		$html .= $duration ? '<strong>' . esc_html__( 'Duration:', 'absel-ai-library' ) . '</strong> ' . esc_html( $duration ) . ' ' : '';
		$html .= $group ? '<strong>' . esc_html__( 'Group size:', 'absel-ai-library' ) . '</strong> ' . esc_html( $group ) : '';
		$html .= '</p>';
	}

	if ( ! absel_ail_member_has_access() ) {
		$html .= '<p>' . sprintf(
			/* translators: 1: login URL, 2: join URL */
			wp_kses_post( __( 'This activity is available to ABSEL members. <a href="%1$s">Log in</a> or <a href="%2$s">become a member</a>.', 'absel-ai-library' ) ),
			esc_url( absel_ail_login_url() ),
			esc_url( absel_ail_join_url() )
		) . '</p></div>';
		return $html;
	}

	$dir = $folder ? trailingslashit( ABSEL_AIL_CONTENT_DIR ) . $folder : '';
	if ( ! $dir || ! is_dir( $dir ) ) {
		return $html . '</div>';
	}
	if ( is_file( $dir . '/index.html' ) ) {
		$html .= sprintf(
			'<p><a class="button wp-element-button" href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_url( absel_ail_file_url( $folder . '/index.html' ) ),
			esc_html__( 'Launch simulation', 'absel-ai-library' )
		);
		$student_link = add_query_arg( 'class', absel_ail_class_pass_create( $folder ), absel_ail_file_url( $folder . '/index.html' ) );
		$html        .= sprintf(
			'<p><label><strong>%1$s</strong><br><input type="text" readonly value="%2$s" onclick="this.select()" style="width:100%%;font-family:monospace;font-size:.85em"></label><br><small>%3$s</small></p>',
			esc_html__( 'Link for your students', 'absel-ai-library' ),
			esc_attr( $student_link ),
			esc_html( sprintf(
				/* translators: %d: number of days */
				__( 'Opens the simulation only (not these materials) for %d days. Post it in your LMS; students do not need an account.', 'absel-ai-library' ),
				ABSEL_AIL_CLASS_DAYS
			) )
		);
	}
	$downloads = array();
	foreach ( (array) scandir( $dir ) as $name ) {
		if ( preg_match( '/^[^._].*\.(pdf|docx|pptx|xlsx|csv|zip)$/i', $name ) && is_file( $dir . '/' . $name ) ) {
			$downloads[] = sprintf( '<li><a href="%s">%s</a></li>', esc_url( absel_ail_file_url( $folder . '/' . $name ) ), esc_html( $name ) );
		}
	}
	if ( $downloads ) {
		$html .= '<p><strong>' . esc_html__( 'Materials', 'absel-ai-library' ) . '</strong></p><ul>' . implode( '', $downloads ) . '</ul>';
	}
	return $html . '</div>';
}
