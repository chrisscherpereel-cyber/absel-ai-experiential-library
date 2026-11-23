<?php
/**
 * The SELAR brand on the site: the emblem, the stylesheet, and the two
 * invitations as shortcodes.
 *
 * [selar_home]   The emblem, name and tagline, with "Put it in the SELAR."
 *                (the submission page) and "What's in the SELAR?" (browsing).
 * [selar_browse] "What's in the SELAR?": search and filter the activities.
 */

defined( 'ABSPATH' ) || exit;

const ABSEL_AIL_PER_PAGE = 12;

/** The emblem as inline SVG, so it picks up the page's fonts. */
function absel_ail_emblem_svg() {
	static $svg = null;
	if ( null === $svg ) {
		$svg = (string) file_get_contents( ABSEL_AIL_DIR . 'assets/selar-emblem.svg' );
	}
	return $svg;
}

/**
 * The shelf icons from the two invitations: a card going onto the shelf
 * ("put") or coming off it ("take"). Lines and shelf use currentColor.
 */
function absel_ail_shelf_icon( $kind ) {
	$cards = '<rect x="8" y="34" width="30" height="26" rx="4" fill="#fbf1df"/><rect x="42" y="34" width="30" height="26" rx="4" fill="#fbf1df"/>'
		. '<g stroke="#0f4a40" stroke-width="3" stroke-linecap="round"><path d="M14 42h17M14 49h17M48 42h17M48 49h17"/></g>';
	if ( 'put' === $kind ) {
		$moving = '<g transform="rotate(-8 92 30)"><rect x="78" y="12" width="32" height="28" rx="4" fill="#f0b43a"/>'
			. '<g stroke="#0f4a40" stroke-width="3" stroke-linecap="round"><path d="M85 21h18M85 28h18"/></g></g>'
			. '<g stroke="#f0b43a" stroke-width="3" stroke-linecap="round"><path d="M72 10l-5-6M80 6V0M88 8l5-6"/></g>'
			. '<path d="M94 46v8m-4-4 4 4 4-4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>';
	} else {
		$moving = '<g transform="rotate(6 92 26)"><rect x="78" y="8" width="32" height="30" rx="4" fill="#fbf1df" stroke="#0f4a40" stroke-width="2"/>'
			. '<g stroke="#0f4a40" stroke-width="3" stroke-linecap="round"><path d="M85 17h18M85 24h18M85 31h12"/></g></g>'
			. '<g stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M70 12l-6-5M72 4l-3-5"/></g>';
	}
	return '<svg viewBox="0 -4 120 72" aria-hidden="true" focusable="false">' . $cards . $moving
		. '<rect x="2" y="60" width="116" height="7" rx="3" fill="currentColor"/>'
		. '<rect x="36" y="56" width="8" height="6" rx="2" fill="currentColor"/><rect x="70" y="56" width="8" height="6" rx="2" fill="currentColor"/></svg>';
}

/** Where "What's in the SELAR?" leads ("Put it in the SELAR." uses absel_ail_submit_page_url()). */
function absel_ail_browse_page_url() {
	$url = get_option( 'absel_ail_browse_page' );
	return $url ? $url : (string) get_post_type_archive_link( 'absel_activity' );
}

