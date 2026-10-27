<?php
/**
 * Membership checks, delegated to Simple Membership (SwpmAuth).
 *
 * Simple Membership keeps its own login session, separate from WordPress users,
 * so is_user_logged_in() is not a membership check. Administrators are let in
 * so they can preview content without a member account.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The logged-in Simple Membership member with an active account, or null.
 *
 * @return array{id:int,level:int}|null
 */
function absel_ail_current_member() {
	if ( ! class_exists( 'SwpmAuth' ) ) {
		return null;
	}
	$auth = SwpmAuth::get_instance();
	// Expired or inactive members may still be allowed to log in (a SWPM setting),
	// but they must not reach paid content.
	if ( ! $auth->is_logged_in() || $auth->is_expired_account() ) {
		return null;
	}
	return array(
		'id'    => (int) $auth->get( 'member_id' ),
		'level' => (int) $auth->get( 'membership_level' ),
	);
}

function absel_ail_member_has_access() {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	$member = absel_ail_current_member();
	if ( ! $member ) {
		return false;
	}
	$levels = absel_ail_allowed_levels();
	return empty( $levels ) || in_array( $member['level'], $levels, true );
}

/**
 * Who is acting: "m<id>" for a Simple Membership member, "u<id>" for an
 * administrator without a member account, or "" for anyone else.
 */
function absel_ail_current_owner() {
	$member = absel_ail_current_member();
	if ( $member ) {
		return 'm' . $member['id'];
	}
	return current_user_can( 'manage_options' ) ? 'u' . get_current_user_id() : '';
}

/**
 * Name and email of the current member (or administrator), for submissions.
 *
 * @return array{name:string,email:string}
 */
function absel_ail_owner_contact() {
	if ( absel_ail_current_member() ) {
		$auth = SwpmAuth::get_instance();
		return array(
			'name'  => trim( $auth->get( 'first_name' ) . ' ' . $auth->get( 'last_name' ) ),
			'email' => (string) $auth->get( 'email' ),
		);
	}
	$user = wp_get_current_user();
	return array(
		'name'  => (string) $user->display_name,
		'email' => (string) $user->user_email,
	);
}

/*
 * Request token for the submission API. Simple Membership members are often not
 * WordPress users, so WordPress's REST nonce can't tell them apart; this token
 * is signed for the member who loaded the page and expires after 12 hours.
 */

function absel_ail_submit_token() {
	$owner   = absel_ail_current_owner();
	$expires = time() + 12 * HOUR_IN_SECONDS;
	return $owner . '.' . $expires . '.' . absel_ail_sign( 'submit.' . $owner . '.' . $expires );
}

function absel_ail_submit_token_ok( $token ) {
	$parts = explode( '.', (string) $token );
	if ( 3 !== count( $parts ) ) {
		return false;
	}
	list( $owner, $expires, $signature ) = $parts;
	return '' !== $owner
		&& absel_ail_current_owner() === $owner
		&& (int) $expires >= time()
		&& hash_equals( absel_ail_sign( 'submit.' . $owner . '.' . $expires ), $signature );
}

/*
 * Student links. Students are not ABSEL members, so a member can hand a class
 * a link that opens one activity's simulation (not its instructor materials)
 * until it expires. The link carries "member.folder.expiry.signature".
 */

const ABSEL_AIL_CLASS_DAYS = 14;

function absel_ail_class_pass_create( $folder ) {
	$member = absel_ail_current_member();
	$owner  = $member ? 'm' . $member['id'] : 'u' . get_current_user_id();
	// Ends at a day boundary so the link stays the same all day.
	$expires = strtotime( 'tomorrow', time() ) + ( ABSEL_AIL_CLASS_DAYS - 1 ) * DAY_IN_SECONDS;
	$payload = $owner . '.' . $folder . '.' . $expires;
	return $payload . '.' . absel_ail_sign( $payload );
}

/**
 * @return array{owner:string,folder:string,expires:int}|null
 */
function absel_ail_class_pass_verify( $pass, $folder ) {
	$parts = explode( '.', (string) $pass );
	if ( 4 !== count( $parts ) ) {
		return null;
	}
	list( $owner, $pass_folder, $expires, $signature ) = $parts;
	$payload = $owner . '.' . $pass_folder . '.' . $expires;
	if ( $pass_folder !== $folder || (int) $expires < time() || ! hash_equals( absel_ail_sign( $payload ), $signature ) ) {
		return null;
	}
	// A link stops working if the member who made it lapses.
	if ( 'm' === $owner[0] && class_exists( 'SwpmMemberUtils' )
		&& 'active' !== SwpmMemberUtils::get_account_status_of_a_member( (int) substr( $owner, 1 ) ) ) {
		return null;
	}
	return array(
		'owner'   => $owner,
		'folder'  => $folder,
		'expires' => (int) $expires,
	);
}

function absel_ail_sign( $payload ) {
	return substr( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ), 0, 32 );
}

function absel_ail_login_url() {
	if ( class_exists( 'SwpmSettings' ) ) {
		$url = SwpmSettings::get_instance()->get_value( 'login-page-url' );
		if ( $url ) {
			return $url;
		}
	}
	return wp_login_url();
}

function absel_ail_join_url() {
	if ( class_exists( 'SwpmSettings' ) ) {
		$url = SwpmSettings::get_instance()->get_value( 'join-us-page-url' );
		if ( $url ) {
			return $url;
		}
	}
	return home_url( '/' );
}
