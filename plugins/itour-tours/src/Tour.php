<?php
/**
 * One tour package's data and price. Rules: docs/PRODUCTS.md (D-024).
 *
 * Text and photos live in the post (block editor); included lists, FAQ and gallery in the shared core
 * Info box. Dates, itinerary, extras and supplements live in the `_itour_tour` meta; price rows in their
 * own table (D-003).
 *
 * @package ITour\Tours
 */

namespace ITour\Tours;

defined( 'ABSPATH' ) || exit;

/**
 * Data access + pricing.
 */
final class Tour {

	/**
	 * Post type.
	 */
	public const TYPE = 'itour_tour';

	/**
	 * Meta key for the settings array.
	 */
	public const META = '_itour_tour';

	/**
	 * Settings with defaults.
	 *
	 * @param int $post Post id.
	 * @return array<string,mixed>
	 */
	public static function config( int $post ): array {
		return array_merge(
			array(
				'destination' => 0,
				'days'        => 1,
				'booking'     => 'instant', // instant | enquiry.
				'deposit'     => 0,         // % payable at checkout; 0 = full price only.
				'dates'       => 'weekdays', // weekdays (start on chosen weekdays) | fixed (set departures).
				'start_days'  => array( 1, 2, 3, 4, 5, 6, 7 ),
				'cutoff'      => 7,         // Days before the start.
				'departures'  => array(),   // [ date, seats ].
				'itinerary'   => array(),   // [ title, place, places, meals, overnight, text ].
				'extras'      => array(),   // [ name, price, per ].
				'supplements' => array(),   // [ name, from, to, adult, child, infant ].
			),
			array_filter( (array) get_post_meta( $post, self::META, true ), 'is_string', ARRAY_FILTER_USE_KEY ) // Drops the [ 0 => '' ] an empty meta casts to.
		);
	}

	/**
	 * Hotel classes a price row can be for ('' = one standard for the whole package). PHP makes '3' an int key.
	 *
	 * @return array<int|string,string>
	 */
	public static function classes(): array {
		return array(
			''  => __( 'Standard', 'itour-tours' ),
			'3' => __( '3-star hotels', 'itour-tours' ),
			'4' => __( '4-star hotels', 'itour-tours' ),
			'5' => __( '5-star hotels', 'itour-tours' ),
		);
	}

	/**
	 * Meals options for an itinerary day.
	 *
	 * @return array<string,string>
	 */
	public static function meals(): array {
		return array(
			''      => __( 'No meals', 'itour-tours' ),
			'B'     => __( 'Breakfast', 'itour-tours' ),
			'B,L'   => __( 'Breakfast, lunch', 'itour-tours' ),
			'B,D'   => __( 'Breakfast, dinner', 'itour-tours' ),
			'B,L,D' => __( 'All meals', 'itour-tours' ),
			'L'     => __( 'Lunch', 'itour-tours' ),
			'D'     => __( 'Dinner', 'itour-tours' ),
			'L,D'   => __( 'Lunch, dinner', 'itour-tours' ),
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
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT hotel_class, season_from, season_to, pax_min, pax_max, adult, child, infant, single_supp, triple_red FROM %i WHERE post_id = %d ORDER BY hotel_class, season_from, pax_min', $wpdb->prefix . 'itour_tour_rates', $post ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * The price row for a hotel class, start date and group size. A row with season dates beats an all-year row.
	 *
	 * @param int    $post  Post id.
	 * @param string $stars Hotel class key.
	 * @param string $date  Start date, Y-m-d.
	 * @param int    $pax   Travellers, infants included.
	 * @return array<string,mixed>|null
	 */
	public static function rate( int $post, string $stars, string $date, int $pax ): ?array {
		$best = null;
		foreach ( self::rates( $post ) as $r ) {
			$seasonal = '' !== (string) $r['season_from'];
			if ( (string) $r['hotel_class'] !== $stars || $pax < (int) $r['pax_min'] || ( (int) $r['pax_max'] && $pax > (int) $r['pax_max'] ) ) {
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
	 * @param string $stars    Hotel class key.
	 * @param string $date     Start date, Y-m-d.
	 * @param int    $adults   Adults.
	 * @param int    $children Children.
	 * @param int    $infants  Infants.
	 * @param int    $singles  Travellers in single rooms.
	 * @param int    $triples  Travellers in triple rooms.
	 * @param int[]  $extras   Indexes of chosen optional extras.
	 * @return array{lines:array<int,array{0:string,1:float}>,total:float}|null
	 */
	public static function price( int $post, string $stars, string $date, int $adults, int $children, int $infants, int $singles = 0, int $triples = 0, array $extras = array() ): ?array {
		$rate = self::rate( $post, $stars, $date, $adults + $children + $infants );
		if ( ! $rate || $adults < 1 ) {
			return null;
		}
		$cfg   = self::config( $post );
		$end   = gmdate( 'Y-m-d', strtotime( $date . ' +' . max( 0, (int) $cfg['days'] - 1 ) . ' days' ) );
		$paid  = $adults + $children;
		$lines = array();
		$add   = static function ( string $label, float $amount ) use ( &$lines ): void {
			if ( 0.0 !== round( $amount, 2 ) ) {
				$lines[] = array( $label, round( $amount, 2 ) );
			}
		};
		/* translators: %d: number of adults. */
		$add( sprintf( _n( '%d adult', '%d adults', $adults, 'itour-tours' ), $adults ), $adults * (float) $rate['adult'] );
		/* translators: %d: number of children. */
		$add( sprintf( _n( '%d child', '%d children', $children, 'itour-tours' ), $children ), $children * (float) $rate['child'] );
		/* translators: %d: number of infants. */
		$add( sprintf( _n( '%d infant', '%d infants', $infants, 'itour-tours' ), $infants ), $infants * (float) $rate['infant'] );
		$add( __( 'Single room supplement', 'itour-tours' ), $singles * (float) $rate['single_supp'] );
		$add( __( 'Triple room reduction', 'itour-tours' ), -$triples * (float) $rate['triple_red'] );
		foreach ( (array) $cfg['supplements'] as $s ) {
			$to = '' !== $s['to'] ? $s['to'] : $s['from'];
			if ( $s['from'] <= $end && $to >= $date ) { // The trip overlaps the supplement's dates.
				$add( (string) $s['name'], $adults * (float) $s['adult'] + $children * (float) $s['child'] + $infants * (float) $s['infant'] );
			}
		}
		foreach ( $extras as $i ) {
			$x = $cfg['extras'][ $i ] ?? null;
			if ( $x ) {
				$add( (string) $x['name'], 'booking' === $x['per'] ? (float) $x['price'] : $paid * (float) $x['price'] );
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
