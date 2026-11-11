<?php
/**
 * Facebook Page and Instagram (professional account linked to the Page) through Meta's Graph API.
 * Both use the Page access token from "Connect Facebook" – it does not expire while the app keeps its permissions.
 */

defined( 'ABSPATH' ) || exit;

function itsoc_graph( $method, $path, array $params = array() ) {
	$url              = 'https://graph.facebook.com/' . itsoc_opt( 'fb_version' ) . '/' . ltrim( $path, '/' );
	$params['access_token'] = itsoc_opt( 'fb_page_token' );
	if ( 'GET' === $method ) {
		return itsoc_http( 'GET', add_query_arg( array_map( 'rawurlencode', $params ), $url ) )['json'];
	}
	return itsoc_http( $method, $url, array( 'form' => $params, 'timeout' => 90 ) )['json'];
}

function itsoc_send_facebook( $format, array $item, array $state ) {
	$page    = itsoc_opt( 'fb_page_id' );
	$caption = itsoc_caption( $item, 'facebook', $format );
	switch ( $format ) {
		case 'link':
			$r = itsoc_graph( 'POST', "$page/feed", array( 'message' => $caption, 'link' => itsoc_link( $item, 'facebook' ) ) );
			return array( 'remote' => 'https://facebook.com/' . $r['id'] );

		case 'image':
			$r = itsoc_graph( 'POST', "$page/photos", array( 'url' => itsoc_card( $item )[1], 'caption' => $caption ) );
			return array( 'remote' => 'https://facebook.com/' . ( $r['post_id'] ?? $r['id'] ) );

		case 'carousel':
			$params = array( 'message' => $caption );
			foreach ( itsoc_slides( $item ) as $i => $s ) {
				$photo                             = itsoc_graph( 'POST', "$page/photos", array( 'url' => $s[1], 'published' => 'false' ) );
				$params[ 'attached_media[' . $i . ']' ] = wp_json_encode( array( 'media_fbid' => $photo['id'] ) );
			}
			$r = itsoc_graph( 'POST', "$page/feed", $params );
			return array( 'remote' => 'https://facebook.com/' . $r['id'] );

		case 'story':
			$photo = itsoc_graph( 'POST', "$page/photos", array( 'url' => itsoc_story( $item )[1], 'published' => 'false' ) );
			$r     = itsoc_graph( 'POST', "$page/photo_stories", array( 'photo_id' => $photo['id'] ) );
			return array( 'remote' => (string) ( $r['post_id'] ?? '' ), 'note' => 'Story (visible 24 hours)' );

		case 'reel':
			$video = itsoc_video( $item );
			$start = itsoc_graph( 'POST', "$page/video_reels", array( 'upload_phase' => 'start' ) );
			itsoc_http( 'POST', 'https://rupload.facebook.com/video-upload/' . itsoc_opt( 'fb_version' ) . '/' . $start['video_id'], array(
				'headers' => array( 'Authorization' => 'OAuth ' . itsoc_opt( 'fb_page_token' ), 'file_url' => $video[1] ),
				'timeout' => 120,
			) );
			itsoc_graph( 'POST', "$page/video_reels", array(
				'upload_phase' => 'finish',
				'video_id'     => $start['video_id'],
				'video_state'  => 'PUBLISHED',
				'description'  => $caption,
			) );
			return array( 'remote' => 'https://facebook.com/reel/' . $start['video_id'] );
	}
	throw new Itsoc_Error( "Facebook does not support “$format”." );
}

/** Instagram publishes in two steps: create a container, wait until it is ready, then publish it. */
function itsoc_send_instagram( $format, array $item, array $state ) {
	$ig = itsoc_opt( 'ig_user_id' );
	if ( empty( $state['container'] ) ) {
		$caption = itsoc_caption( $item, 'instagram', $format );
		switch ( $format ) {
			case 'image':
				$c = itsoc_graph( 'POST', "$ig/media", array( 'image_url' => itsoc_card( $item )[1], 'caption' => $caption ) );
				break;
			case 'story':
				$c = itsoc_graph( 'POST', "$ig/media", array( 'image_url' => itsoc_story( $item )[1], 'media_type' => 'STORIES' ) );
				break;
			case 'carousel':
				$children = array();
				foreach ( array_slice( itsoc_slides( $item ), 0, 10 ) as $s ) {
					$children[] = itsoc_graph( 'POST', "$ig/media", array( 'image_url' => $s[1], 'is_carousel_item' => 'true' ) )['id'];
				}
				$c = itsoc_graph( 'POST', "$ig/media", array( 'media_type' => 'CAROUSEL', 'children' => implode( ',', $children ), 'caption' => $caption ) );
				break;
			case 'reel':
				$c = itsoc_graph( 'POST', "$ig/media", array( 'media_type' => 'REELS', 'video_url' => itsoc_video( $item )[1], 'caption' => $caption, 'share_to_feed' => 'true' ) );
				break;
			default:
				throw new Itsoc_Error( "Instagram does not support “$format”." );
		}
		$state = array( 'container' => $c['id'] );
	}
	$status = itsoc_graph( 'GET', $state['container'], array( 'fields' => 'status_code,status' ) );
	if ( 'IN_PROGRESS' === ( $status['status_code'] ?? '' ) ) {
		return array( 'wait' => $state );
	}
	if ( in_array( $status['status_code'] ?? '', array( 'ERROR', 'EXPIRED' ), true ) ) {
		throw new Itsoc_Error( 'Instagram could not process the media: ' . ( $status['status'] ?? $status['status_code'] ) );
	}
	$r    = itsoc_graph( 'POST', "$ig/media_publish", array( 'creation_id' => $state['container'] ) );
	$link = itsoc_graph( 'GET', $r['id'], array( 'fields' => 'permalink' ) );
	return array( 'remote' => (string) ( $link['permalink'] ?? $r['id'] ), 'note' => 'story' === $format ? 'Story (visible 24 hours)' : '' );
}

function itsoc_test_facebook() {
	$p = itsoc_graph( 'GET', itsoc_opt( 'fb_page_id' ), array( 'fields' => 'name,link' ) );
	return 'Page: ' . $p['name'];
}

function itsoc_test_instagram() {
	$p = itsoc_graph( 'GET', itsoc_opt( 'ig_user_id' ), array( 'fields' => 'username' ) );
	return 'Instagram: @' . $p['username'];
}
