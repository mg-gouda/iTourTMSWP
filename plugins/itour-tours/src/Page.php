<?php
/**
 * The tour package page: after the owner's text, key facts, the day-by-day itinerary with a route map,
 * prices, extras and the shared info (included, gallery, FAQ). Booking comes next.
 *
 * @package ITour\Tours
 */

namespace ITour\Tours;

use ITour\Core\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Content filter.
 */
final class Page {

	/**
	 * Append the tour details to the post content (main query only).
	 *
	 * @param string $content Post content.
	 */
	public static function append( $content ): string {
		if ( ! is_singular( Tour::TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return (string) $content;
		}
		Products::enqueue_style();
		wp_enqueue_style( 'itour-tours', plugins_url( 'assets/tour.css', ITOUR_TOURS_FILE ), array( 'itour-products' ), '0.3.0' );
		$id  = (int) get_the_ID();
		$c   = Tour::config( $id );
		$fmt = static fn( $v ): string => itour()->currencies()->format( (float) $v );
		return (string) $content . '<section class="itour-tour">' . self::facts( $c ) . self::itinerary( $c ) . self::prices( $id, $fmt ) . self::extras( $c, $fmt ) . Products::render_info( $id ) . '</section>';
	}

	/**
	 * Duration, start days or departures, booking type.
	 *
	 * @param array<string,mixed> $c Settings.
	 */
	private static function facts( array $c ): string {
		$days  = (int) $c['days'];
		$facts = array(
			/* translators: 1: days, 2: nights. */
			__( 'Duration', 'itour-tours' ) => $days > 1 ? sprintf( __( '%1$d days / %2$d nights', 'itour-tours' ), $days, $days - 1 ) : __( '1 day', 'itour-tours' ),
		);
		if ( 'fixed' === $c['dates'] ) {
			$next                                       = array_slice( array_filter( (array) $c['departures'], static fn( $d ) => $d['date'] >= wp_date( 'Y-m-d' ) ), 0, 4 );
			$facts[ __( 'Departures', 'itour-tours' ) ] = $next ? implode( ', ', array_map( static fn( $d ) => wp_date( 'j M Y', strtotime( $d['date'] ) ), $next ) ) : __( 'New dates coming soon', 'itour-tours' );
		} else {
			$start                                  = array_map( 'intval', (array) $c['start_days'] );
			$facts[ __( 'Starts', 'itour-tours' ) ] = 7 === count( $start ) || ! $start ? __( 'Any day', 'itour-tours' ) : implode( ', ', array_map( static fn( $d ) => wp_date( 'l', strtotime( "monday this week +{$d} days -1 day" ) ), $start ) );
		}
		$facts[ __( 'Booking', 'itour-tours' ) ] = 'enquiry' === $c['booking'] ? __( 'On request: we confirm availability and send a quote', 'itour-tours' ) : __( 'Instant confirmation', 'itour-tours' );
		$h                                       = '<dl class="itour-exc__facts">';
		foreach ( $facts as $k => $v ) {
			$h .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
		}
		return $h . '</dl>';
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
		$h     = '<h2 class="itour-exc__title">' . esc_html__( 'Day by day', 'itour-tours' ) . '</h2><ol class="itour-tour__days">';
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
	 * One price table per hotel class and season.
	 *
	 * @param int      $id  Post id.
	 * @param callable $fmt Money formatter.
	 */
	private static function prices( int $id, callable $fmt ): string {
		$groups = array();
		foreach ( Tour::rates( $id ) as $r ) {
			$season = '' !== (string) $r['season_from']
				/* translators: 1: start date, 2: end date. */
				? ' · ' . sprintf( __( '%1$s – %2$s', 'itour-tours' ), wp_date( 'j M', strtotime( $r['season_from'] ) ), wp_date( 'j M Y', strtotime( (string) $r['season_to'] ) ) )
				: '';
			$groups[ Tour::classes()[ (string) $r['hotel_class'] ] . $season ][] = $r;
		}
		if ( ! $groups ) {
			return '';
		}
		$h = '<h2 class="itour-exc__title">' . esc_html__( 'Prices per person', 'itour-tours' ) . '</h2>';
		foreach ( $groups as $label => $rows ) {
			$h .= '<table class="itour-exc__prices"><caption>' . esc_html( $label ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Group size', 'itour-tours' ) . '</th><th scope="col">' . esc_html__( 'Adult, sharing', 'itour-tours' ) . '</th><th scope="col">' . esc_html__( 'Child', 'itour-tours' ) . ' <small>2–11</small></th><th scope="col">' . esc_html__( 'Infant', 'itour-tours' ) . ' <small>0–1</small></th><th scope="col">' . esc_html__( 'Single room', 'itour-tours' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$min  = (int) $r['pax_min'];
				$max  = (int) $r['pax_max'];
				$size = $max ? ( $min === $max ? (string) $min : $min . '–' . $max ) : $min . '+';
				$free = static fn( $v ) => (float) $v ? $fmt( $v ) : __( 'Free', 'itour-tours' );
				/* translators: %s: group size, e.g. "2–3" or "11+". */
				$h .= '<tr><th scope="row">' . esc_html( sprintf( __( '%s travellers', 'itour-tours' ), $size ) ) . '</th><td>' . esc_html( $fmt( $r['adult'] ) ) . '</td><td>' . esc_html( $free( $r['child'] ) ) . '</td><td>' . esc_html( $free( $r['infant'] ) ) . '</td><td>' . esc_html( (float) $r['single_supp'] ? '+' . $fmt( $r['single_supp'] ) : '—' ) . '</td></tr>';
			}
			$h .= '</tbody></table>';
		}
		return $h;
	}

	/**
	 * Optional extras and dated supplements.
	 *
	 * @param array<string,mixed> $c   Settings.
	 * @param callable            $fmt Money formatter.
	 */
	private static function extras( array $c, callable $fmt ): string {
		$items = array();
		foreach ( (array) $c['extras'] as $x ) {
			/* translators: 1: extra, 2: price, 3: per person / per booking. */
			$items[] = sprintf( __( '%1$s: %2$s %3$s', 'itour-tours' ), $x['name'], $fmt( $x['price'] ), 'booking' === $x['per'] ? __( 'per booking', 'itour-tours' ) : __( 'per person', 'itour-tours' ) );
		}
		foreach ( (array) $c['supplements'] as $s ) {
			$when = wp_date( 'j M Y', strtotime( $s['from'] ) ) . ( '' !== $s['to'] && $s['to'] !== $s['from'] ? ' – ' . wp_date( 'j M Y', strtotime( $s['to'] ) ) : '' );
			/* translators: 1: supplement, 2: date(s), 3: adult price. */
			$items[] = sprintf( __( '%1$s (%2$s): %3$s per adult, added automatically', 'itour-tours' ), $s['name'], $when, $fmt( $s['adult'] ) );
		}
		return $items ? '<h2 class="itour-exc__title">' . esc_html__( 'Extras', 'itour-tours' ) . '</h2><ul class="itour-exc__extras"><li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li></ul>' : '';
	}
}
