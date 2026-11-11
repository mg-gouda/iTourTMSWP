<?php
/**
 * "Connect …" buttons: the official sign-in of each network. You log in on their site and approve;
 * the site receives a token (never your password) and stores it encrypted.
 * Redirect URL to register in each developer app: https://<site>/wp-json/itour-social/v1/oauth
 */

defined( 'ABSPATH' ) || exit;

function itsoc_redirect_uri() {
	return rest_url( 'itour-social/v1/oauth' );
}

function itsoc_admin_url( array $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=itour-social' ) );
}

function itsoc_notice( $text, $type = 'success' ) {
	set_transient( 'itsoc_notice_' . get_current_user_id(), array( $text, $type ), 120 );
}

/** Where each network's sign-in starts. */
function itsoc_auth_url( $provider, $state ) {
	$redirect = itsoc_redirect_uri();
	switch ( $provider ) {
		case 'facebook': // Apps using "Facebook Login for Business" ask for a configuration ID instead of a scope list.
			return 'https://www.facebook.com/' . itsoc_opt( 'fb_version' ) . '/dialog/oauth?' . http_build_query( array_merge(
				array(
					'client_id'     => itsoc_opt( 'fb_app_id' ),
					'redirect_uri'  => $redirect,
					'state'         => $state,
					'response_type' => 'code',
				),
				itsoc_opt( 'fb_config_id' )
					? array( 'config_id' => itsoc_opt( 'fb_config_id' ) )
					: array( 'scope' => 'pages_show_list,pages_read_engagement,pages_manage_posts,instagram_basic,instagram_content_publish,business_management' )
			) );
		case 'linkedin':
			return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query( array(
				'response_type' => 'code',
				'client_id'     => itsoc_opt( 'li_client_id' ),
				'redirect_uri'  => $redirect,
				'state'         => $state,
				'scope'         => 'w_organization_social r_organization_social rw_organization_admin',
			), '', '&', PHP_QUERY_RFC3986 );
		case 'tiktok':
			return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query( array(
				'client_key'    => itsoc_opt( 'tt_client_key' ),
				'response_type' => 'code',
				'scope'         => 'user.info.basic,video.publish,video.upload',
				'redirect_uri'  => $redirect,
				'state'         => $state,
			) );
		case 'google':
			return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( array(
				'client_id'              => itsoc_opt( 'g_client_id' ),
				'redirect_uri'           => $redirect,
				'response_type'          => 'code',
				'scope'                  => 'openid email https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/business.manage',
				'access_type'            => 'offline',
				'prompt'                 => 'consent',
				'include_granted_scopes' => 'true',
				'state'                  => $state,
			), '', '&', PHP_QUERY_RFC3986 );
	}
	return '';
}

add_action( 'admin_post_itsoc_connect', function () {
	$provider = sanitize_key( $_GET['provider'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'itsoc_connect_' . $provider ) ) {
		wp_die( 'Not allowed.' );
	}
	$state = wp_generate_password( 32, false );
	set_transient( 'itsoc_state_' . $state, $provider, 20 * MINUTE_IN_SECONDS );
	$url = itsoc_auth_url( $provider, $state );
	if ( ! $url || false !== strpos( $url, 'client_id=&' ) || false !== strpos( $url, 'client_key=&' ) ) {
		itsoc_notice( 'Save the app ID / client ID and secret first, then click Connect.', 'error' );
		wp_safe_redirect( itsoc_admin_url( array( 'tab' => 'channels' ) ) );
		exit;
	}
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- the networks' own sign-in pages.
	exit;
} );

add_action( 'admin_post_itsoc_disconnect', function () {
	$provider = sanitize_key( $_GET['provider'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'itsoc_disconnect_' . $provider ) ) {
		wp_die( 'Not allowed.' );
	}
	$clear = array(
		'facebook' => array( 'fb_page_token' => '', 'fb_pages' => '', 'fb_page_id' => '', 'fb_page_name' => '', 'ig_user_id' => '', 'ig_username' => '' ),
		'linkedin' => array( 'li_token' => '', 'li_refresh' => '', 'li_token_expires' => 0, 'li_org_id' => '', 'li_org_name' => '', 'li_orgs' => array() ),
		'tiktok'   => array( 'tt_access' => '', 'tt_refresh' => '', 'tt_expires' => 0, 'tt_username' => '' ),
		'google'   => array( 'g_refresh' => '', 'g_email' => '', 'gbp_account' => '', 'gbp_location' => '', 'gbp_name' => '', 'gbp_locations' => array() ),
		'x'        => array( 'x_access_token' => '', 'x_access_secret' => '', 'x_username' => '' ),
	)[ $provider ] ?? array();
	itsoc_save( $clear, true );
	delete_transient( 'itsoc_g_access' );
	itsoc_notice( 'Disconnected.' );
	wp_safe_redirect( itsoc_admin_url( array( 'tab' => 'channels' ) ) );
	exit;
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'itour-social/v1', '/oauth', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true', // Protected by the one-time state value below.
		'callback'            => 'itsoc_oauth_callback',
	) );
} );

