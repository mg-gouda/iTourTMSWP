<?php
/**
 * X (Twitter) API v2 with the four keys from the developer portal (OAuth 1.0a, user context).
 * The link gets X's preview card from the page's Open Graph tags (iTour SEO).
 */

defined( 'ABSPATH' ) || exit;

function itsoc_x( $method, $url, ?array $json = null ) {
	$oauth = array(
		'oauth_consumer_key'     => itsoc_opt( 'x_api_key' ),
		'oauth_nonce'            => wp_generate_password( 32, false ),
		'oauth_signature_method' => 'HMAC-SHA1',
		'oauth_timestamp'        => (string) time(),
		'oauth_token'            => itsoc_opt( 'x_access_token' ),
		'oauth_version'          => '1.0',
	);
	$parts = wp_parse_url( $url );
	$base  = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
	$sign  = $oauth;
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $q );
		$sign = array_merge( $sign, $q );
	}
	ksort( $sign );
	$pairs = array();
	foreach ( $sign as $k => $v ) {
		$pairs[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
	}
	$base_string             = strtoupper( $method ) . '&' . rawurlencode( $base ) . '&' . rawurlencode( implode( '&', $pairs ) );
	$key                     = rawurlencode( itsoc_opt( 'x_api_secret' ) ) . '&' . rawurlencode( itsoc_opt( 'x_access_secret' ) );
	$oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base_string, $key, true ) );
	$header                  = array();
	foreach ( $oauth as $k => $v ) {
		$header[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
	}
	$o = array( 'headers' => array( 'Authorization' => 'OAuth ' . implode( ', ', $header ) ) );
	if ( null !== $json ) {
		$o['json'] = $json;
	}
	return itsoc_http( $method, $url, $o )['json'];
}

function itsoc_send_x( $format, array $item, array $state ) {
	if ( 'link' !== $format ) {
		throw new Itsoc_Error( "X does not support “$format”." );
	}
	$r  = itsoc_x( 'POST', 'https://api.x.com/2/tweets', array( 'text' => itsoc_caption( $item, 'x', $format ) ) );
	$id = $r['data']['id'] ?? '';
	return array( 'remote' => $id ? 'https://x.com/' . ( itsoc_opt( 'x_username' ) ?: 'i' ) . '/status/' . $id : '' );
}

function itsoc_test_x() {
	$me = itsoc_x( 'GET', 'https://api.x.com/2/users/me' );
	if ( ! empty( $me['data']['username'] ) ) {
		itsoc_save( array( 'x_username' => $me['data']['username'] ) );
	}
	return 'X: @' . ( $me['data']['username'] ?? '?' );
}
