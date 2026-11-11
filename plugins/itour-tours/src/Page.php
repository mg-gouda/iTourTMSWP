<?php
/**
 * The tour package page as parts for the chosen layout (core Layouts): facts, day by day with the route
 * map, prices, extras, cancellation, the shared info and an enquiry button (online booking comes next).
 *
 * @package ITour\Tours
 */

namespace ITour\Tours;

use ITour\Core\Layouts;
use ITour\Core\Policies;

defined( 'ABSPATH' ) || exit;

/**
 * Parts builder.
 */
final class Page {

	/**
	 * Parts of one tour page.
	 *
	 * @param int $id Post id.
	 * @return array<string,mixed>
	 */
	public static function parts( int $id ): array {
		wp_enqueue_style( 'itour-tours', plugins_url( 'assets/tour.css', ITOUR_TOURS_FILE ), array( 'itour-products' ), '0.4.0' );
		$c        = Tour::config( $id );
		$info     = Layouts::info_parts( $id );
		$sections = array();
		foreach ( array(
			'itinerary' => array( __( 'Day by day', 'itour-tours' ), self::itinerary( $c ) ),
			'extras'    => array( __( 'Extras', 'itour-tours' ), self::extras( $c ) ),
		) as $k => $s ) {
			if ( '' !== $s[1] ) {
				$sections[ $k ] = $s;
			}
		}
		$tiers = Policies::of( $id );
		if ( $tiers ) {
			$sections['cancellation'] = array( __( 'Cancellation', 'itour-tours' ), Policies::html( $tiers, false ) );
		}
		$from    = Tour::from_price( $id );
		$contact = get_page_by_path( 'contact' );
		$ask     = add_query_arg( 'subject', rawurlencode( get_the_title( $id ) ), $contact ? (string) get_permalink( $contact ) : home_url( '/' ) );
		// ponytail: enquiry button until online booking for tours is built (same form as the contact page).
		$cta = '<div class="itl-cta"><p>' . esc_html( 'enquiry' === $c['booking'] ? __( 'Tell us your dates and group: we check availability and send a quote within one working day.', 'itour-tours' ) : __( 'Send us your dates and group size and we will confirm availability.', 'itour-tours' ) ) . '</p><a class="wp-element-button" href="' . esc_url( $ask ) . '">' . esc_html__( 'Ask for this tour', 'itour-tours' ) . '</a></div>';
		return array(
			'images'   => $info['images'],
			'facts'    => self::facts( $c ) + $info['facts'],
			'price'    => $from ? array( 'amount' => \ITour\Core\Cards::money( $from ) ) : null, // Lowest price; no per-person rates on the page.
			'content'  => Layouts::content( $id ),
			'sections' => $sections + $info['sections'],
			'book'     => array(
				'title' => __( 'Plan this tour', 'itour-tours' ),
				'html'  => $cta,
			),
			'notice'   => Policies::headline( $tiers ),
		);
	}

	/**
	 * Duration, start days or departures, booking type.
	 *
	 * @param array<string,mixed> $c Settings.
	 * @return array<string,string>
	 */
	private static function facts( array $c ): array {
		$days  = (int) $c['days'];
		$facts = array(
			/* translators: 1: days, 2: nights. */
			__( 'Duration', 'itour-tours' ) => $days > 1 ? sprintf( __( '%1$d days / %2$d nights', 'itour-tours' ), $days, $days - 1 ) : __( '1 day', 'itour-tours' ),
		);
		if ( 'fixed' === $c['dates'] ) {
			$next = array_slice( array_filter( (array) $c['departures'], static fn( $d ) => $d['date'] >= wp_date( 'Y-m-d' ) ), 0, 3 );

			$facts[ __( 'Departures', 'itour-tours' ) ] = $next ? implode( ', ', array_map( static fn( $d ) => wp_date( 'j M', strtotime( $d['date'] ) ), $next ) ) : __( 'New dates coming soon', 'itour-tours' );
		} else {
			$start = array_map( 'intval', (array) $c['start_days'] );

			$facts[ __( 'Starts', 'itour-tours' ) ] = 7 === count( $start ) || ! $start ? __( 'Any day', 'itour-tours' ) : implode( ', ', array_map( static fn( $d ) => wp_date( 'D', strtotime( "monday this week +{$d} days -1 day" ) ), $start ) );
		}
		$facts[ __( 'Booking', 'itour-tours' ) ] = 'enquiry' === $c['booking'] ? __( 'On request', 'itour-tours' ) : __( 'Instant confirmation', 'itour-tours' );
		return $facts;
	}

