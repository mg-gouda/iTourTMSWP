<?php
/**
 * One Google connection for YouTube Shorts and Google Business Profile.
 */

defined( 'ABSPATH' ) || exit;

/** Short-lived access token from the stored refresh token (kept encrypted for up to 50 minutes). */
function itsoc_google_token() {
	$cached = itsoc_decrypt( (string) get_transient( 'itsoc_g_access' ) );
	if ( $cached ) {
		return $cached;
	}
	$r = itsoc_http( 'POST', 'https://oauth2.googleapis.com/token', array( 'form' => array(
		'client_id'     => itsoc_opt( 'g_client_id' ),
		'client_secret' => itsoc_opt( 'g_client_secret' ),
		'refresh_token' => itsoc_opt( 'g_refresh' ),
		'grant_type'    => 'refresh_token',
	), 'allow_error' => true ) );
	if ( empty( $r['json']['access_token'] ) ) {
		throw new Itsoc_Error( 'Google: ' . itsoc_api_message( $r ) . ' – click “Connect Google” again.' );
	}
	set_transient( 'itsoc_g_access', itsoc_encrypt( $r['json']['access_token'] ), min( 3000, (int) $r['json']['expires_in'] - 120 ) );
	return $r['json']['access_token'];
}

function itsoc_google( $method, $url, array $o = array() ) {
	$o['headers']                  = ( $o['headers'] ?? array() ) + array( 'Authorization' => 'Bearer ' . itsoc_google_token() );
	return itsoc_http( $method, $url, $o );
}

function itsoc_send_youtube( $format, array $item, array $state ) {
	$video = itsoc_video( $item );
	$size  = filesize( $video[0] );
	$tags  = array_map( function ( $t ) {
		return ltrim( $t, '#' );
	}, preg_split( '/\s+/', itsoc_hashtags( $item, 'instagram' ) ) );
	$start = itsoc_google( 'POST', 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', array(
		'headers' => array( 'X-Upload-Content-Length' => (string) $size, 'X-Upload-Content-Type' => 'video/mp4' ),
		'json'    => array(
			'snippet' => array(
				'title'       => itsoc_youtube_title( $item ),
				'description' => mb_substr( itsoc_caption( $item, 'youtube', $format ), 0, 4900 ),
				'tags'        => array_slice( array_filter( $tags ), 0, 15 ),
				'categoryId'  => '19', // Travel & Events.
			),
			'status'  => array( 'privacyStatus' => itsoc_opt( 'yt_privacy' ), 'selfDeclaredMadeForKids' => false ),
		),
	) );
	$upload = $start['headers']['location'] ?? '';
	if ( ! $upload ) {
		throw new Itsoc_Error( 'YouTube did not start the upload.' );
	}
	$r = itsoc_google( 'PUT', $upload, array( 'headers' => array( 'Content-Type' => 'video/mp4' ), 'body' => file_get_contents( $video[0] ), 'timeout' => 300 ) )['json'];
	return array( 'remote' => 'https://youtube.com/shorts/' . ( $r['id'] ?? '' ) );
}

function itsoc_send_gbp( $format, array $item, array $state ) {
	$url = 'https://mybusiness.googleapis.com/v4/' . itsoc_opt( 'gbp_account' ) . '/' . itsoc_opt( 'gbp_location' ) . '/localPosts';
	$r   = itsoc_google( 'POST', $url, array( 'json' => array(
		'languageCode' => 'en',
		'topicType'    => 'STANDARD',
		'summary'      => itsoc_caption( $item, 'gbp', $format ),
		'callToAction' => array( 'actionType' => itsoc_gbp_action( $item ), 'url' => itsoc_link( $item, 'google_business' ) ),
		'media'        => array( array( 'mediaFormat' => 'PHOTO', 'sourceUrl' => itsoc_card( $item, 1200, 900 )[1] ) ),
	) ) )['json'];
	return array( 'remote' => (string) ( $r['searchUrl'] ?? $r['name'] ?? '' ) );
}

/** Business Profile locations the connected Google account manages: [ 'accounts/1|locations/2' => 'Title' ]. */
function itsoc_gbp_locations() {
	$out      = array();
	$accounts = itsoc_google( 'GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts' )['json']['accounts'] ?? array();
	foreach ( $accounts as $a ) {
		$locs = itsoc_google( 'GET', 'https://mybusinessbusinessinformation.googleapis.com/v1/' . $a['name'] . '/locations?readMask=name,title&pageSize=100' )['json']['locations'] ?? array();
		foreach ( $locs as $l ) {
			$out[ $a['name'] . '|' . $l['name'] ] = $l['title'] . ' (' . ( $a['accountName'] ?? $a['name'] ) . ')';
		}
	}
	return $out;
}

function itsoc_test_youtube() {
	$c = itsoc_google( 'GET', 'https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true' )['json'];
	return 'YouTube channel: ' . ( $c['items'][0]['snippet']['title'] ?? 'none found on this Google account' );
}

function itsoc_test_gbp() {
	$l = itsoc_google( 'GET', 'https://mybusinessbusinessinformation.googleapis.com/v1/' . itsoc_opt( 'gbp_location' ) . '?readMask=title' )['json'];
	return 'Business Profile: ' . ( $l['title'] ?? '?' );
}
