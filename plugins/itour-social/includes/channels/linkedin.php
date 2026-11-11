<?php
/**
 * LinkedIn company Page through the Posts API (link posts with a preview card, and multi-image posts for carousels).
 */

defined( 'ABSPATH' ) || exit;

/** A token from "Connect LinkedIn"; refreshed automatically when LinkedIn gave a refresh token. */
function itsoc_li_token() {
	$exp = (int) itsoc_opt( 'li_token_expires' );
	if ( $exp && $exp < time() + DAY_IN_SECONDS && itsoc_opt( 'li_refresh' ) ) {
		$r = itsoc_http( 'POST', 'https://www.linkedin.com/oauth/v2/accessToken', array( 'form' => array(
			'grant_type'    => 'refresh_token',
			'refresh_token' => itsoc_opt( 'li_refresh' ),
			'client_id'     => itsoc_opt( 'li_client_id' ),
			'client_secret' => itsoc_opt( 'li_client_secret' ),
		) ) )['json'];
		itsoc_save( array( 'li_token' => $r['access_token'], 'li_token_expires' => time() + (int) $r['expires_in'], 'li_refresh' => $r['refresh_token'] ?? '' ) );
	} elseif ( $exp && $exp < time() ) {
		throw new Itsoc_Error( 'The LinkedIn connection expired – click “Connect LinkedIn” again.' );
	}
	return itsoc_opt( 'li_token' );
}

function itsoc_li( $method, $path, ?array $json = null ) {
	$o = array( 'headers' => array(
		'Authorization'             => 'Bearer ' . itsoc_li_token(),
		'LinkedIn-Version'          => gmdate( 'Ym', strtotime( '-2 months' ) ),
		'X-Restli-Protocol-Version' => '2.0.0',
	) );
	if ( null !== $json ) {
		$o['json'] = $json;
	}
	return itsoc_http( $method, 'https://api.linkedin.com/rest/' . $path, $o );
}

/** LinkedIn's "little text" format: reserved characters escaped, hashtags as hashtag elements. */
function itsoc_li_text( $text ) {
	$text = preg_replace( '/([\\\\()\[\]{}<>@|~_*#])/u', '\\\\$1', $text );
	return preg_replace( '/\\\\#(\w+)/u', '{hashtag|\\\\#|$1}', $text );
}

function itsoc_li_upload( $file ) {
	$org  = 'urn:li:organization:' . itsoc_opt( 'li_org_id' );
	$init = itsoc_li( 'POST', 'images?action=initializeUpload', array( 'initializeUploadRequest' => array( 'owner' => $org ) ) )['json']['value'];
	itsoc_http( 'PUT', $init['uploadUrl'], array(
		'headers' => array( 'Authorization' => 'Bearer ' . itsoc_li_token(), 'Content-Type' => 'image/jpeg' ),
		'body'    => file_get_contents( $file ),
		'timeout' => 90,
	) );
	return $init['image'];
}

function itsoc_send_linkedin( $format, array $item, array $state ) {
	$post = array(
		'author'                    => 'urn:li:organization:' . itsoc_opt( 'li_org_id' ),
		'commentary'                => itsoc_li_text( itsoc_caption( $item, 'linkedin', $format ) ),
		'visibility'                => 'PUBLIC',
		'distribution'              => array( 'feedDistribution' => 'MAIN_FEED', 'targetEntities' => array(), 'thirdPartyDistributionChannels' => array() ),
		'lifecycleState'            => 'PUBLISHED',
		'isReshareDisabledByAuthor' => false,
	);
	if ( 'link' === $format ) {
		$post['content'] = array( 'article' => array(
			'source'      => itsoc_link( $item, 'linkedin' ),
			'title'       => mb_substr( $item['title'], 0, 200 ),
			'description' => mb_substr( $item['summary'], 0, 250 ),
			'thumbnail'   => itsoc_li_upload( itsoc_card( $item )[0] ),
		) );
	} elseif ( 'carousel' === $format ) {
		$images = array();
		foreach ( array_slice( itsoc_slides( $item ), 0, 9 ) as $i => $s ) {
			$images[] = array( 'id' => itsoc_li_upload( $s[0] ), 'altText' => 0 === $i ? mb_substr( $item['title'], 0, 120 ) : 'Slide ' . ( $i + 1 ) );
		}
		$post['content'] = array( 'multiImage' => array( 'images' => $images ) );
	} else {
		throw new Itsoc_Error( "LinkedIn does not support “$format”." );
	}
	$r  = itsoc_li( 'POST', 'posts', $post );
	$id = $r['headers']['x-restli-id'] ?? '';
	return array( 'remote' => $id ? 'https://www.linkedin.com/feed/update/' . $id : '' );
}

function itsoc_test_linkedin() {
	$o = itsoc_li( 'GET', 'organizations/' . itsoc_opt( 'li_org_id' ) )['json'];
	return 'LinkedIn Page: ' . ( $o['localizedName'] ?? itsoc_opt( 'li_org_id' ) );
}
