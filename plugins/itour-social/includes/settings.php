<?php
/**
 * Settings and the encrypted vault for keys and tokens.
 * Secrets are stored encrypted with a key derived from this site's wp-config salts, so a database copy alone does not reveal them.
 */

defined( 'ABSPATH' ) || exit;

/** Every channel and the formats it supports. */
function itsoc_channels() {
	return array(
		'facebook'  => array( 'label' => 'Facebook Page', 'formats' => array( 'link', 'reel', 'carousel', 'story', 'image' ) ),
		'instagram' => array( 'label' => 'Instagram', 'formats' => array( 'reel', 'carousel', 'story', 'image' ) ),
		'linkedin'  => array( 'label' => 'LinkedIn Page', 'formats' => array( 'link', 'carousel' ) ),
		'x'         => array( 'label' => 'X (Twitter)', 'formats' => array( 'link' ) ),
		'tiktok'    => array( 'label' => 'TikTok', 'formats' => array( 'short' ) ),
		'youtube'   => array( 'label' => 'YouTube Shorts', 'formats' => array( 'short' ) ),
		'gbp'       => array( 'label' => 'Google Business Profile', 'formats' => array( 'post' ) ),
		'mailchimp' => array( 'label' => 'Email list (Mailchimp)', 'formats' => array( 'email' ) ),
	);
}

/** Keys whose values are secret: kept encrypted, never printed back into the settings page. */
function itsoc_secret_keys() {
	return array( 'fb_app_secret', 'fb_page_token', 'fb_pages', 'li_client_secret', 'li_token', 'li_refresh','x_api_secret', 'x_access_token', 'x_access_secret', 'tt_client_secret', 'tt_access', 'tt_refresh', 'g_client_secret', 'g_refresh', 'mc_api_key' );
}

function itsoc_defaults() {
	return array(
		'enabled'          => 0,
		'channels'         => array(),          // channel => 1 when switched on.
		'post_steps'       => array( 'link', 'reel', 'short', 'carousel', 'story', 'email', 'gbp' ),
		'product_channels' => array( 'facebook:image', 'instagram:image', 'instagram:story', 'linkedin:link', 'x:link', 'gbp:post' ),
		'product_types'    => array( 'tour', 'activity', 'transfer' ),
		'daily_hour'       => 11,
		'backfill_days'    => 3,
		'hashtags'         => '#travel #holiday #daytrip',
		'utm'              => 1,
		'ffmpeg'           => '',
		'fb_version'       => 'v23.0',
		'fb_app_id'        => '', 'fb_config_id' => '', 'fb_page_id' => '', 'fb_page_name' => '', 'ig_user_id' => '', 'ig_username' => '',
		'li_client_id'     => '', 'li_org_id' => '', 'li_org_name' => '', 'li_token_expires' => 0, 'li_orgs' => array(), 'gbp_locations' => array(),
		'x_api_key'        => '', 'x_username' => '',
		'tt_client_key'    => '', 'tt_privacy' => 'SELF_ONLY', 'tt_mode' => 'draft', 'tt_expires' => 0, 'tt_username' => '',
		'g_client_id'      => '', 'g_email' => '', 'yt_privacy' => 'public', 'gbp_account' => '', 'gbp_location' => '', 'gbp_name' => '',
		'mc_list'          => '', 'mc_from_name' => '', 'mc_reply_to' => '',
	);
}

function itsoc_settings() {
	return wp_parse_args( (array) get_option( 'itsoc_settings', array() ), itsoc_defaults() );
}

function itsoc_opt( $key ) {
	$s = itsoc_settings();
	if ( in_array( $key, itsoc_secret_keys(), true ) ) {
		$v = itsoc_decrypt( $s[ $key ] ?? '' );
		if ( 'mc_api_key' === $key && '' === $v ) { // Reuse the key already saved in "Mailchimp for WP".
			$mc = (array) get_option( 'mc4wp', array() );
			$v  = (string) ( $mc['api_key'] ?? '' );
		}
		return 'fb_pages' === $key ? (array) json_decode( $v, true ) : $v;
	}
	return $s[ $key ] ?? null;
}

/** Saves values; secrets are encrypted. An empty secret leaves the stored one alone unless $clear. */
function itsoc_save( array $values, $clear = false ) {
	$s = (array) get_option( 'itsoc_settings', array() );
	foreach ( $values as $k => $v ) {
		if ( in_array( $k, itsoc_secret_keys(), true ) ) {
			if ( is_array( $v ) ) {
				$v = wp_json_encode( $v );
			}
			if ( '' === (string) $v && ! $clear ) {
				continue;
			}
			$v = '' === (string) $v ? '' : itsoc_encrypt( (string) $v );
		}
		$s[ $k ] = $v;
	}
	update_option( 'itsoc_settings', $s, false );
}

function itsoc_vault_key() {
	return sodium_crypto_generichash( 'itour-social|' . AUTH_KEY . SECURE_AUTH_KEY, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
}

function itsoc_encrypt( $plain ) {
	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	return 'v1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, itsoc_vault_key() ) );
}

function itsoc_decrypt( $stored ) {
	if ( ! is_string( $stored ) || 0 !== strpos( $stored, 'v1:' ) ) {
		return '';
	}
	$raw   = base64_decode( substr( $stored, 3 ), true );
	$nonce = substr( (string) $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$plain = $raw ? sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, itsoc_vault_key() ) : false;
	return false === $plain ? '' : $plain;
}

/** Is a channel switched on and does it have what it needs to post? */
function itsoc_ready( $channel ) {
	$on = ! empty( itsoc_opt( 'channels' )[ $channel ] );
	switch ( $channel ) {
		case 'facebook':
			return $on && itsoc_opt( 'fb_page_id' ) && itsoc_opt( 'fb_page_token' );
		case 'instagram':
			return $on && itsoc_opt( 'ig_user_id' ) && itsoc_opt( 'fb_page_token' );
		case 'linkedin':
			return $on && itsoc_opt( 'li_org_id' ) && itsoc_opt( 'li_token' );
		case 'x':
			return $on && itsoc_opt( 'x_api_key' ) && itsoc_opt( 'x_api_secret' ) && itsoc_opt( 'x_access_token' ) && itsoc_opt( 'x_access_secret' );
		case 'tiktok':
			return $on && itsoc_opt( 'tt_refresh' ) && itsoc_ffmpeg();
		case 'youtube':
			return $on && itsoc_opt( 'g_refresh' ) && itsoc_ffmpeg();
		case 'gbp':
			return $on && itsoc_opt( 'g_refresh' ) && itsoc_opt( 'gbp_location' );
		case 'mailchimp':
			return $on && itsoc_opt( 'mc_api_key' ) && itsoc_opt( 'mc_list' );
	}
	return false;
}
