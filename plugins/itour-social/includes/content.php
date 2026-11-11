<?php
/**
 * What gets posted: one normalised "item" for a blog post, tour, activity or transfer route, and the caption for each channel.
 */

defined( 'ABSPATH' ) || exit;

/** Post types behind each product kind. */
function itsoc_kinds() {
	return array( 'post' => 'post', 'tour' => 'itour_tour', 'activity' => 'itour_excursion' );
}

/**
 * @return array|null title, url, summary, points (list of [heading, text]), image_id, image_ids, price, kind, id, key.
 */
function itsoc_item( $kind, $id ) {
	if ( 'transfer' === $kind ) {
		return itsoc_transfer_item( $id );
	}
	$p = get_post( (int) $id );
	if ( ! $p || 'publish' !== $p->post_status || itsoc_kinds()[ $kind ] !== $p->post_type ) {
		return null;
	}
	$seo     = get_post_meta( $p->ID, '_itour_seo', true ); // iTour SEO description first.
	$summary = ( is_array( $seo ) ? (string) ( $seo['desc'] ?? '' ) : '' ) ?: $p->post_excerpt ?: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 35 );
	$info    = get_post_meta( $p->ID, '_itour_info', true );
	$images  = array_filter( array_merge( array( (int) get_post_thumbnail_id( $p ) ), array_map( 'intval', (array) ( is_array( $info ) ? ( $info['gallery'] ?? array() ) : array() ) ) ) );
	$item    = array(
		'kind'      => $kind,
		'id'        => $p->ID,
		'key'       => $kind . ':' . $p->ID,
		'title'     => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
		'url'       => get_permalink( $p ),
		'summary'   => html_entity_decode( wp_strip_all_tags( $summary ), ENT_QUOTES, 'UTF-8' ),
		'points'    => itsoc_points( $p->post_content ),
		'image_id'  => $images ? reset( $images ) : 0,
		'image_ids' => array_values( array_unique( $images ) ),
		'price'     => '',
	);
	if ( 'post' !== $kind ) {
		$price         = (float) get_post_meta( $p->ID, '_itour_price_from', true );
		$item['price'] = $price > 0 ? 'From ' . html_entity_decode( wp_strip_all_tags( itour()->currencies()->format( $price ) ), ENT_QUOTES, 'UTF-8' ) . ' per person' : '';
		// Slides: the itinerary (Day 1 …), else what is included, else the page's own sections.
		$cfg            = get_post_meta( $p->ID, 'tour' === $kind ? '_itour_tour' : '_itour_excursion', true );
		$cfg            = is_array( $cfg ) ? $cfg : array();
		$program        = array_map( static fn( $d, $i ) => array( 'title' => 'Day ' . ( $i + 1 ) . ': ' . ( $d['title'] ?? '' ), 'desc' => $d['text'] ?? '' ), array_values( (array) ( $cfg['itinerary'] ?? array() ) ), array_keys( array_values( (array) ( $cfg['itinerary'] ?? array() ) ) ) );
		$item['points'] = itsoc_program_points( $program )
			?: itsoc_list_points( (string) ( is_array( $info ) ? ( $info['includes'] ?? '' ) : '' ) )
			?: $item['points'];
		$days = (int) ( $cfg['days'] ?? 0 );
		if ( 'tour' === $kind && $days > 1 && false === stripos( $item['summary'], 'day' ) ) {
			$item['summary'] = $days . ' days · ' . $item['summary'];
		}
	}
	return $item;
}

/** Itinerary rows [title, desc] → points; "<span> 2 Abu Simbel</span>" → "Abu Simbel", "Day 2:Pyramids" → "Day 2: Pyramids". */
function itsoc_program_points( $program ) {
	$out = array();
	foreach ( is_array( $program ) ? $program : array() as $row ) {
		$head = trim( html_entity_decode( wp_strip_all_tags( (string) ( $row['title'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) );
		$head = preg_replace( '/^(Day\s*\d+)\s*[:\-–]\s*/i', '$1: ', $head );
		$head = preg_replace( '/^\d+\s+/', '', $head );
		$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( (string) ( $row['desc'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) ) );
		$text = preg_match( '/^(.{20,170}?[.!?])(\s|$)/u', $text, $s ) ? $s[1] : wp_trim_words( $text, 24, '…' );
		if ( '' !== $head ) {
			$out[] = array( $head, $text );
		}
	}
	return array_slice( $out, 0, 7 );
}

/** Sections of a blog post: each h2/h3 with the first sentence that follows it. */
function itsoc_points( $html ) {
	$out = array();
	if ( preg_match_all( '#<h[23][^>]*>(.*?)</h[23]>(.*?)(?=<h[23]|$)#is', (string) $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $row ) {
			$head = trim( html_entity_decode( wp_strip_all_tags( $row[1] ), ENT_QUOTES, 'UTF-8' ) );
			$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $row[2] ), ENT_QUOTES, 'UTF-8' ) ) );
			$text = preg_match( '/^(.{20,180}?[.!?])(\s|$)/u', $text, $s ) ? $s[1] : wp_trim_words( $text, 22, '…' );
			// Generic sections ("FAQ", "Plan your trip") make poor slides.
			if ( '' !== $head && ! preg_match( '/frequently asked|faq|questions|plan your trip|who this .* suits|contact us|book (now|your)|^prices?$/i', $head ) ) {
				$out[] = array( $head, $text );
			}
		}
	}
	return array_slice( $out, 0, 7 );
}

/** Highlights/includes lists (one per line or <li>) as points. */
function itsoc_list_points( $text ) {
	$text  = preg_replace( '#</li>|<br\s*/?>#i', "\n", $text );
	$lines = array_filter( array_map( 'trim', explode( "\n", html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) ) );
	$out   = array();
	foreach ( array_slice( array_values( $lines ), 0, 6 ) as $l ) {
		$out[] = array( wp_trim_words( ltrim( $l, "•-*\t " ), 9, '…' ), '' );
	}
	return $out;
}

