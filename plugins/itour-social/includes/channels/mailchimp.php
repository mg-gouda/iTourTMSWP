<?php
/**
 * Email to the Mailchimp audience (the same Mailchimp account as the “Mailchimp for WP” sign-up forms).
 */

defined( 'ABSPATH' ) || exit;

function itsoc_mc( $method, $path, ?array $json = null ) {
	$key = itsoc_opt( 'mc_api_key' );
	$dc  = substr( strrchr( $key, '-' ), 1 );
	if ( ! $dc ) {
		throw new Itsoc_Error( 'The Mailchimp API key looks wrong (it should end in -us1, -us21 …).' );
	}
	$o = array( 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( 'itour:' . $key ) ) );
	if ( null !== $json ) {
		$o['json'] = $json;
	}
	return itsoc_http( $method, "https://$dc.api.mailchimp.com/3.0/" . ltrim( $path, '/' ), $o )['json'];
}

/** [ list_id => 'Name (members)' ] */
function itsoc_mc_lists() {
	$out = array();
	foreach ( itsoc_mc( 'GET', 'lists?count=50&fields=lists.id,lists.name,lists.stats.member_count' )['lists'] ?? array() as $l ) {
		$out[ $l['id'] ] = $l['name'] . ' (' . (int) $l['stats']['member_count'] . ' subscribers)';
	}
	return $out;
}

function itsoc_email_html( array $item ) {
	$link    = add_query_arg( array( 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'post' === $item['kind'] ? 'blog' : 'daily_' . $item['kind'] ), $item['url'] );
	$image   = $item['image_id'] ? wp_get_attachment_image_url( $item['image_id'], 'large' ) : itsoc_card( $item )[1];
	$button  = array( 'post' => 'Read the full guide', 'tour' => 'See dates & book', 'activity' => 'Book this trip', 'transfer' => 'Book your transfer' )[ $item['kind'] ];
	$points  = '';
	foreach ( array_slice( $item['points'], 0, 6 ) as $p ) {
		$points .= '<li style="margin:0 0 8px">' . esc_html( $p[0] ) . '</li>';
	}
	$o = vsprintf( '#%02x%02x%02x', itsoc_rgb( 'brand' ) );
	$bg = vsprintf( '#%02x%02x%02x', itsoc_rgb( 'brand-dark' ) );
	$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	return '<!doctype html><html><body style="margin:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#222">'
		. '<span style="display:none;max-height:0;overflow:hidden">' . esc_html( wp_trim_words( $item['summary'], 20 ) ) . '</span>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:8px;overflow:hidden">'
		. '<tr><td style="background:' . $bg . ';padding:18px 24px;color:#fff;font-size:20px;font-weight:bold">' . esc_html( $name ) . '</td></tr>'
		. '<tr><td><a href="' . esc_url( $link ) . '"><img src="' . esc_url( $image ) . '" width="600" alt="' . esc_attr( $item['title'] ) . '" style="display:block;width:100%;height:auto;border:0"></a></td></tr>'
		. '<tr><td style="padding:24px">'
		. '<h1 style="margin:0 0 12px;font-size:24px;line-height:1.3">' . esc_html( $item['title'] ) . '</h1>'
		. ( $item['price'] ? '<p style="margin:0 0 12px;color:' . $o . ';font-weight:bold">' . esc_html( $item['price'] ) . '</p>' : '' )
		. '<p style="margin:0 0 16px;font-size:16px;line-height:1.6">' . esc_html( $item['summary'] ) . '</p>'
		. ( $points ? '<ul style="margin:0 0 20px;padding-left:20px;font-size:15px;line-height:1.5">' . $points . '</ul>' : '' )
		. '<a href="' . esc_url( $link ) . '" style="display:inline-block;background:' . $o . ';color:#fff;text-decoration:none;font-weight:bold;padding:14px 26px;border-radius:6px">' . esc_html( $button ) . '</a>'
		. '</td></tr>'
		. '<tr><td style="padding:16px 24px;background:#fafafa;font-size:12px;color:#777;line-height:1.5">Tours, day trips and airport transfers · <a href="' . esc_url( home_url( '/' ) ) . '" style="color:#777">' . esc_html( itsoc_domain() ) . '</a><br>'
		. 'You are receiving this because you subscribed on ' . esc_html( itsoc_domain() ) . '. <a href="*|UNSUB|*" style="color:#777">Unsubscribe</a><br>*|LIST:ADDRESSLINE|*</td></tr>'
		. '</table></td></tr></table></body></html>';
}

function itsoc_send_mailchimp( $format, array $item, array $state ) {
	$list = itsoc_opt( 'mc_list' );
	$def  = itsoc_mc( 'GET', "lists/$list?fields=campaign_defaults,name" )['campaign_defaults'] ?? array();
	$c    = itsoc_mc( 'POST', 'campaigns', array(
		'type'       => 'regular',
		'recipients' => array( 'list_id' => $list ),
		'settings'   => array(
			'subject_line' => itsoc_email_subject( $item ),
			'preview_text' => wp_trim_words( $item['summary'], 18 ),
			'title'        => 'Social Auto Pilot – ' . mb_substr( $item['title'], 0, 80 ),
			'from_name'    => itsoc_opt( 'mc_from_name' ) ?: ( $def['from_name'] ?? get_bloginfo( 'name' ) ),
			'reply_to'     => itsoc_opt( 'mc_reply_to' ) ?: ( $def['from_email'] ?? get_option( 'admin_email' ) ),
		),
	) );
	itsoc_mc( 'PUT', 'campaigns/' . $c['id'] . '/content', array( 'html' => itsoc_email_html( $item ) ) );
	itsoc_mc( 'POST', 'campaigns/' . $c['id'] . '/actions/send' );
	return array( 'remote' => (string) ( $c['archive_url'] ?? $c['id'] ) );
}

function itsoc_test_mailchimp() {
	$a = itsoc_mc( 'GET', '?fields=account_name' );
	return 'Mailchimp account: ' . ( $a['account_name'] ?? '?' );
}
