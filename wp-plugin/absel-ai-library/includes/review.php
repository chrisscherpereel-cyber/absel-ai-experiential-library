<?php
/**
 * The editors' review queue: AI Activities > Submissions.
 *
 * Editors (anyone who can edit others' posts) see each submission's details,
 * download its files, and accept, return or decline it. Accepting copies the
 * files into the library and creates a draft AI Activity, or updates the
 * existing activity when the submission revises an accepted exercise.
 */

defined( 'ABSPATH' ) || exit;

/* ---------- the list ---------- */

add_filter( 'manage_absel_submission_posts_columns', function () {
	return array(
		'cb'           => '<input type="checkbox">',
		'title'        => __( 'Exercise', 'absel-ai-library' ),
		'absel_by'     => __( 'Submitted by', 'absel-ai-library' ),
		'absel_state'  => __( 'Status', 'absel-ai-library' ),
		'absel_fields' => __( 'Disciplines', 'absel-ai-library' ),
		'absel_date'   => __( 'Submitted', 'absel-ai-library' ),
	);
} );

add_action( 'manage_absel_submission_posts_custom_column', function ( $column, $id ) {
	switch ( $column ) {
		case 'absel_by':
			echo esc_html( get_post_meta( $id, '_absel_sub_name', true ) );
			break;
		case 'absel_state':
			$states = absel_ail_submission_states();
			$state  = absel_ail_submission_state( $id );
			echo esc_html( isset( $states[ $state ] ) ? $states[ $state ] : $state );
			if ( get_post_meta( $id, '_absel_sub_replaces', true ) ) {
				echo '<br><small>' . esc_html__( 'Revision', 'absel-ai-library' ) . '</small>';
			}
			break;
		case 'absel_fields':
			$manifest = absel_ail_manifest_view( absel_ail_submission_manifest( $id ) );
			echo esc_html( $manifest ? implode( ', ', array_map( 'absel_ail_label', $manifest->disciplines ) ) : '' );
			break;
		case 'absel_date':
			$time = (int) get_post_meta( $id, '_absel_sub_submitted', true );
			echo $time ? esc_html( wp_date( get_option( 'date_format' ), $time ) ) : '—';
			break;
	}
}, 10, 2 );

// Status filter links above the list, and "waiting" first by default.
add_filter( 'views_edit-absel_submission', function () {
	$current = isset( $_GET['absel_state'] ) ? sanitize_key( $_GET['absel_state'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$views   = array();
	$base    = admin_url( 'edit.php?post_type=absel_submission' );
	$views['all'] = sprintf( '<a href="%s"%s>%s</a>', esc_url( $base ), '' === $current ? ' class="current"' : '', esc_html__( 'All', 'absel-ai-library' ) );
	foreach ( absel_ail_submission_states() as $state => $label ) {
		if ( 'uploading' === $state ) {
			continue;
		}
		$count = absel_ail_count_submissions( $state );
		$views[ $state ] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'absel_state', $state, $base ) ),
			$state === $current ? ' class="current"' : '',
			esc_html( $label ),
			$count
		);
	}
	return $views;
} );

add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'absel_submission' !== $query->get( 'post_type' ) ) {
		return;
	}
	$state = isset( $_GET['absel_state'] ) ? sanitize_key( $_GET['absel_state'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$query->set( 'meta_query', array(
		$state
			? array( 'key' => '_absel_sub_state', 'value' => $state )
			: array( 'key' => '_absel_sub_state', 'value' => 'uploading', 'compare' => '!=' ),
	) );
} );

function absel_ail_count_submissions( $state ) {
	return count( get_posts( array(
		'post_type'      => 'absel_submission',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_absel_sub_state', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => $state, // phpcs:ignore WordPress.DB.SlowDBQuery
	) ) );
}