	/**
	 * Day by day, then the route map.
	 *
	 * @param array<string,mixed> $c Settings.
	 */
	private static function itinerary( array $c ): string {
		if ( ! $c['itinerary'] ) {
			return '';
		}
		global $wpdb;
		$meals = Tour::meals();
		$h     = '<ol class="itour-tour__days">';
		$stops = array();
		foreach ( (array) $c['itinerary'] as $i => $d ) {
			/* translators: %d: day number. */
			$h   .= '<li><p class="itour-tour__day">' . esc_html( sprintf( __( 'Day %d', 'itour-tours' ), $i + 1 ) ) . '</p><h3>' . esc_html( $d['title'] ) . '</h3>';
			$meta = array_filter( array( $d['places'], '' !== $d['meals'] ? $meals[ $d['meals'] ] ?? '' : '', '' !== $d['overnight'] ? sprintf( /* translators: %s: overnight place. */ __( 'Overnight: %s', 'itour-tours' ), $d['overnight'] ) : '' ) );
			if ( $meta ) {
				$h .= '<p class="itour-tour__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
			}
			if ( '' !== $d['text'] ) {
				$h .= '<p>' . nl2br( esc_html( $d['text'] ) ) . '</p>';
			}
			$h .= '</li>';
			if ( '' !== (string) $d['place'] ) {
				$loc = $wpdb->get_row( $wpdb->prepare( 'SELECT lat, lng FROM %i WHERE id = %d AND lat IS NOT NULL AND lng IS NOT NULL', $wpdb->prefix . 'itour_locations', $d['place'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( $loc ) {
					$point = (float) $loc->lat . ',' . (float) $loc->lng;
					if ( end( $stops ) !== $point ) {
						$stops[] = $point;
					}
				}
			}
		}
		$h .= '</ol>';
		if ( $stops ) {
			// ponytail: Google's key-free embed (route through the itinerary places); a self-hosted map can replace it.
			$src = 1 === count( $stops )
				? 'https://maps.google.com/maps?q=' . rawurlencode( $stops[0] ) . '&z=10&output=embed'
				: 'https://maps.google.com/maps?saddr=' . rawurlencode( $stops[0] ) . '&daddr=' . implode( '+to:', array_map( 'rawurlencode', array_slice( $stops, 1 ) ) ) . '&output=embed';
			$h  .= '<iframe class="itour-tour__map" title="' . esc_attr__( 'Route map', 'itour-tours' ) . '" src="' . esc_url( $src ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
		}
		return $h;
	}

	/**
	 * Optional extras and dated supplements.
	 *
	 * @param array<string,mixed> $c   Settings.
	 */
	private static function extras( array $c ): string {
		$items = array();
		foreach ( (array) $c['extras'] as $x ) {
			/* translators: %s: extra. */
			$items[] = sprintf( __( '%s (optional, ask us to add it)', 'itour-tours' ), $x['name'] );
		}
		foreach ( (array) $c['supplements'] as $s ) {
			$when = wp_date( 'j M Y', strtotime( $s['from'] ) ) . ( '' !== $s['to'] && $s['to'] !== $s['from'] ? ' – ' . wp_date( 'j M Y', strtotime( $s['to'] ) ) : '' );
			/* translators: 1: supplement, 2: date(s). */
			$items[] = sprintf( __( '%1$s (%2$s): included in the price for these dates', 'itour-tours' ), $s['name'], $when );
		}
		return $items ? '<ul class="itour-exc__extras"><li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li></ul>' : '';
	}
}
