<?php
/**
 * The excursion page: after the owner's text, the key facts and the price table. Booking comes next.
 *
 * @package ITour\Excursions
 */

namespace ITour\Excursions;

use ITour\Core\Locations;

defined( 'ABSPATH' ) || exit;

/**
 * Content filter.
 */
final class Page {

	/**
	 * Append facts + prices to the excursion's own content (main query only).
	 *
	 * @param string $content Post content.
	 */
	public static function append( $content ): string {
		if ( ! is_singular( Excursion::TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return (string) $content;
		}
		wp_enqueue_style( 'itour-excursions', plugins_url( 'assets/excursion.css', ITOUR_EXCURSIONS_FILE ), array(), '0.2.0' );
		$id  = (int) get_the_ID();
		$c   = Excursion::config( $id );
		$fmt = static fn( $v ): string => itour()->currencies()->format( (float) $v );

		// Facts.
		$days  = array_map( 'intval', (array) $c['days'] );
		$facts = array();
		if ( '' !== $c['duration'] ) {
			$facts[ __( 'Duration', 'itour-excursions' ) ] = $c['duration'];
		}
		$facts[ __( 'Runs', 'itour-excursions' ) ] = 7 === count( $days ) ? __( 'Every day', 'itour-excursions' ) : implode( ', ', array_map( static fn( $d ) => wp_date( 'l', strtotime( "monday this week +{$d} days -1 day" ) ), $days ) );
		if ( $c['times'] ) {
			$facts[ __( 'Starts', 'itour-excursions' ) ] = implode( ', ', array_map( static fn( $t ) => wp_date( get_option( 'time_format' ), strtotime( 'today ' . $t ) ), (array) $c['times'] ) );
		}
		$pick = array();
		if ( in_array( 'hotel', (array) $c['pickup_modes'], true ) || in_array( 'typed', (array) $c['pickup_modes'], true ) ) {
			$pick[] = __( 'Pick-up from your hotel', 'itour-excursions' );
		}
		if ( in_array( 'meeting', (array) $c['pickup_modes'], true ) && '' !== $c['meeting_point'] ) {
			/* translators: %s: meeting point. */
			$pick[] = sprintf( __( 'or meet us at %s', 'itour-excursions' ), $c['meeting_point'] );
		}
		if ( $pick ) {
			$facts[ __( 'Pick-up', 'itour-excursions' ) ] = implode( ' ', $pick );
		}
		$h = '<section class="itour-exc" aria-label="' . esc_attr__( 'Trip details', 'itour-excursions' ) . '"><dl class="itour-exc__facts">';
		foreach ( $facts as $k => $v ) {
			$h .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
		}
		$h .= '</dl>';

		// Prices: one table per type and season.
		$groups = array();
		foreach ( Excursion::rates( $id ) as $r ) {
			$season = '' !== (string) $r['season_from']
				/* translators: 1: start date, 2: end date. */
				? sprintf( __( '%1$s – %2$s', 'itour-excursions' ), wp_date( 'j M', strtotime( $r['season_from'] ) ), wp_date( 'j M Y', strtotime( (string) $r['season_to'] ) ) )
				: '';
			$groups[ Excursion::variants()[ $r['variant'] ] . ( '' !== $season ? ' · ' . $season : '' ) ][] = $r;
		}
		if ( $groups ) {
			$h .= '<h2 class="itour-exc__title">' . esc_html__( 'Prices per person', 'itour-excursions' ) . '</h2>';
			foreach ( $groups as $label => $rows ) {
				$h .= '<table class="itour-exc__prices"><caption>' . esc_html( $label ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Group size', 'itour-excursions' ) . '</th><th scope="col">' . esc_html__( 'Adult', 'itour-excursions' ) . '</th><th scope="col">' . esc_html__( 'Child', 'itour-excursions' ) . ' <small>2–11</small></th><th scope="col">' . esc_html__( 'Infant', 'itour-excursions' ) . ' <small>0–1</small></th></tr></thead><tbody>';
				foreach ( $rows as $r ) {
					$min  = (int) $r['pax_min'];
					$max  = (int) $r['pax_max'];
					$size = $max ? ( $min === $max ? (string) $min : $min . '–' . $max ) : $min . '+';
					/* translators: %s: group size, e.g. "2–3" or "11+". */
					$h .= '<tr><th scope="row">' . esc_html( sprintf( __( '%s travellers', 'itour-excursions' ), $size ) ) . '</th><td>' . esc_html( $fmt( $r['adult'] ) ) . '</td><td>' . esc_html( (float) $r['child'] ? $fmt( $r['child'] ) : __( 'Free', 'itour-excursions' ) ) . '</td><td>' . esc_html( (float) $r['infant'] ? $fmt( $r['infant'] ) : __( 'Free', 'itour-excursions' ) ) . '</td></tr>';
				}
				$h .= '</tbody></table>';
			}
		}

		// Extras.
		$extra = array();
		foreach ( (array) $c['addons'] as $a ) {
			/* translators: 1: add-on name, 2: price, 3: "per person" or "per booking". */
			$extra[] = sprintf( __( '%1$s: %2$s %3$s', 'itour-excursions' ), $a['name'], $fmt( $a['price'] ), 'booking' === $a['per'] ? __( 'per booking', 'itour-excursions' ) : __( 'per person', 'itour-excursions' ) );
		}
		foreach ( (array) $c['supplements'] as $s ) {
			$when = wp_date( 'j M Y', strtotime( $s['from'] ) ) . ( '' !== $s['to'] && $s['to'] !== $s['from'] ? ' – ' . wp_date( 'j M Y', strtotime( $s['to'] ) ) : '' );
			/* translators: 1: supplement name, 2: date(s), 3: adult price. */
			$extra[] = sprintf( __( '%1$s (%2$s): %3$s per adult, added automatically', 'itour-excursions' ), $s['name'], $when, $fmt( $s['adult'] ) );
		}
		$zones = Locations::zone_options();
		foreach ( Excursion::pickups( $id ) as $p ) {
			if ( (float) $p['extra'] > 0 && isset( $zones[ $p['zone_id'] ] ) ) {
				/* translators: 1: zone, 2: price. */
				$extra[] = sprintf( __( 'Pick-up from %1$s: %2$s per person', 'itour-excursions' ), $zones[ $p['zone_id'] ], $fmt( $p['extra'] ) );
			}
		}
		if ( $extra ) {
			$h .= '<h2 class="itour-exc__title">' . esc_html__( 'Extras', 'itour-excursions' ) . '</h2><ul class="itour-exc__extras"><li>' . implode( '</li><li>', array_map( 'esc_html', $extra ) ) . '</li></ul>';
		}
		return $content . $h . '</section>';
	}
}