// The brand stylesheet and fonts, only where SELAR appears.
add_action( 'wp_enqueue_scripts', function () {
	$content = is_singular() ? (string) get_post_field( 'post_content', get_queried_object_id() ) : '';
	$ours    = is_singular( 'absel_activity' ) || is_post_type_archive( 'absel_activity' )
		|| is_tax( array( 'ai_topic', 'activity_format', 'discipline' ) )
		|| has_shortcode( $content, 'selar_home' ) || has_shortcode( $content, 'selar_browse' )
		|| has_shortcode( $content, 'absel_submit_exercise' );
	if ( ! $ours ) {
		return;
	}
	$deps = array();
	// Fonts come from Google Fonts unless a site turns them off with this filter;
	// without them the brand falls back to Rockwell and Georgia.
	if ( apply_filters( 'absel_ail_google_fonts', true ) ) {
		wp_enqueue_style( 'selar-fonts', 'https://fonts.googleapis.com/css2?family=Alfa+Slab+One&family=Source+Serif+4:ital,wght@0,400;0,600;1,400&family=Zilla+Slab:ital,wght@0,600;0,700;1,500&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		$deps[] = 'selar-fonts';
	}
	wp_enqueue_style( 'selar', plugins_url( 'assets/selar.css', ABSEL_AIL_FILE ), $deps, ABSEL_AIL_VERSION );
} );

add_shortcode( 'selar_home', function () {
	return '<div class="selar selar-home">'
		. '<div class="selar-hero"><div class="selar-emblem">' . absel_ail_emblem_svg() . '</div>'
		. '<p class="selar-name">' . esc_html__( 'Simulation & Experiential Learning Activities Repository', 'absel-ai-library' ) . '</p>'
		. '<p class="selar-tagline">' . esc_html__( 'A place for activities worth keeping.', 'absel-ai-library' ) . '</p></div>'
		. '<div class="selar-invites">'
		. '<a class="selar-invite selar-invite--put" href="' . esc_url( absel_ail_submit_page_url() ) . '">' . absel_ail_shelf_icon( 'put' )
		. '<span class="selar-invite-text"><span class="selar-invite-lead">' . esc_html__( 'Have a classroom exercise you’d gladly teach again?', 'absel-ai-library' ) . '</span>'
		. '<span class="selar-invite-cta">' . esc_html__( 'Put it in the SELAR.', 'absel-ai-library' ) . '</span></span></a>'
		. '<a class="selar-invite selar-invite--explore" href="' . esc_url( absel_ail_browse_page_url() ) . '">' . absel_ail_shelf_icon( 'take' )
		. '<span class="selar-invite-text"><span class="selar-invite-cta">' . esc_html__( 'What’s in the SELAR?', 'absel-ai-library' ) . '</span></span></a>'
		. '</div></div>';
} );

add_shortcode( 'selar_browse', function () {
	$permalink = get_permalink();
	if ( $permalink && get_option( 'absel_ail_browse_page' ) !== $permalink ) {
		update_option( 'absel_ail_browse_page', $permalink, false );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a public search form.
	$search  = isset( $_GET['selar_q'] ) ? sanitize_text_field( wp_unslash( $_GET['selar_q'] ) ) : '';
	$page    = isset( $_GET['selar_page'] ) ? max( 1, absint( $_GET['selar_page'] ) ) : 1;
	$filters = array(
		'discipline'      => __( 'Discipline', 'absel-ai-library' ),
		'activity_format' => __( 'Format', 'absel-ai-library' ),
		'ai_topic'        => __( 'AI topic', 'absel-ai-library' ),
	);
	$chosen  = array();
	foreach ( $filters as $tax => $label ) {
		$chosen[ $tax ] = isset( $_GET[ 'selar_' . $tax ] ) ? sanitize_title( wp_unslash( $_GET[ 'selar_' . $tax ] ) ) : '';
	}
	// phpcs:enable

	$args = array(
		'post_type'      => 'absel_activity',
		'post_status'    => 'publish',
		'posts_per_page' => ABSEL_AIL_PER_PAGE,
		'paged'          => $page,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( '' !== $search ) {
		$args['s']       = $search;
		$args['orderby'] = 'relevance';
	}
	$tax_query = array();
	foreach ( $chosen as $tax => $slug ) {
		if ( '' !== $slug ) {
			$tax_query[] = array( 'taxonomy' => $tax, 'field' => 'slug', 'terms' => $slug );
		}
	}
	if ( $tax_query ) {
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$query = new WP_Query( $args );

	$html = '<div class="selar selar-browse">'
		. '<div class="selar-banner selar-banner--explore">' . absel_ail_shelf_icon( 'take' )
		. '<div><h2>' . esc_html__( 'What’s in the SELAR?', 'absel-ai-library' ) . '</h2>'
		. '<p>' . esc_html__( 'Pull an activity from the shelf: simulations and experiential exercises shared by colleagues.', 'absel-ai-library' ) . '</p></div></div>';

	$html .= '<form class="selar-search" method="get" action="' . esc_url( (string) get_permalink() ) . '" role="search">'
		. '<div class="selar-field selar-field--q"><label for="selar-q">' . esc_html__( 'Search', 'absel-ai-library' ) . '</label>'
		. '<input id="selar-q" type="search" name="selar_q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Topic, skill, course…', 'absel-ai-library' ) . '"></div>';
	foreach ( $filters as $tax => $label ) {
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
		$html .= '<div class="selar-field"><label for="selar-' . esc_attr( $tax ) . '">' . esc_html( $label ) . '</label>'
			. '<select id="selar-' . esc_attr( $tax ) . '" name="selar_' . esc_attr( $tax ) . '"><option value="">' . esc_html__( 'Any', 'absel-ai-library' ) . '</option>';
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$html .= '<option value="' . esc_attr( $term->slug ) . '"' . selected( $chosen[ $tax ], $term->slug, false ) . '>' . esc_html( $term->name ) . '</option>';
		}
		$html .= '</select></div>';
	}
	$html .= '<button class="selar-button" type="submit">' . esc_html__( 'Search', 'absel-ai-library' ) . '</button></form>';

	if ( ! $query->have_posts() ) {
		$html .= '<div class="selar-empty"><p>' . esc_html__( 'Nothing on this shelf yet. Try fewer filters, or be the first:', 'absel-ai-library' ) . '</p>'
			. '<a class="selar-button selar-button--gold" href="' . esc_url( absel_ail_submit_page_url() ) . '">' . esc_html__( 'Put it in the SELAR.', 'absel-ai-library' ) . '</a></div></div>';
		return $html;
	}

	$html .= '<p class="selar-count" role="status">' . esc_html( sprintf(
		/* translators: %d: number of activities */
		_n( '%d activity', '%d activities', $query->found_posts, 'absel-ai-library' ),
		$query->found_posts
	) ) . '</p><ul class="selar-shelf">';
	foreach ( $query->posts as $post ) {
		$chips = array();
		foreach ( array( 'discipline', 'activity_format' ) as $tax ) {
			foreach ( (array) get_the_terms( $post, $tax ) as $term ) {
				if ( $term instanceof WP_Term ) {
					$chips[] = '<span class="selar-chip">' . esc_html( $term->name ) . '</span>';
				}
			}
		}
		$duration = get_post_meta( $post->ID, '_absel_ail_duration', true );
		if ( $duration ) {
			$chips[] = '<span class="selar-chip">' . esc_html( $duration ) . '</span>';
		}
		$html .= '<li class="selar-card"><h3><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>'
			. '<p>' . esc_html( wp_trim_words( get_the_excerpt( $post ), 32 ) ) . '</p>'
			. ( $chips ? '<div class="selar-meta">' . implode( '', $chips ) . '</div>' : '' ) . '</li>';
	}
	$html .= '</ul>';

	if ( $query->max_num_pages > 1 ) {
		$links = paginate_links( array(
			'base'      => esc_url_raw( add_query_arg( 'selar_page', '%#%' ) ),
			'format'    => '',
			'current'   => $page,
			'total'     => $query->max_num_pages,
			'prev_text' => __( 'Previous', 'absel-ai-library' ),
			'next_text' => __( 'Next', 'absel-ai-library' ),
		) );
		$html .= '<nav class="selar-pages" aria-label="' . esc_attr__( 'Pages', 'absel-ai-library' ) . '">' . $links . '</nav>';
	}
	return $html . '</div>';
} );