/** Transfer "products" are routes: airport (or zone) → zone, cheapest car price. Key: transfer:{from zone}-{to zone}. */
function itsoc_transfer_routes() {
	global $wpdb;
	$p = $wpdb->prefix;
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $p . 'itour_transfer_rates' ) ) ) {
		return array();
	}
	$rows = $wpdb->get_results(
		"SELECT r.from_zone, r.to_zone, MIN(r.price) price,
			COALESCE((SELECT a.name FROM {$p}itour_locations a WHERE a.parent_id = r.from_zone AND a.type = 'airport' AND a.active = 1 ORDER BY a.id LIMIT 1), f.name) from_name,
			t.name to_name
		FROM {$p}itour_transfer_rates r
		JOIN {$p}itour_transfer_vehicles v ON v.id = r.vehicle_id AND v.active = 1
		JOIN {$p}itour_locations f ON f.id = r.from_zone AND f.active = 1
		JOIN {$p}itour_locations t ON t.id = r.to_zone AND t.active = 1
		WHERE r.price > 0
		GROUP BY r.from_zone, r.to_zone",
		ARRAY_A
	);
	$out = array();
	foreach ( $rows as $r ) {
		$out[ $r['from_zone'] . '-' . $r['to_zone'] ] = $r;
	}
	return $out;
}

function itsoc_transfer_item( $route ) {
	global $wpdb;
	$routes = itsoc_transfer_routes();
	if ( ! isset( $routes[ $route ] ) ) {
		return null;
	}
	$r       = $routes[ $route ];
	$vehicle = $wpdb->get_row( $wpdb->prepare( "SELECT v.name, v.max_pax FROM {$wpdb->prefix}itour_transfer_rates r JOIN {$wpdb->prefix}itour_transfer_vehicles v ON v.id = r.vehicle_id WHERE r.from_zone = %d AND r.to_zone = %d AND r.price > 0 AND v.active = 1 ORDER BY r.price ASC LIMIT 1", $r['from_zone'], $r['to_zone'] ), ARRAY_A );
	$page    = class_exists( '\\ITour\\Transfers\\Module' ) ? \ITour\Transfers\Module::page_url( 'results_page' ) : '';
	$url     = add_query_arg( array( 'from' => rawurlencode( $r['from_name'] ), 'to' => rawurlencode( $r['to_name'] ), 'pax' => 2 ), '' !== $page ? $page : home_url( '/' ) );
	$rules   = class_exists( '\\ITour\\Transfers\\Settings' ) ? \ITour\Transfers\Settings::get() : array();
	$cancel  = class_exists( '\\ITour\\Transfers\\Settings' ) ? \ITour\Core\Policies::headline( \ITour\Transfers\Settings::cancel_tiers( 0 ) ) : '';
	$to      = $r['to_name'];
	$from    = trim( preg_replace( '/\s*International\s*/i', ' ', $r['from_name'] ) );
	$points  = array( array( 'Private car, fixed price per car', '' ) );
	if ( ! empty( $rules['wait_airport'] ) ) {
		$points[] = array( 'Free waiting: ' . (int) $rules['wait_airport'] . ' minutes at the airport', '' );
	}
	if ( '' !== $cancel ) {
		$points[] = array( $cancel, '' );
	}
	return array(
		'kind'      => 'transfer',
		'id'        => $route,
		'key'       => 'transfer:' . $route,
		'title'     => $from . ' → ' . $to . ' private transfer',
		'url'       => $url,
		'summary'   => 'Private transfer from ' . $r['from_name'] . ' to ' . $to . '. Fixed price per car, not per person' . ( '' !== $cancel ? '; ' . lcfirst( $cancel ) : '' ) . '.',
		'points'    => $points,
		'image_id'  => 0,
		'image_ids' => array(),
		'price'     => 'From ' . html_entity_decode( wp_strip_all_tags( itour()->currencies()->format( (float) $r['price'] ) ), ENT_QUOTES, 'UTF-8' ) . ' per car' . ( $vehicle ? ' (' . $vehicle['name'] . ', up to ' . (int) $vehicle['max_pax'] . ')' : '' ),
	);
}

/** Every product key that can be posted: tour:ID, activity:ID, transfer:FROM-TO. */
function itsoc_product_keys() {
	$keys = array();
	foreach ( array( 'tour', 'activity' ) as $kind ) {
		foreach ( get_posts( array( 'post_type' => itsoc_kinds()[ $kind ], 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) ) as $id ) {
			$keys[] = $kind . ':' . $id;
		}
	}
	foreach ( array_keys( itsoc_transfer_routes() ) as $r ) {
		$keys[] = 'transfer:' . $r;
	}
	return $keys;
}

/** Link with campaign tags so visits show up per channel in Google Analytics. */
function itsoc_link( array $item, $channel ) {
	if ( ! itsoc_opt( 'utm' ) ) {
		return $item['url'];
	}
	return add_query_arg( array(
		'utm_source'   => $channel,
		'utm_medium'   => 'social',
		'utm_campaign' => 'post' === $item['kind'] ? 'blog' : 'daily_' . $item['kind'],
	), $item['url'] );
}


/** "Tour: Cairo & Nile Cruise …" for a key like tour:123. */
function itsoc_key_label( $key ) {
	if ( ! $key ) {
		return '—';
	}
	list( $kind, $id ) = explode( ':', $key, 2 );
	$item              = itsoc_item( $kind, $id );
	return $item ? ucfirst( $kind ) . ': ' . $item['title'] : $key;
}
