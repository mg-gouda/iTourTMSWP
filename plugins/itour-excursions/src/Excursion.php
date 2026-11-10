<?php
/**
 * One excursion's data and price. Rules: docs/PRODUCTS.md (D-024).
 *
 * Text and photos live in the post (block editor). Schedule, pick-up, add-ons and supplements live in
 * the `_itour_excursion` meta; price rows and pick-up zones in their own tables (D-003).
 *
 * @package ITour\Excursions
 */

namespace ITour\Excursions;

defined( 'ABSPATH' ) || exit;

/**
 * Data access + pricing.
 */
final class Excursion {

	/**
	 * Post type.
	 */
	public const TYPE = 'itour_excursion';

	/**
	 * Meta key for the settings array.
	 */
	public const META = '_itour_excursion';

	/**
	 * Settings with defaults.
	 *
	 * @param int $post Post id.
	 * @return array<string,mixed>
	 */
	public static function config( int $post ): array {
		return array_merge(
			array(
				'destination'   => 0,
				'duration'      => '',
				'days'          => array( 1, 2, 3, 4, 5, 6, 7 ), // ISO weekdays, 1 = Monday.
				'times'         => array(),
				'cutoff'        => 24,
				'seats'         => 0,
				'blocked'       => array(),
				'pickup_modes'  => array( 'hotel' ),
				'meeting_point' => '',
				'addons'        => array(),
				'supplements'   => array(),
			),
			(array) get_post_meta( $post, self::META, true )
		);
	}

	/**
	 * Price rows.
	 *
	 * @param int $post Post id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function rates( int $post ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT variant, season_from, season_to, pax_min, pax_max, adult, child, infant FROM %i WHERE post_id = %d ORDER BY variant DESC, season_from, pax_min', $wpdb->prefix . 'itour_excursion_rates', $post ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Pick-up zones.
	 *
	 * @param int $post Post id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function pickups( int $post ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT zone_id, pickup_time, extra FROM %i WHERE post_id = %d ORDER BY pickup_time', $wpdb->prefix . 'itour_excursion_pickups', $post ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Variants offered: shared, private or both.
	 *
	 * @return array<string,string>
	 */
	public static function variants(): array {
		return array(
			'shared'  => __( 'Shared (group)', 'itour-excursions' ),
			'private' => __( 'Private', 'itour-excursions' ),
		);
	}

	/**
	 * The price row for a variant, date and group size. A row with season dates beats an all-year row.
	 *
	 * @param int    $post    Post id.
	 * @param string $variant shared|private.
	 * @param string $date    Y-m-d.
	 * @param int    $pax     Travellers, infants included.
	 * @return array<string,mixed>|null
	 */
	public static function rate( int $post, string $variant, string $date, int $pax ): ?array {
		$best = null;
		foreach ( self::rates( $post ) as $r ) {
			$seasonal = '' !== (string) $r['season_from'];
			if ( $r['variant'] !== $variant || $pax < (int) $r['pax_min'] || ( (int) $r['pax_max'] && $pax > (int) $r['pax_max'] ) ) {
				continue;
			}
			if ( $seasonal && ( $date < $r['season_from'] || $date > (string) $r['season_to'] ) ) {
				continue;
			}
			if ( ! $best || $seasonal ) {
				$best = $r;
			}
		}
		return $best;
	}

	/**
	 * Full price with a line per charge. Null when no price row fits.
	 *
	 * @param int    $post     Post id.
	 * @param string $variant  shared|private.
	 * @param string $date     Y-m-d.
	 * @param int    $adults   Adults.
	 * @param int    $children Children.
	 * @param int    $infants  Infants.
	 * @param int    $zone     Pick-up zone id (0 = no zone charge).
	 * @param int[]  $addons   Indexes of chosen add-ons.
	 * @return array{lines:array<int,array{0:string,1:float}>,total:float}|null
	 */
	public static function price( int $post, string $variant, string $date, int $adults, int $children, int $infants, int $zone = 0, array $addons = array() ): ?array {
		$rate = self::rate( $post, $variant, $date, $adults + $children + $infants );
		if ( ! $rate || $adults < 1 ) {
			return null;
		}
		$cfg   = self::config( $post );
		$paid  = $adults + $children; // Infants ride free on extras.
		$lines = array();
		$add   = static function ( string $label, float $amount ) use ( &$lines ): void {
			if ( $amount > 0 ) {
				$lines[] = array( $label, round( $amount, 2 ) );
			}
		};
		/* translators: %d: number of adults. */
		$add( sprintf( _n( '%d adult', '%d adults', $adults, 'itour-excursions' ), $adults ), $adults * (float) $rate['adult'] );
		/* translators: %d: number of children. */
		$add( sprintf( _n( '%d child', '%d children', $children, 'itour-excursions' ), $children ), $children * (float) $rate['child'] );
		/* translators: %d: number of infants. */
		$add( sprintf( _n( '%d infant', '%d infants', $infants, 'itour-excursions' ), $infants ), $infants * (float) $rate['infant'] );
		foreach ( self::pickups( $post ) as $p ) {
			if ( $zone && (int) $p['zone_id'] === $zone ) {
				$add( __( 'Pick-up from your area', 'itour-excursions' ), $paid * (float) $p['extra'] );
			}
		}
		foreach ( (array) $cfg['supplements'] as $s ) {
			if ( $date >= $s['from'] && $date <= ( '' !== $s['to'] ? $s['to'] : $s['from'] ) ) {
				$add( (string) $s['name'], $adults * (float) $s['adult'] + $children * (float) $s['child'] + $infants * (float) $s['infant'] );
			}
		}
		foreach ( $addons as $i ) {
			$a = $cfg['addons'][ $i ] ?? null;
			if ( $a ) {
				$add( (string) $a['name'], 'booking' === $a['per'] ? (float) $a['price'] : $paid * (float) $a['price'] );
			}
		}
		return array(
			'lines' => $lines,
			'total' => round( array_sum( array_column( $lines, 1 ) ), 2 ),
		);
	}

	/**
	 * Lowest adult price (for "from" labels and sorting), 0 when unpriced.
	 *
	 * @param int $post Post id.
	 */
	public static function from_price( int $post ): float {
		$prices = array_filter( array_map( static fn( $r ) => (float) $r['adult'], self::rates( $post ) ) );
		return $prices ? min( $prices ) : 0.0;
	}
}