// A count bubble on the Submissions menu item while submissions are waiting.
add_action( 'admin_menu', function () {
	global $submenu;
	$parent = 'edit.php?post_type=absel_activity';
	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}
	$waiting = absel_ail_count_submissions( 'submitted' );
	foreach ( $submenu[ $parent ] as $i => $item ) {
		if ( 'edit.php?post_type=absel_submission' === $item[2] && $waiting ) {
			$submenu[ $parent ][ $i ][0] .= ' <span class="awaiting-mod">' . (int) $waiting . '</span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}
}, 99 );

/* ---------- the review screen ---------- */

add_action( 'add_meta_boxes_absel_submission', function ( $post ) {
	remove_meta_box( 'submitdiv', 'absel_submission', 'side' );
	add_meta_box( 'absel_sub_checks', __( 'Things to check', 'absel-ai-library' ), 'absel_ail_box_checks', 'absel_submission', 'normal', 'high' );
	add_meta_box( 'absel_sub_details', __( 'Details', 'absel-ai-library' ), 'absel_ail_box_details', 'absel_submission', 'normal' );
	add_meta_box( 'absel_sub_files', __( 'Files', 'absel-ai-library' ), 'absel_ail_box_files', 'absel_submission', 'normal' );
	add_meta_box( 'absel_sub_decision', __( 'Decision', 'absel-ai-library' ), 'absel_ail_box_decision', 'absel_submission', 'side', 'high' );
} );

/**
 * A copy of a manifest with every field present (empty when not given), for display.
 * Submissions can contain anything from a title alone to a full package.
 */
function absel_ail_manifest_view( $manifest ) {
	if ( ! $manifest ) {
		return null;
	}
	$m        = json_decode( wp_json_encode( $manifest ) );
	$defaults = array(
		'slug' => '', 'stage' => '', 'summary' => '', 'description' => null, 'version' => '', 'version_notes' => null,
		'authors' => array(), 'exercise_types' => array(), 'disciplines' => array(), 'courses' => array(), 'topics' => array(),
		'learning_objectives' => array(), 'skills' => array(), 'levels' => array(), 'delivery_modes' => array(),
		'technology' => array(), 'materials' => array(), 'prior_publications' => array(), 'assumptions' => array(),
		'class_size' => null, 'group_size' => null, 'prep_time_minutes' => null, 'technology_notes' => null,
		'cost_to_students' => null, 'ai_involvement' => null, 'classroom_testing' => null,
		'evidence_of_effectiveness' => null, 'license' => '', 'generated_with' => null,
	);
	foreach ( $defaults as $key => $value ) {
		if ( ! property_exists( $m, $key ) || ( null === $m->$key && is_array( $value ) ) ) {
			$m->$key = $value;
		}
	}
	if ( '' === $m->slug ) {
		$m->slug = trim( substr( preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $m->title ) ) ), 0, 60 ), '-' );
	}
	$m->duration = isset( $m->duration ) && is_object( $m->duration ) ? $m->duration : new stdClass();
	foreach ( array( 'in_class_minutes', 'sessions', 'out_of_class_hours' ) as $key ) {
		if ( ! property_exists( $m->duration, $key ) ) {
			$m->duration->$key = null;
		}
	}
	$m->rights = isset( $m->rights ) && is_object( $m->rights ) ? $m->rights : new stdClass();
	if ( ! isset( $m->rights->third_party_materials ) ) {
		$m->rights->third_party_materials = array();
	}
	$m->rights->contains_identifiable_student_information = ! empty( $m->rights->contains_identifiable_student_information );
	return $m;
}

function absel_ail_label( $value ) {
	$labels = array(
		'graduate'           => "Graduate (master's, MBA)",
		'online-synchronous' => 'Online, live',
		'online-asynchronous' => 'Online, self-paced',
		'lms'                => 'Course LMS',
		'ai-literacy'        => 'AI literacy',
		'student-devices'    => 'Student laptops or phones',
		'role-play'          => 'Role-play',
		'absel-member-use'   => 'ABSEL members only',
		'cc-by-nc-4.0'       => 'CC BY-NC 4.0',
		'cc-by-nc-sa-4.0'    => 'CC BY-NC-SA 4.0',
		'cc-by-4.0'          => 'CC BY 4.0',
		'under-25'           => 'Under $25',
		'25-to-75'           => '$25 to $75',
		'over-75'            => 'Over $75',
	);
	return isset( $labels[ $value ] ) ? $labels[ $value ] : ucfirst( str_replace( '-', ' ', (string) $value ) );
}

function absel_ail_range_text( $range, $unit ) {
	if ( ! $range ) {
		return '';
	}
	if ( null === $range->max ) {
		return sprintf( '%d+ %s', $range->min, $unit );
	}
	return $range->min === $range->max ? sprintf( '%d %s', $range->min, $unit ) : sprintf( '%d–%d %s', $range->min, $range->max, $unit );
}

