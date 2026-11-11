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
		wp_enqueue_script( 'itour-exc-quote', plugins_url( 'assets/quote.js', ITOUR_EXCURSIONS_FILE ), array(), '0.5.0', true );
		wp_localize_script(
			'itour-exc-quote',
			'itourExcQuote',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'total' => __( 'Total for your group', 'itour-excursions' ),
			)
		);
		$c    = Excursion::config( $id );
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
		$extras   = self::extras( $id, $c );
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
			'price'    => $from ? array( 'amount' => \ITour\Core\Cards::money( $from ) ) : null, // Lowest price; the card shows the group total as the form changes.
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
	 * Add-ons, supplements and zone pick-up charges.
	 *
	 * @param int                 $id  Post id.
	 * @param array<string,mixed> $c   Settings.
	 */
	private static function extras( int $id, array $c ): string {
		$extra = array();
		foreach ( (array) $c['addons'] as $a ) {
			/* translators: %s: add-on name. */
			$extra[] = sprintf( __( '%s (optional, choose it when you book)', 'itour-excursions' ), $a['name'] );
		}
		foreach ( (array) $c['supplements'] as $s ) {
			$when = wp_date( 'j M Y', strtotime( $s['from'] ) ) . ( '' !== $s['to'] && $s['to'] !== $s['from'] ? ' – ' . wp_date( 'j M Y', strtotime( $s['to'] ) ) : '' );
			/* translators: 1: supplement name, 2: date(s). */
			$extra[] = sprintf( __( '%1$s (%2$s): included in the total for these dates', 'itour-excursions' ), $s['name'], $when );
		}
		$zones = Locations::zone_options();
		foreach ( Excursion::pickups( $id ) as $p ) {
			if ( (float) $p['extra'] > 0 && isset( $zones[ $p['zone_id'] ] ) ) {
				/* translators: %s: zone. */
				$extra[] = sprintf( __( 'Pick-up from %s: a small surcharge, included in the total', 'itour-excursions' ), $zones[ $p['zone_id'] ] );
			}
		}
		return $extra ? '<ul class="itour-exc__extras"><li>' . implode( '</li><li>', array_map( 'esc_html', $extra ) ) . '</li></ul>' : '';
	}
}
