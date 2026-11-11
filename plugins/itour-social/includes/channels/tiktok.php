<?php
/**
 * TikTok Content Posting API (direct post of the generated video).
 * Until TikTok approves the app in its audit, videos can only be posted as private (“Only me”) – TikTok's rule.
 */

defined( 'ABSPATH' ) || exit;

/** Access tokens last 24 hours; the refresh token (365 days) renews them. */
function itsoc_tt_token() {
	if ( (int) itsoc_opt( 'tt_expires' ) > time() + 300 && itsoc_opt( 'tt_access' ) ) {
		return itsoc_opt( 'tt_access' );
	}
	$r = itsoc_http( 'POST', 'https://open.tiktokapis.com/v2/oauth/token/', array( 'form' => array(
		'client_key'    => itsoc_opt( 'tt_client_key' ),
		'client_secret' => itsoc_opt( 'tt_client_secret' ),
		'grant_type'    => 'refresh_token',
		'refresh_token' => itsoc_opt( 'tt_refresh' ),
	) ) )['json'];
	if ( empty( $r['access_token'] ) ) {
		throw new Itsoc_Error( 'TikTok: ' . ( $r['error_description'] ?? 'could not renew the connection – click “Connect TikTok” again.' ) );
	}
	itsoc_save( array( 'tt_access' => $r['access_token'], 'tt_refresh' => $r['refresh_token'] ?? '', 'tt_expires' => time() + (int) $r['expires_in'] ) );
	return $r['access_token'];
}

function itsoc_tt( $path, array $json ) {
	$r = itsoc_http( 'POST', 'https://open.tiktokapis.com/v2/' . $path, array(
		'headers' => array( 'Authorization' => 'Bearer ' . itsoc_tt_token(), 'Content-Type' => 'application/json; charset=UTF-8' ),
		'body'    => $json ? wp_json_encode( $json ) : '{}', // TikTok wants an object, never [].
		'timeout' => 60,
	) )['json'];
	if ( ! empty( $r['error']['code'] ) && 'ok' !== $r['error']['code'] ) {
		throw new Itsoc_Error( 'TikTok: ' . ( $r['error']['message'] ?: $r['error']['code'] ), 'rate_limit_exceeded' === $r['error']['code'] );
	}
	return $r['data'] ?? array();
}

/**
 * Draft mode (default): the video goes to the @account's TikTok inbox; you add a sound and tap Post in the app
 * (TikTok's rule: until the app passes its audit, direct posts only work for private accounts).
 * Direct mode: published straight away with the caption.
 */
function itsoc_send_tiktok( $format, array $item, array $state ) {
	if ( 'direct' !== itsoc_opt( 'tt_mode' ) ) {
		return itsoc_send_tiktok_draft( $item, $state );
	}
	if ( empty( $state['publish_id'] ) ) {
		$video   = itsoc_video( $item );
		$creator = itsoc_tt( 'post/publish/creator_info/query/', array() );
		$allowed = (array) ( $creator['privacy_level_options'] ?? array( 'SELF_ONLY' ) );
		$privacy = in_array( itsoc_opt( 'tt_privacy' ), $allowed, true ) ? itsoc_opt( 'tt_privacy' ) : ( in_array( 'SELF_ONLY', $allowed, true ) ? 'SELF_ONLY' : reset( $allowed ) );
		$size    = filesize( $video[0] );
		$init    = itsoc_tt( 'post/publish/video/init/', array(
			'post_info'   => array(
				'title'           => mb_substr( itsoc_caption( $item, 'tiktok', $format ), 0, 2200 ),
				'privacy_level'   => $privacy,
				'disable_comment' => false,
				'disable_duet'    => false,
				'disable_stitch'  => false,
			),
			'source_info' => array( 'source' => 'FILE_UPLOAD', 'video_size' => $size, 'chunk_size' => $size, 'total_chunk_count' => 1 ),
		) );
		itsoc_http( 'PUT', $init['upload_url'], array(
			'headers' => array( 'Content-Type' => 'video/mp4', 'Content-Range' => 'bytes 0-' . ( $size - 1 ) . '/' . $size ),
			'body'    => file_get_contents( $video[0] ),
			'timeout' => 180,
		) );
		$state = array( 'publish_id' => $init['publish_id'], 'privacy' => $privacy );
	}
	$s = itsoc_tt( 'post/publish/status/fetch/', array( 'publish_id' => $state['publish_id'] ) );
	if ( 'FAILED' === ( $s['status'] ?? '' ) ) {
		throw new Itsoc_Error( 'TikTok could not publish the video: ' . ( $s['fail_reason'] ?? 'unknown reason' ) );
	}
	if ( 'PUBLISH_COMPLETE' !== ( $s['status'] ?? '' ) ) {
		return array( 'wait' => $state );
	}
	$id = $s['publicaly_available_post_id'][0] ?? ( $s['publicly_available_post_id'][0] ?? '' );
	return array(
		'remote' => $id && itsoc_opt( 'tt_username' ) ? 'https://www.tiktok.com/@' . itsoc_opt( 'tt_username' ) . '/video/' . $id : (string) $state['publish_id'],
		'note'   => 'SELF_ONLY' === $state['privacy'] ? 'Posted as private (“Only me”) – TikTok allows public posts once the app passes its audit.' : '',
	);
}

/** Uploads the video to the TikTok inbox as a draft; the caption is kept in the queue to copy into the app. */
function itsoc_send_tiktok_draft( array $item, array $state ) {
	if ( empty( $state['publish_id'] ) ) {
		$video = itsoc_video( $item );
		$size  = filesize( $video[0] );
		$init  = itsoc_tt( 'post/publish/inbox/video/init/', array(
			'source_info' => array( 'source' => 'FILE_UPLOAD', 'video_size' => $size, 'chunk_size' => $size, 'total_chunk_count' => 1 ),
		) );
		itsoc_http( 'PUT', $init['upload_url'], array(
			'headers' => array( 'Content-Type' => 'video/mp4', 'Content-Range' => 'bytes 0-' . ( $size - 1 ) . '/' . $size ),
			'body'    => file_get_contents( $video[0] ),
			'timeout' => 180,
		) );
		$state = array( 'publish_id' => $init['publish_id'] );
	}
	$s = itsoc_tt( 'post/publish/status/fetch/', array( 'publish_id' => $state['publish_id'] ) );
	if ( 'FAILED' === ( $s['status'] ?? '' ) ) {
		throw new Itsoc_Error( 'TikTok could not take the video: ' . ( $s['fail_reason'] ?? 'unknown reason' ) );
	}
	if ( ! in_array( $s['status'] ?? '', array( 'SEND_TO_USER_INBOX', 'PUBLISH_COMPLETE' ), true ) ) {
		return array( 'wait' => $state );
	}
	return array(
		'remote' => '',
		'note'   => 'Draft sent to the TikTok inbox – open TikTok, add a sound and post. Caption: ' . itsoc_caption( $item, 'tiktok', 'short' ),
	);
}

/** Uses the Content Posting API's creator info (covered by video.publish), which also gives the @username. */
function itsoc_test_tiktok() {
	$c = itsoc_tt( 'post/publish/creator_info/query/', array() );
	$u = (string) ( $c['creator_username'] ?? '' );
	if ( $u ) {
		itsoc_save( array( 'tt_username' => $u ) );
	}
	return 'TikTok: @' . ( $u ?: ( $c['creator_nickname'] ?? '?' ) ) . ' · can post as: ' . implode( ', ', (array) ( $c['privacy_level_options'] ?? array() ) );
}