function absel_ail_box_checks( $post ) {
	$manifest = absel_ail_manifest_view( absel_ail_submission_manifest( $post->ID ) );
	if ( ! $manifest ) {
		echo '<p>' . esc_html__( 'This submission has no details.', 'absel-ai-library' ) . '</p>';
		return;
	}
	$checks = array();
	$state  = absel_ail_submission_state( $post->ID );
	if ( 'uploading' === $state ) {
		$checks[] = array( 'warn', __( 'The author has not finished sending this submission. Unfinished submissions are deleted after two days.', 'absel-ai-library' ) );
	}
	$stage_text = array(
		'idea'             => __( 'Stage: an idea. Judge it as a concept; no runnable materials are expected.', 'absel-ai-library' ),
		'in-development'   => __( "Stage: in development. Some materials exist but it isn't complete.", 'absel-ai-library' ),
		'ready-to-use'     => __( 'Stage: ready to use. Check that another instructor could run it from these files.', 'absel-ai-library' ),
		'classroom-tested' => __( 'Stage: classroom-tested. Check that another instructor could run it from these files.', 'absel-ai-library' ),
	);
	$checks[] = array( 'info', esc_html( isset( $stage_text[ $manifest->stage ] ) ? $stage_text[ $manifest->stage ] : __( 'Stage: not stated.', 'absel-ai-library' ) ) );
	if ( $manifest->assumptions ) {
		/* translators: %d: number of assumptions */
		$checks[] = array( 'info', sprintf( esc_html__( 'The drafting assistant made %d guesses, which the author checked or corrected. They are listed under Details.', 'absel-ai-library' ), count( $manifest->assumptions ) ) );
	}
	if ( $manifest->rights->contains_identifiable_student_information ) {
		$checks[] = array( 'warn', esc_html__( 'The materials may contain student names, grades or identifiable work. Have it removed before publication.', 'absel-ai-library' ) );
	}
	$missing = array_diff( absel_ail_manifest_files( $manifest ), array_keys( absel_ail_submission_files( $post->ID ) ) );
	if ( $missing ) {
		/* translators: %s: file names */
		$checks[] = array( 'warn', sprintf( esc_html__( 'Listed but not received: %s. Ask the author for them if needed.', 'absel-ai-library' ), esc_html( implode( ', ', $missing ) ) ) );
	}
	if ( ! $manifest->materials ) {
		$checks[] = array( 'info', esc_html__( 'No files or links: this submission is a description only.', 'absel-ai-library' ) );
	}
	$replaces = (int) get_post_meta( $post->ID, '_absel_sub_replaces', true );
	if ( $replaces ) {
		$states   = absel_ail_submission_states();
		$checks[] = array( 'info', sprintf(
			/* translators: 1: link to earlier submission, 2: its status */
			__( 'This revises %1$s (%2$s). Files the author did not replace were carried over from it.', 'absel-ai-library' ),
			'<a href="' . esc_url( get_edit_post_link( $replaces ) ) . '">#' . $replaces . '</a>',
			esc_html( isset( $states[ absel_ail_submission_state( $replaces ) ] ) ? $states[ absel_ail_submission_state( $replaces ) ] : '' )
		) );
	}
	foreach ( $manifest->rights->third_party_materials as $item ) {
		if ( 'needs-review' === $item->status ) {
			/* translators: %s: description of third-party material */
			$checks[] = array( 'warn', sprintf( esc_html__( 'Permission not clear for third-party material: %s', 'absel-ai-library' ), esc_html( $item->description ) ) );
		}
	}
	$web = preg_grep( '/\.(html?|js|svg)$/i', absel_ail_manifest_files( $manifest ) );
	if ( $web ) {
		$checks[] = array( 'warn', sprintf(
			/* translators: %s: file names */
			esc_html__( 'Contains web files (%s). Once accepted they run on absel.org for every member who opens them, so download and review the code first.', 'absel-ai-library' ),
			esc_html( implode( ', ', $web ) )
		) );
	}
	$folder   = absel_ail_sanitize_folder( $manifest->slug );
	$activity = absel_ail_previous_activity( $post->ID );
	if ( ! $activity && is_dir( ABSEL_AIL_CONTENT_DIR . '/' . $folder ) ) {
		/* translators: %s: folder name */
		$checks[] = array( 'warn', sprintf( esc_html__( 'The library already has a folder named "%s". Choose a different folder name when you accept.', 'absel-ai-library' ), esc_html( $folder ) ) );
	}
	if ( $manifest->classroom_testing && isset( $manifest->classroom_testing->times_used ) && 0 === $manifest->classroom_testing->times_used ) {
		$checks[] = array( 'info', esc_html__( 'Not yet used with students.', 'absel-ai-library' ) );
	}
	if ( ! empty( $manifest->generated_with ) ) {
		/* translators: %s: AI tool name */
		$checks[] = array( 'info', sprintf( esc_html__( 'Details drafted with %s and confirmed by the author.', 'absel-ai-library' ), esc_html( $manifest->generated_with ) ) );
	}
	$words = str_word_count( (string) $manifest->summary );
	if ( ! $words ) {
		$checks[] = array( 'info', esc_html__( 'No summary. Write one before publishing.', 'absel-ai-library' ) );
	} elseif ( $words < 60 || $words > 180 ) {
		/* translators: %d: word count */
		$checks[] = array( 'info', sprintf( esc_html__( 'The summary is %d words (the guideline is 60 to 180).', 'absel-ai-library' ), $words ) );
	}
	$checks[] = array( 'info', esc_html__( 'Also check: nothing confidential in the summary or topics, answer keys only in instructor-only files, and no student information in any file.', 'absel-ai-library' ) );

	echo '<ul style="margin:0">';
	foreach ( $checks as list( $kind, $html ) ) {
		printf( '<li style="margin:.4em 0">%s %s</li>', 'warn' === $kind ? '⚠️' : 'ℹ️', wp_kses_post( $html ) );
	}
	echo '</ul>';
}

