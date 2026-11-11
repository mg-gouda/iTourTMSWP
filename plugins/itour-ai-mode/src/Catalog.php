<?php
/**
 * What the assistant knows, all from the site's own data: tour packages and day trips (with destination,
 * length, from-price, cancellation), the open dates / pick-up hotels / add-ons / price of a chosen day trip,
 * transfer places, vehicles and prices, and the company's contact details and booking rules.
 *
 * @package ITour\AiMode
 */

namespace ITour\AiMode;

use ITour\Core\Locations;
use ITour\Core\Policies;

defined( 'ABSPATH' ) || exit;

/**
 * Site data for the prompt and for booking.
 */
final class Catalog {

	/**
	 * Plain text.
	 *
	 * @param string $text Text or HTML.
	 * @param int    $len  Max length (0 = all).
	 */
	public static function clean( string $text, int $len = 0 ): string {
		$text = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $text ) ), ENT_QUOTES, 'UTF-8' );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		return $len && mb_strlen( $text ) > $len ? rtrim( mb_substr( $text, 0, $len ) ) . '…' : $text;
	}

	/**
	 * Money in the site currency.
	 *
	 * @param float $v Amount.
	 */
	public static function money( float $v ): string {
		return html_entity_decode( wp_strip_all_tags( itour()->currencies()->format( $v ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Tour packages and day trips: id => facts (cached for an hour, cleared when one is saved).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function products(): array {
		$cached = get_transient( 'itour_ai_products' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$types = array_filter( array( 'itour_tour', 'itour_excursion' ), 'post_type_exists' );
		$out   = array();
		if ( ! $types ) {
			return $out;
		}
		global $wpdb;
		$dest = array_column( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM %i WHERE type = 'destination'", $wpdb->prefix . 'itour_locations' ), ARRAY_A ), 'name', 'id' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( get_posts(
			array(
				'post_type'   => $types,
				'post_status' => 'publish',
				'numberposts' => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- the whole catalogue goes into the prompt; cached.
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
			)
		) as $p ) {
			$tour          = 'itour_tour' === $p->post_type;
			$out[ $p->ID ] = array(
				'id'      => $p->ID,
				'kind'    => $tour ? 'tour' : 'excursion',
				'title'   => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
				'url'     => (string) get_permalink( $p ),
				'image'   => (string) get_the_post_thumbnail_url( $p, 'medium_large' ),
				'place'   => (string) ( $dest[ (int) apply_filters( 'itour/destination', 0, $p->ID ) ] ?? '' ),
				'length'  => implode( ', ', array_filter( (array) apply_filters( 'itour/card_facts', array(), $p->ID ) ) ),
				'from'    => (float) get_post_meta( $p->ID, '_itour_price_from', true ),
				'cancel'  => Policies::headline( Policies::of( $p->ID ) ),
				// Tour packages are booked by enquiry on their page; day trips book in the chat.
				'booking' => $tour ? 'enquiry' : 'chat',
				'summary' => self::clean( '' !== $p->post_excerpt ? $p->post_excerpt : $p->post_content, 240 ),
			);
		}
		set_transient( 'itour_ai_products', $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * A chosen day trip: trip types, the next open starts, pick-up, add-ons, and the price for the group.
	 *
	 * @param int                 $post  Excursion id.
	 * @param array<string,mixed> $draft Draft so far.
	 * @return array<string,mixed>
	 */
	public static function excursion( int $post, array $draft ): array {
		if ( ! class_exists( '\ITour\Excursions\Booking' ) ) {
			return array();
		}
		$c        = \ITour\Excursions\Excursion::config( $post );
		$slots    = \ITour\Excursions\Booking::slots( $post );
		$variants = array_intersect_key( \ITour\Excursions\Excursion::variants(), array_flip( array_column( \ITour\Excursions\Excursion::rates( $post ), 'variant' ) ) );
		$hotels   = in_array( 'hotel', (array) $c['pickup_modes'], true ) ? \ITour\Excursions\Booking::hotels( $post ) : array();
		$out      = array(
			'variants' => $variants,
			'starts'   => array(),
			'pickup'   => array(
				'hotels'  => array_values( wp_list_pluck( $hotels, 'label' ) ),
				'typed'   => in_array( 'typed', (array) $c['pickup_modes'], true ),
				'meeting' => in_array( 'meeting', (array) $c['pickup_modes'], true ) ? (string) $c['meeting_point'] : '',
			),
			'addons'   => array_map( static fn( $a ) => $a['name'] . ' (' . self::money( (float) $a['price'] ) . ' ' . ( 'booking' === $a['per'] ? 'per booking' : 'per person' ) . ')', array_values( (array) $c['addons'] ) ),
		);
		foreach ( $slots as $at => $s ) {
			$out['starts'][] = $at . ( null !== $s[1] ? " ({$s[1]} seats left)" : '' );
			if ( count( $out['starts'] ) >= 14 ) {
				break;
			}
		}
		// Price for the current group, from the site's own rates.
		$start = self::slot( $post, $draft );
		if ( '' !== $start && (int) $draft['adults'] > 0 ) {
			$variant = isset( $variants[ (string) $draft['variant'] ] ) ? (string) $draft['variant'] : (string) array_key_first( $variants );
			$zone    = 0;
			foreach ( $hotels as $h ) {
				if ( '' !== (string) $draft['hotel'] && false !== mb_stripos( $h['label'], (string) $draft['hotel'] ) ) {
					$zone = $h['zone'];
					break;
				}
			}
			$price         = \ITour\Excursions\Excursion::price( $post, $variant, substr( $start, 0, 10 ), (int) $draft['adults'], (int) $draft['children'], (int) $draft['infants'], $zone, self::addon_ids( $post, (array) $draft['addons'] ) );
			$out['price']  = $price ? self::money( (float) $price['total'] ) : 'no price for this group size: ask the guest to contact us';
			$out['chosen'] = $start;
		}
		return $out;
	}

	/**
	 * The open start ('Y-m-d H:i') matching the draft's date and time, or ''.
	 *
	 * @param int                 $post  Excursion id.
	 * @param array<string,mixed> $draft Draft.
	 */
	public static function slot( int $post, array $draft ): string {
		if ( empty( $draft['date'] ) || ! class_exists( '\ITour\Excursions\Booking' ) ) {
			return '';
		}
		foreach ( array_keys( \ITour\Excursions\Booking::slots( $post ) ) as $at ) {
			if ( str_starts_with( $at, (string) $draft['date'] ) && ( empty( $draft['time'] ) || substr( $at, 11, 5 ) === $draft['time'] ) ) {
				return $at;
			}
		}
		return '';
	}

	/**
	 * Add-on indexes from names.
	 *
	 * @param int      $post  Excursion id.
	 * @param string[] $names Names.
	 * @return int[]
	 */
	public static function addon_ids( int $post, array $names ): array {
		$out = array();
		foreach ( (array) \ITour\Excursions\Excursion::config( $post )['addons'] as $i => $a ) {
			foreach ( $names as $n ) {
				if ( '' !== (string) $n && false !== mb_stripos( (string) $a['name'], (string) $n ) ) {
					$out[] = (int) $i;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Transfer places by destination ("Hurghada: Hurghada International Airport (airport), Sahl Hasheesh (zone) …")
	 * and the booking rules.
	 */
	public static function transfer_info(): string {
		if ( ! class_exists( '\ITour\Transfers\Module' ) ) {
			return '';
		}
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT l.name, l.type, COALESCE(d.name, d2.name) dest FROM %i l LEFT JOIN %i z ON z.id = l.parent_id LEFT JOIN %i d ON d.id = z.parent_id AND d.type = 'destination' LEFT JOIN %i d2 ON d2.id = l.parent_id AND d2.type = 'destination' WHERE l.active = 1 AND l.type IN ('zone','airport','port','hotel') ORDER BY dest, l.type, l.name LIMIT 600", $wpdb->prefix . 'itour_locations', $wpdb->prefix . 'itour_locations', $wpdb->prefix . 'itour_locations', $wpdb->prefix . 'itour_locations' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$by   = array();
		foreach ( $rows as $r ) {
			$by[ (string) ( $r['dest'] ?? '' ) ][] = $r['name'] . ( 'hotel' === $r['type'] ? '' : ' (' . $r['type'] . ')' );
		}
		$s   = \ITour\Transfers\Settings::get();
		$out = '';
		foreach ( $by as $dest => $names ) {
			$out .= '- ' . ( '' !== $dest ? $dest : 'Other' ) . ': ' . implode( ', ', $names ) . "\n";
		}
		return $out . sprintf(
			"Rules: book at least %d hours before pick-up; flight number %s for airport pick-ups; driver waits %d min at airports, %d min at hotels; private car, price per car not per person%s.\n",
			(int) $s['min_hours'],
			! empty( $s['flight_required'] ) ? 'required' : 'optional',
			(int) $s['wait_airport'],
			(int) $s['wait_hotel'],
			(float) $s['return_discount'] > 0 ? '; return trips ' . (float) $s['return_discount'] . '% off' : ''
		);
	}

	/**
	 * Vehicles and prices for the draft's route (or the problem to tell the guest).
	 *
	 * @param array<string,mixed> $d Draft.
	 * @return array<string,mixed>
	 */
	public static function transfer_quote( array $d ): array {
		if ( ! class_exists( '\ITour\Transfers\Module' ) || empty( $d['from'] ) || empty( $d['to'] ) ) {
			return array();
		}
		$from = Locations::find( (string) $d['from'] );
		$to   = Locations::find( (string) $d['to'] );
		if ( ! $from || ! $to ) {
			return array( 'error' => sprintf( 'Unknown place "%s". Use an exact name from TRANSFER PLACES.', ! $from ? $d['from'] : $d['to'] ) );
		}
		$pax  = max( 1, (int) $d['pax'] );
		$back = 'return' === $d['trip'];
		$list = array();
		foreach ( \ITour\Transfers\Module::quote( Locations::zone_of( $from ), Locations::zone_of( $to ), $pax ) as $q ) {
			$p = \ITour\Transfers\Module::price( (int) $q['vehicle']->id, Locations::zone_of( $from ), Locations::zone_of( $to ), $pax, $back, (int) $d['seats'], (int) $d['bags'] );
			if ( $p ) {
				$list[] = array(
					'id'    => (int) $q['vehicle']->id,
					'name'  => (string) $q['vehicle']->name,
					'seats' => (int) $q['vehicle']->max_pax,
					'bags'  => (int) $q['vehicle']->max_bags,
					'total' => self::money( (float) $p['total'] ),
				);
			}
		}
		return $list ? array(
			'route'    => $from->name . ' → ' . $to->name . ( $back ? ' and back' : '' ),
			'vehicles' => $list,
		) : array( 'error' => 'No vehicle serves this route for this many passengers. Offer to contact us.' );
	}

	/**
	 * Company facts for general questions.
	 */
	public static function site_info(): string {
		$c    = \ITour\Core\Company::get();
		$info = 'Company: ' . html_entity_decode( (string) $c['name'], ENT_QUOTES ) . ( ! empty( $c['licence'] ) ? ' (tourism licence ' . $c['licence'] . ')' : '' ) . "\n";
		foreach ( array(
			'address' => 'Address',
			'phone'   => 'Phone / WhatsApp',
			'email'   => 'Email',
		) as $k => $label ) {
			if ( ! empty( $c[ $k ] ) ) {
				$info .= $label . ': ' . str_replace( "\n", ', ', (string) $c[ $k ] ) . "\n";
			}
		}
		if ( function_exists( 'WC' ) ) {
			$pay = wp_list_pluck( WC()->payment_gateways()->get_available_payment_gateways(), 'title' );
			if ( $pay ) {
				$info .= 'Payment at checkout: ' . implode( ', ', array_map( 'wp_strip_all_tags', $pay ) ) . "\n";
			}
		}
		$contact = get_page_by_path( 'contact' );
		if ( $contact ) {
			$info .= 'Contact page: ' . get_permalink( $contact ) . "\n";
		}
		$info .= 'Prices are in ' . itour()->currencies()->base() . ". The guest enters name, email and phone at checkout.\n";
		return $info;
	}
}