function itsoc_oauth_callback( WP_REST_Request $req ) {
	$state    = sanitize_text_field( (string) $req->get_param( 'state' ) );
	$provider = $state ? get_transient( 'itsoc_state_' . $state ) : false;
	$back     = itsoc_admin_url( array( 'tab' => 'channels' ) );
	if ( ! $provider ) {
		wp_safe_redirect( add_query_arg( 'itsoc_err', rawurlencode( 'The sign-in link expired – please click Connect again.' ), $back ) );
		exit;
	}
	delete_transient( 'itsoc_state_' . $state );
	$code = (string) $req->get_param( 'code' );
	try {
		if ( '' === $code ) {
			throw new Itsoc_Error( (string) ( $req->get_param( 'error_description' ) ?: $req->get_param( 'error_message' ) ?: $req->get_param( 'error' ) ?: 'Sign-in was cancelled.' ) );
		}
		$msg = call_user_func( 'itsoc_oauth_finish_' . $provider, $code );
		wp_safe_redirect( add_query_arg( 'itsoc_ok', rawurlencode( $msg ), $back ) );
	} catch ( Throwable $e ) {
		wp_safe_redirect( add_query_arg( 'itsoc_err', rawurlencode( ucfirst( $provider ) . ': ' . $e->getMessage() ), $back ) );
	}
	exit;
}

function itsoc_oauth_finish_facebook( $code ) {
	$v     = itsoc_opt( 'fb_version' );
	$short = itsoc_http( 'GET', "https://graph.facebook.com/$v/oauth/access_token?" . http_build_query( array(
		'client_id'     => itsoc_opt( 'fb_app_id' ),
		'client_secret' => itsoc_opt( 'fb_app_secret' ),
		'redirect_uri'  => itsoc_redirect_uri(),
		'code'          => $code,
	) ) )['json']['access_token'];
	$long = itsoc_http( 'GET', "https://graph.facebook.com/$v/oauth/access_token?" . http_build_query( array(
		'grant_type'        => 'fb_exchange_token',
		'client_id'         => itsoc_opt( 'fb_app_id' ),
		'client_secret'     => itsoc_opt( 'fb_app_secret' ),
		'fb_exchange_token' => $short,
	) ) )['json']['access_token'];
	$pages = itsoc_http( 'GET', "https://graph.facebook.com/$v/me/accounts?" . http_build_query( array(
		'fields'       => 'id,name,access_token,instagram_business_account{id,username}',
		'limit'        => 50,
		'access_token' => $long,
	) ) )['json']['data'] ?? array();
	if ( ! $pages ) {
		throw new Itsoc_Error( 'No Facebook Page was shared with the app. Click Connect again and tick your Page (and its Instagram account).' );
	}
	$list = array();
	foreach ( $pages as $p ) {
		$list[ $p['id'] ] = array(
			'name'  => $p['name'],
			'token' => $p['access_token'],
			'ig'    => $p['instagram_business_account']['id'] ?? '',
			'igu'   => $p['instagram_business_account']['username'] ?? '',
		);
	}
	itsoc_save( array( 'fb_pages' => $list ) );
	$pick = isset( $list[ itsoc_opt( 'fb_page_id' ) ] ) ? itsoc_opt( 'fb_page_id' ) : array_key_first( $list );
	itsoc_select_page( $pick );
	return 'Facebook connected: ' . $list[ $pick ]['name'] . ( $list[ $pick ]['igu'] ? ' + Instagram @' . $list[ $pick ]['igu'] : ' (no Instagram professional account linked to this Page)' );
}

/** Uses one of the connected Pages (and its Instagram account). */
function itsoc_select_page( $id ) {
	$list = (array) itsoc_opt( 'fb_pages' );
	if ( ! isset( $list[ $id ] ) ) {
		return;
	}
	itsoc_save( array(
		'fb_page_id'    => $id,
		'fb_page_name'  => $list[ $id ]['name'],
		'fb_page_token' => $list[ $id ]['token'],
		'ig_user_id'    => $list[ $id ]['ig'],
		'ig_username'   => $list[ $id ]['igu'],
	) );
}