function absel_ail_box_details( $post ) {
	$m = absel_ail_manifest_view( absel_ail_submission_manifest( $post->ID ) );
	if ( ! $m ) {
		return;
	}
	$labels = function ( $values ) {
		return esc_html( implode( ', ', array_map( 'absel_ail_label', (array) $values ) ) );
	};
	$text = function ( $value ) {
		return null === $value || '' === $value ? '<span style="color:#787c82">—</span>' : nl2br( esc_html( $value ) );
	};
	$authors = array();
	foreach ( $m->authors as $a ) {
		$authors[] = esc_html( $a->name )
			. ( ! empty( $a->affiliation ) ? ', ' . esc_html( $a->affiliation ) : '' )
			. ( ! empty( $a->email ) ? ' &lt;' . esc_html( $a->email ) . '&gt;' : '' )
			. ( ! empty( $a->corresponding ) ? ' <em>(' . esc_html__( 'contact', 'absel-ai-library' ) . ')</em>' : '' );
	}
	$objectives = '<ul style="margin:0;list-style:disc;padding-left:1.2em">';
	foreach ( $m->learning_objectives as $objective ) {
		$objectives .= '<li>' . esc_html( $objective ) . '</li>';
	}
	$objectives .= '</ul>';
	$third = array();
	foreach ( $m->rights->third_party_materials as $item ) {
		$third[] = esc_html( $item->description . ' (' . absel_ail_label( $item->status ) . ')' );
	}
	$publications = array();
	foreach ( isset( $m->prior_publications ) ? $m->prior_publications : array() as $p ) {
		$publications[] = esc_html( $p->citation ) . ( ! empty( $p->doi ) ? ' doi:' . esc_html( $p->doi ) : '' );
	}
	$ai      = $m->ai_involvement;
	$guesses = array();
	foreach ( $m->assumptions as $a ) {
		$guesses[] = '<strong>' . esc_html( $a->field ) . '</strong>: ' . esc_html( $a->basis )
			. ( empty( $a->confirmed ) ? '' : ' <span style="color:#00a32a">✓ ' . esc_html__( 'checked by the author', 'absel-ai-library' ) . '</span>' );
	}
	$time = absel_ail_range_text( $m->duration->in_class_minutes, 'minutes' );
	if ( $m->duration->sessions ) {
		/* translators: %d: number of class sessions */
		$time .= ( $time ? ', ' : '' ) . sprintf( _n( '%d session', '%d sessions', $m->duration->sessions, 'absel-ai-library' ), $m->duration->sessions );
	}

	$rows = array(
		__( 'Submitted by', 'absel-ai-library' )      => esc_html( get_post_meta( $post->ID, '_absel_sub_name', true ) . ' <' . get_post_meta( $post->ID, '_absel_sub_email', true ) . '>' ),
		__( 'Stage', 'absel-ai-library' )             => $labels( $m->stage ? array( $m->stage ) : array() ),
		__( 'Summary', 'absel-ai-library' )           => $text( $m->summary ),
		__( 'Description', 'absel-ai-library' )       => $text( isset( $m->description ) ? $m->description : null ),
		__( 'Authors', 'absel-ai-library' )           => implode( '<br>', $authors ),
		__( 'Type', 'absel-ai-library' )              => $labels( $m->exercise_types ),
		__( 'Disciplines', 'absel-ai-library' )       => $labels( $m->disciplines ),
		__( 'Topics', 'absel-ai-library' )            => esc_html( implode( ', ', $m->topics ) ),
		__( 'Learning objectives', 'absel-ai-library' ) => $objectives,
		__( 'Courses', 'absel-ai-library' )           => esc_html( implode( ', ', $m->courses ) ),
		__( 'Skills', 'absel-ai-library' )            => $labels( $m->skills ),
		__( 'Levels', 'absel-ai-library' )            => $labels( $m->levels ),
		__( 'Delivery', 'absel-ai-library' )          => $labels( $m->delivery_modes ),
		__( 'Class time', 'absel-ai-library' )        => esc_html( $time ),
		__( 'Class size', 'absel-ai-library' )        => esc_html( absel_ail_range_text( $m->class_size, 'students' ) ),
		__( 'Team size', 'absel-ai-library' )         => esc_html( absel_ail_range_text( $m->group_size, 'per team' ) ),
		__( 'Instructor prep', 'absel-ai-library' )   => $text( null !== $m->prep_time_minutes ? $m->prep_time_minutes . ' minutes' : null ),
		__( 'Technology', 'absel-ai-library' )        => $labels( $m->technology ) . ( ! empty( $m->technology_notes ) ? '<br>' . esc_html( $m->technology_notes ) : '' ),
		__( 'Cost to students', 'absel-ai-library' )  => $labels( $m->cost_to_students ? array( $m->cost_to_students ) : array() ),
		__( 'AI', 'absel-ai-library' )                => $ai ? esc_html( ( ! empty( $ai->topic_is_ai ) ? __( 'Teaches about AI. ', 'absel-ai-library' ) : '' ) . ( ! empty( $ai->students_use_ai ) ? sprintf( /* translators: %s: none, optional or required */ __( 'Students use AI: %s.', 'absel-ai-library' ), $ai->students_use_ai ) : '' ) . ( ! empty( $ai->notes ) ? ' ' . $ai->notes : '' ) ) : '',
		__( 'Times used', 'absel-ai-library' )        => $text( $m->classroom_testing ? trim( ( isset( $m->classroom_testing->times_used ) ? $m->classroom_testing->times_used : '' ) . ( ! empty( $m->classroom_testing->contexts ) ? ': ' . $m->classroom_testing->contexts : '' ), ': ' ) : null ),
		__( 'Evidence', 'absel-ai-library' )          => $text( $m->evidence_of_effectiveness ),
		__( 'Publications', 'absel-ai-library' )      => implode( '<br>', $publications ),
		__( 'Third-party material', 'absel-ai-library' ) => $third ? implode( '<br>', $third ) : esc_html__( 'None declared', 'absel-ai-library' ),
		__( 'License', 'absel-ai-library' )           => $m->license ? $labels( array( $m->license ) ) : esc_html__( 'Not chosen (ABSEL members only applies)', 'absel-ai-library' ),
		__( 'Version', 'absel-ai-library' )           => esc_html( $m->version . ( ! empty( $m->version_notes ) ? ': ' . $m->version_notes : '' ) ),
		__( "Assistant's guesses", 'absel-ai-library' ) => $guesses ? implode( '<br>', $guesses ) : esc_html__( 'None', 'absel-ai-library' ),
	);
	echo '<table class="widefat striped" style="border:0"><tbody>';
	foreach ( $rows as $label => $html ) {
		printf( '<tr><th style="width:11em">%s</th><td>%s</td></tr>', esc_html( $label ), wp_kses_post( $html ) );
	}
	echo '</tbody></table>';
	printf(
		'<p><a class="button" href="%s">%s</a></p>',
		esc_url( absel_ail_submission_file_url( $post->ID, 'manifest.json' ) ),
		esc_html__( 'Download manifest.json', 'absel-ai-library' )
	);
}

