<?php
/**
 * The excursion page as parts for the chosen layout (core Layouts): facts, prices, extras, cancellation,
 * the shared info and the booking form.
 *
 * @package ITour\Excursions
 */

namespace ITour\Excursions;

use ITour\Core\Layouts;
use ITour\Core\Locations;
use ITour\Core\Policies;

defined( 'ABSPATH' ) || exit;

/**
 * Parts builder.
 */
final class Page {

	/**
	 * Parts of one excursion page.
	 *
	 * @param int $id Post id.
	 * @return array<string,mixed>
	 */
	public static function parts( int $id ): array {
		wp_enqueue_style( 'itour-excursions', plugins_url( 'assets/excursion.css', ITOUR_EXCURSIONS_FILE ), array( 'itour-products' ), '0.4.0' );
		$c    = Excursion::config( $id );
		$fmt  = static fn( $v ): string => itour()->currencies()->format( (float) $v );
		$info = Layouts::info_parts( $id );

		// Facts.
		$days  = array_map( 'intval', (array) $c['days'] );
		$facts = array();
		if ( '' !== $c['duration'] ) {
			$facts[ __( 'Duration', 'itour-excursions' ) ] = $c['duration'];
		}
		$facts[ __( 'Runs', 'itour-excursions' ) ] = 7 === count( $days ) ? __( 'Every day', 'itour-excursions' ) : implode( ', ', array_map( static fn( $d ) => wp_date( 'D', strtotime( "monday this week +{$d} days -1 day" ) ), $days ) );
		if ( $c['times'] ) {
			$facts[ __( 'Starts', 'itour-excursions' ) ] = implode( ', ', array_map( static fn( $t ) => wp_date( get_option( 'time_format' ), strtotime( 'today ' . $t ) ), (array) $c['times'] ) );
		}
		$modes = (array) $c['pickup_modes'];
		if ( array_intersect( array( 'hotel', 'typed' ), $modes ) ) {
			$facts[ __( 'Pick-up', 'itour-excursions' ) ] = __( 'From your hotel', 'itour-excursions' );
		} elseif ( in_array( 'meeting', $modes, true ) && '' !== $c['meeting_point'] ) {
			$facts[ __( 'Meeting point', 'itour-excursions' ) ] = $c['meeting_point'];
		}
		$facts += $info['facts'];

		$sections = array();
		$prices   = self::prices( $id, $fmt );
		if ( '' !== $prices ) {
			$sections['prices'] = array( __( 'Prices per person', 'itour-excursions' ), $prices );
		}
		$extras = self::extras( $id, $c, $fmt );
		if ( '' !== $extras ) {
			$sections['extras'] = array( __( 'Extras', 'itour-excursions' ), $extras );
		}
		$tiers = Policies::of( $id );
		if ( $tiers ) {
			$sections['cancellation'] = array( __( 'Cancellation', 'itour-excursions' ), Policies::html( $tiers, false ) );
		}
		$from = Excursion::from_price( $id );
		$form = Booking::form( $id );
		return array(
			'images'   => $info['images'],
			'facts'    => $facts,
			'price'    => $from ? array(
				'amount' => $fmt( $from ),
				'note'   => __( 'per adult', 'itour-excursions' ),
			) : null,
			'content'  => Layouts::content( $id ),
			'sections' => $sections + $info['sections'],
			'book'     => '' !== $form ? array(
				'title' => __( 'Book this trip', 'itour-excursions' ),
				'html'  => $form,
			) : null,
			'notice'   => Policies::headline( $tiers ),
			'trust'    => array_intersect( array( 'hotel', 'typed' ), $modes ) ? array( __( 'Pick-up from your hotel', 'itour-excursions' ) ) : array(),
		);
	}

	/**
	 * One price table per type and season.
	 *
	 * @param int      $id  Post id.
	 * @param callable $fmt Money formatter.
	 */
	private static function prices( int $id, callable $fmt ): string {
		$groups = array();
		foreach ( Excursion::rates( $id ) as $r ) {
			$season = '' !== (string) $r['season_from']
				/* translators: 1: start date, 2: end date. */
				? sprintf( __( '%1$s – %2$s', 'itour-excursions' ), wp_date( 'j M', strtotime( $r['season_from'] ) ), wp_date( 'j M Y', strtotime( (string) $r['season_to'] ) ) )
				: '';
			$groups[ Excursion::variants()[ $r['variant'] ] . ( '' !== $season ? ' · ' . $season : '' ) ][] = $r;
		}
		$h = '';
		foreach ( $groups as $label => $rows ) {
			$h .= '<table class="itour-exc__prices"><caption>' . esc_html( $label ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Group size', 'itour-excursions' ) . '</th><th scope="col">' . esc_html__( 'Adult', 'itour-excursions' ) . '</th><th scope="col">' . esc_html__( 'Child', 'itour-excursions' ) . ' <small>2–11</small></th><th scope="col">' . esc_html__( 'Infant', 'itour-excursions' ) . ' <small>0–1</small></th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$min  = (int) $r['pax_min'];
				$max  = (int) $r['pax_max'];
				$size = $max ? ( $min === $max ? (string) $min : $min . '–' . $max ) : $min . '+';
				$free = static fn( $v ) => (float) $v ? $fmt( $v ) : __( 'Free', 'itour-excursions' );
				/* translators: %s: group size, e.g. "2–3" or "11+". */
				$h .= '<tr><th scope="row">' . esc_html( sprintf( __( '%s travellers', 'itour-excursions' ), $size ) ) . '</th><td>' . esc_html( $fmt( $r['adult'] ) ) . '</td><td>' . esc_html( $free( $r['child'] ) ) . '</td><td>' . esc_html( $free( $r['infant'] ) ) . '</td></tr>';
			}
			$h .= '</tbody></table>';
		}
		return $h;
	}

	/**
	 * Add-ons, supplements and zone pick-up charges.
	 *
	 * @param int                 $id  Post id.
	 * @param array<string,mixed> $c   Settings.
	 * @param callable            $fmt Money formatter.
	 */
	private static function extras( int $id, array $c, callable $fmt ): string {
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
		return $extra ? '<ul class="itour-exc__extras"><li>' . implode( '</li><li>', array_map( 'esc_html', $extra ) ) . '</li></ul>' : '';
	}
}