function itsoc_oauth_finish_linkedin( $code ) {
	$t = itsoc_http( 'POST', 'https://www.linkedin.com/oauth/v2/accessToken', array( 'form' => array(
		'grant_type'    => 'authorization_code',
		'code'          => $code,
		'redirect_uri'  => itsoc_redirect_uri(),
		'client_id'     => itsoc_opt( 'li_client_id' ),
		'client_secret' => itsoc_opt( 'li_client_secret' ),
	) ) )['json'];
	itsoc_save( array( 'li_token' => $t['access_token'], 'li_token_expires' => time() + (int) $t['expires_in'], 'li_refresh' => $t['refresh_token'] ?? '' ) );
	$acl  = itsoc_li( 'GET', 'organizationAcls?q=roleAssignee&role=ADMINISTRATOR&state=APPROVED' )['json']['elements'] ?? array();
	$orgs = array();
	foreach ( $acl as $a ) {
		$id = (string) preg_replace( '/\D/', '', $a['organization'] ?? '' );
		if ( $id ) {
			$o           = itsoc_li( 'GET', 'organizations/' . $id )['json'];
			$orgs[ $id ] = $o['localizedName'] ?? $id;
		}
	}
	if ( ! $orgs ) {
		throw new Itsoc_Error( 'This LinkedIn account is not an admin of any company Page.' );
	}
	$pick = isset( $orgs[ itsoc_opt( 'li_org_id' ) ] ) ? itsoc_opt( 'li_org_id' ) : array_key_first( $orgs );
	itsoc_save( array( 'li_orgs' => $orgs, 'li_org_id' => $pick, 'li_org_name' => $orgs[ $pick ] ) );
	return 'LinkedIn connected: ' . $orgs[ $pick ] . ' (valid ' . round( $t['expires_in'] / DAY_IN_SECONDS ) . ' days' . ( empty( $t['refresh_token'] ) ? ', then click Connect again' : ', renews automatically' ) . ')';
}

function itsoc_oauth_finish_tiktok( $code ) {
	$t = itsoc_http( 'POST', 'https://open.tiktokapis.com/v2/oauth/token/', array( 'form' => array(
		'client_key'    => itsoc_opt( 'tt_client_key' ),
		'client_secret' => itsoc_opt( 'tt_client_secret' ),
		'code'          => $code,
		'grant_type'    => 'authorization_code',
		'redirect_uri'  => itsoc_redirect_uri(),
	) ) )['json'];
	if ( empty( $t['access_token'] ) ) {
		throw new Itsoc_Error( $t['error_description'] ?? 'no token returned' );
	}
	itsoc_save( array( 'tt_access' => $t['access_token'], 'tt_refresh' => $t['refresh_token'], 'tt_expires' => time() + (int) $t['expires_in'] ) );
	return 'TikTok connected. ' . itsoc_test_tiktok();
}

function itsoc_oauth_finish_google( $code ) {
	$t = itsoc_http( 'POST', 'https://oauth2.googleapis.com/token', array( 'form' => array(
		'code'          => $code,
		'client_id'     => itsoc_opt( 'g_client_id' ),
		'client_secret' => itsoc_opt( 'g_client_secret' ),
		'redirect_uri'  => itsoc_redirect_uri(),
		'grant_type'    => 'authorization_code',
	) ) )['json'];
	if ( empty( $t['refresh_token'] ) ) {
		throw new Itsoc_Error( 'Google did not return a long-term token. Remove the app at myaccount.google.com/permissions and connect again.' );
	}
	$claims = json_decode( base64_decode( strtr( explode( '.', $t['id_token'] ?? '..' )[1], '-_', '+/' ) ), true );
	itsoc_save( array( 'g_refresh' => $t['refresh_token'], 'g_email' => $claims['email'] ?? '' ) );
	set_transient( 'itsoc_g_access', itsoc_encrypt( $t['access_token'] ), 3000 );
	$msg = 'Google connected (' . ( $claims['email'] ?? '' ) . ').';
	try {
		$locs = itsoc_gbp_locations();
		itsoc_save( array( 'gbp_locations' => $locs ) );
		if ( $locs && ! isset( $locs[ itsoc_opt( 'gbp_account' ) . '|' . itsoc_opt( 'gbp_location' ) ] ) ) {
			itsoc_select_location( array_key_first( $locs ) );
		}
		$msg .= $locs ? ' Business Profile: ' . itsoc_opt( 'gbp_name' ) . '.' : ' No Business Profile found on this account.';
	} catch ( Itsoc_Error $e ) {
		$msg .= ' Business Profile: ' . $e->getMessage();
	}
	return $msg;
}

function itsoc_select_location( $key ) {
	$locs = (array) itsoc_opt( 'gbp_locations' );
	if ( isset( $locs[ $key ] ) ) {
		list( $account, $location ) = explode( '|', $key );
		itsoc_save( array( 'gbp_account' => $account, 'gbp_location' => $location, 'gbp_name' => $locs[ $key ] ) );
	}
}