function absel_ail_box_files( $post ) {
	$manifest = absel_ail_manifest_view( absel_ail_submission_manifest( $post->ID ) );
	$stored   = absel_ail_submission_files( $post->ID );
	if ( ! $manifest || ! $manifest->materials ) {
		echo '<p>' . esc_html__( 'No files or links were submitted.', 'absel-ai-library' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'File', 'absel-ai-library' ) . '</th><th>' . esc_html__( 'Type', 'absel-ai-library' ) . '</th><th>' . esc_html__( 'Who may see it', 'absel-ai-library' ) . '</th><th>' . esc_html__( 'Size', 'absel-ai-library' ) . '</th></tr></thead><tbody>';
	foreach ( $manifest->materials as $item ) {
		if ( isset( $item->url ) ) {
			$name = sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a> <small>(%s)</small>', esc_url( $item->url ), esc_html( $item->title ), esc_html__( 'link', 'absel-ai-library' ) );
			$size = '';
		} elseif ( isset( $stored[ $item->file ] ) ) {
			$name = sprintf( '<a href="%s">%s</a><br><small>%s</small>', esc_url( absel_ail_submission_file_url( $post->ID, $item->file ) ), esc_html( $item->file ), esc_html( $item->title ) );
			$size = size_format( $stored[ $item->file ] );
		} else {
			$name = esc_html( $item->file ) . ' <strong style="color:#b32d2e">' . esc_html__( 'not received', 'absel-ai-library' ) . '</strong>';
			$size = '';
		}
		printf(
			'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
			wp_kses_post( $name ),
			esc_html( isset( $item->role ) ? absel_ail_label( $item->role ) : '—' ),
			esc_html( ! isset( $item->audience ) ? '—' : ( 'instructor-only' === $item->audience ? __( 'Instructor only', 'absel-ai-library' ) : __( 'Students', 'absel-ai-library' ) ) ),
			esc_html( $size )
		);
	}
	echo '</tbody></table><p class="description">' . esc_html__( 'Files download to your computer; they never open in the browser here.', 'absel-ai-library' ) . '</p>';
}

function absel_ail_box_decision( $post ) {
	$state    = absel_ail_submission_state( $post->ID );
	$states   = absel_ail_submission_states();
	$manifest = absel_ail_manifest_view( absel_ail_submission_manifest( $post->ID ) );
	$activity = absel_ail_previous_activity( $post->ID );
	$folder   = $activity ? get_post_meta( $activity, '_absel_ail_folder', true ) : ( $manifest ? absel_ail_sanitize_folder( $manifest->slug ) : '' );
	wp_nonce_field( 'absel_ail_decision', 'absel_ail_decision_nonce' );

	echo '<p><strong>' . esc_html__( 'Status:', 'absel-ai-library' ) . '</strong> ' . esc_html( isset( $states[ $state ] ) ? $states[ $state ] : $state ) . '</p>';
	$own_activity = (int) get_post_meta( $post->ID, '_absel_sub_activity', true );
	if ( $own_activity ) {
		printf( '<p><a href="%s">%s</a></p>', esc_url( get_edit_post_link( $own_activity ) ), esc_html__( 'Open the activity', 'absel-ai-library' ) );
	}
	$note = get_post_meta( $post->ID, '_absel_sub_message', true );
	if ( $note ) {
		echo '<p><strong>' . esc_html__( 'Message sent to the author:', 'absel-ai-library' ) . '</strong><br>' . nl2br( esc_html( $note ) ) . '</p>';
	}
	if ( 'submitted' !== $state ) {
		return;
	}
	?>
	<p>
		<label for="absel-folder"><strong><?php esc_html_e( 'Library folder', 'absel-ai-library' ); ?></strong></label>
		<input type="text" class="widefat" id="absel-folder" name="absel_ail_folder" value="<?php echo esc_attr( $folder ); ?>" <?php disabled( (bool) $activity ); ?>>
		<span class="description"><?php echo $activity ? esc_html__( 'This revises a published exercise; its files will be replaced.', 'absel-ai-library' ) : esc_html__( 'Lowercase letters, numbers and hyphens. Becomes part of the file addresses.', 'absel-ai-library' ); ?></span>
	</p>
	<p>
		<label for="absel-message"><strong><?php esc_html_e( 'Message to the author', 'absel-ai-library' ); ?></strong></label>
		<textarea class="widefat" rows="5" id="absel-message" name="absel_ail_message" placeholder="<?php esc_attr_e( 'Required when returning or declining.', 'absel-ai-library' ); ?>"></textarea>
	</p>
	<p style="display:flex;flex-wrap:wrap;gap:6px">
		<button type="submit" class="button button-primary" name="absel_ail_decision" value="accept"><?php echo $activity ? esc_html__( 'Accept and update exercise', 'absel-ai-library' ) : esc_html__( 'Accept', 'absel-ai-library' ); ?></button>
		<button type="submit" class="button" name="absel_ail_decision" value="return"><?php esc_html_e( 'Return for changes', 'absel-ai-library' ); ?></button>
		<button type="submit" class="button button-link-delete" name="absel_ail_decision" value="decline"><?php esc_html_e( 'Decline', 'absel-ai-library' ); ?></button>
	</p>
	<p class="description"><?php echo $activity ? esc_html__( 'Accepting replaces the exercise\'s files and details. The activity page text is not changed.', 'absel-ai-library' ) : esc_html__( 'Accepting copies the files into the library and creates a draft activity for you to check and publish.', 'absel-ai-library' ); ?></p>
	<?php
}

/* ---------- decisions ---------- */

add_action( 'save_post_absel_submission', function ( $post_id ) {
	if ( ! isset( $_POST['absel_ail_decision'], $_POST['absel_ail_decision_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['absel_ail_decision_nonce'] ) ), 'absel_ail_decision' )
		|| ! current_user_can( 'edit_post', $post_id )
		|| 'submitted' !== absel_ail_submission_state( $post_id ) ) {
		return;
	}
	$decision = sanitize_key( $_POST['absel_ail_decision'] );
	$message  = isset( $_POST['absel_ail_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['absel_ail_message'] ) ) : '';
	if ( 'accept' === $decision ) {
		$folder = isset( $_POST['absel_ail_folder'] ) ? sanitize_text_field( wp_unslash( $_POST['absel_ail_folder'] ) ) : '';
		$result = absel_ail_accept_submission( $post_id, $folder, $message );
		absel_ail_flash( is_wp_error( $result ) ? $result->get_error_message() : __( 'Accepted. The files are in the library; check the activity and publish it when ready.', 'absel-ai-library' ), is_wp_error( $result ) ? 'error' : 'success' );
		return;
	}
	if ( 'return' === $decision || 'decline' === $decision ) {
		if ( '' === $message ) {
			absel_ail_flash( __( 'Write a message to the author first.', 'absel-ai-library' ), 'error' );
			return;
		}
		update_post_meta( $post_id, '_absel_sub_state', 'return' === $decision ? 'returned' : 'declined' );
		update_post_meta( $post_id, '_absel_sub_message', $message );
		absel_ail_notify_decision( $post_id );
		absel_ail_flash( 'return' === $decision ? __( 'Returned to the author.', 'absel-ai-library' ) : __( 'Declined.', 'absel-ai-library' ), 'success' );
	}
} );

/**
 * The activity created from an earlier accepted version of this submission, or 0.
 */
function absel_ail_previous_activity( $id ) {
	$seen = array();
	for ( $current = (int) get_post_meta( $id, '_absel_sub_replaces', true ); $current && ! isset( $seen[ $current ] ); $current = (int) get_post_meta( $current, '_absel_sub_replaces', true ) ) {
		$seen[ $current ] = true;
		$activity         = (int) get_post_meta( $current, '_absel_sub_activity', true );
		if ( $activity && get_post( $activity ) ) {
			return $activity;
		}
	}
	return 0;
}

/**
 * Copies the files into the library and creates (or updates) the activity.
 *
 * @return int|WP_Error Activity post ID.
 */
function absel_ail_accept_submission( $id, $folder, $message = '' ) {
	$manifest = absel_ail_submission_manifest( $id );
	$view     = absel_ail_manifest_view( $manifest );
	$activity = absel_ail_previous_activity( $id );
	if ( $activity ) {
		$folder = get_post_meta( $activity, '_absel_ail_folder', true );
	}
	$folder = absel_ail_sanitize_folder( $folder );
	if ( ! $manifest || '' === $folder ) {
		return new WP_Error( 'absel_ail_folder', __( 'Enter a library folder name.', 'absel-ai-library' ) );
	}
	$target = ABSEL_AIL_CONTENT_DIR . '/' . $folder;
	if ( ! $activity && file_exists( $target ) ) {
		return new WP_Error( 'absel_ail_folder', __( 'A library folder with that name already exists. Choose another name.', 'absel-ai-library' ) );
	}
	if ( ! wp_mkdir_p( $target ) ) {
		return new WP_Error( 'absel_ail_folder', __( 'The library folder could not be created.', 'absel-ai-library' ) );
	}

	// Replace the folder's files (names starting with "_" or "." are left alone).
	foreach ( scandir( $target ) as $name ) {
		if ( '.' !== $name[0] && '_' !== $name[0] && is_file( $target . '/' . $name ) ) {
			unlink( $target . '/' . $name );
		}
	}
	// Files listed but never received are left out; the manifest still lists them.
	$source = absel_ail_submission_dir( $id );
	foreach ( array_keys( absel_ail_submission_files( $id ) ) as $name ) {
		if ( ! copy( $source . '/' . $name, $target . '/' . $name ) ) {
			/* translators: %s: file name */
			return new WP_Error( 'absel_ail_copy', sprintf( __( 'Could not copy %s into the library.', 'absel-ai-library' ), $name ) );
		}
	}
	$manifest->slug = $folder;
	$json           = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	file_put_contents( $target . '/manifest.json', $json . "\n" );

	$duration = absel_ail_range_text( $view->duration->in_class_minutes, 'minutes' )
		. ( $view->duration->sessions > 1 ? sprintf( ' over %d sessions', $view->duration->sessions ) : '' );
	$group    = '';
	if ( $view->group_size ) {
		$group = 1 === $view->group_size->max ? 'Individual' : absel_ail_range_text( $view->group_size, 'per team' );
	}

	if ( ! $activity ) {
		$content = $view->summary ? '<!-- wp:paragraph --><p>' . esc_html( $view->summary ) . '</p><!-- /wp:paragraph -->' : '';
		if ( $view->learning_objectives ) {
			$content .= '<!-- wp:heading {"level":3} --><h3>' . esc_html__( 'Learning objectives', 'absel-ai-library' ) . '</h3><!-- /wp:heading -->'
				. '<!-- wp:list --><ul>';
			foreach ( $view->learning_objectives as $objective ) {
				$content .= '<!-- wp:list-item --><li>' . esc_html( $objective ) . '</li><!-- /wp:list-item -->';
			}
			$content .= '</ul><!-- /wp:list -->';
		}
		$activity = wp_insert_post( array(
			'post_type'    => 'absel_activity',
			'post_status'  => 'draft',
			'post_title'   => $manifest->title,
			'post_excerpt' => (string) $view->summary,
			'post_content' => $content,
		), true );
		if ( is_wp_error( $activity ) ) {
			return $activity;
		}
		wp_set_object_terms( $activity, array_map( 'absel_ail_label', $view->disciplines ), 'discipline' );
		wp_set_object_terms( $activity, array_map( 'absel_ail_label', $view->delivery_modes ), 'activity_format' );
	}
	update_post_meta( $activity, '_absel_ail_folder', $folder );
	update_post_meta( $activity, '_absel_ail_duration', $duration );
	update_post_meta( $activity, '_absel_ail_group_size', $group );
	update_post_meta( $activity, '_absel_ail_manifest', wp_slash( $json ) );

	update_post_meta( $id, '_absel_sub_state', 'accepted' );
	update_post_meta( $id, '_absel_sub_activity', $activity );
	update_post_meta( $id, '_absel_sub_message', $message );
	absel_ail_notify_decision( $id );
	return $activity;
}

function absel_ail_notify_decision( $id ) {
	$email = get_post_meta( $id, '_absel_sub_email', true );
	if ( ! $email ) {
		return;
	}
	$state   = absel_ail_submission_state( $id );
	$title   = get_post_field( 'post_title', $id );
	$message = (string) get_post_meta( $id, '_absel_sub_message', true );
	$intro   = array(
		/* translators: %s: exercise title */
		'accepted' => __( 'Good news: "%s" has been accepted for the ABSEL Exercise Library. The editors will let you know when it is published.', 'absel-ai-library' ),
		/* translators: %s: exercise title */
		'returned' => __( 'The editors have reviewed "%s" and are asking for some changes before it can be accepted. You can revise and resubmit it from the submission page; files you don\'t change are kept.', 'absel-ai-library' ),
		/* translators: %s: exercise title */
		'declined' => __( 'The editors have reviewed "%s" and are unable to accept it for the library.', 'absel-ai-library' ),
	);
	if ( ! isset( $intro[ $state ] ) ) {
		return;
	}
	$body = sprintf( $intro[ $state ], $title )
		. ( $message ? "\n\n" . __( 'Message from the editors:', 'absel-ai-library' ) . "\n" . $message : '' )
		. "\n\n" . absel_ail_submit_page_url();
	/* translators: %s: exercise title */
	wp_mail( $email, sprintf( __( 'Your exercise submission: %s', 'absel-ai-library' ), $title ), $body );
}

// WordPress's own "Post updated." notice would sit next to ours and mean nothing to editors here.
add_filter( 'redirect_post_location', function ( $location, $post_id ) {
	return 'absel_submission' === get_post_type( $post_id ) ? remove_query_arg( 'message', $location ) : $location;
}, 10, 2 );

function absel_ail_flash( $text, $type ) {
	set_transient( 'absel_ail_flash_' . get_current_user_id(), array( $text, $type ), 60 );
}

add_action( 'admin_notices', function () {
	$key   = 'absel_ail_flash_' . get_current_user_id();
	$flash = get_transient( $key );
	if ( $flash ) {
		delete_transient( $key );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $flash[1] ), esc_html( $flash[0] ) );
	}
} );

/* ---------- downloading submitted files ---------- */

function absel_ail_submission_file_url( $id, $name ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=absel_ail_sub_file&id=' . (int) $id . '&name=' . rawurlencode( $name ) ), 'absel_ail_sub_file_' . (int) $id );
}

add_action( 'admin_post_absel_ail_sub_file', function () {
	$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$name = isset( $_GET['name'] ) ? sanitize_text_field( wp_unslash( $_GET['name'] ) ) : '';
	check_admin_referer( 'absel_ail_sub_file_' . $id );
	if ( 'absel_submission' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html__( 'Not allowed.', 'absel-ai-library' ), 403 );
	}
	if ( 'manifest.json' === $name ) {
		$data = (string) get_post_meta( $id, '_absel_sub_manifest', true );
	} else {
		$path = absel_ail_submission_dir( $id ) . '/' . $name;
		if ( ! in_array( $name, absel_ail_manifest_files( absel_ail_submission_manifest( $id ) ), true ) || ! is_file( $path ) ) {
			wp_die( esc_html__( 'File not found.', 'absel-ai-library' ), 404 );
		}
	}
	nocache_headers();
	// Always a download, never displayed: submitted HTML must not run inside the admin.
	header( 'Content-Type: application/octet-stream' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $name ) . '"' );
	if ( isset( $data ) ) {
		echo $data; // phpcs:ignore WordPress.Security.EscapeOutput -- file download.
	} else {
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path );
	}
	exit;
} );
