<?php
/**
 * Cancellation tiers (same model as Rehlla's booking policies, owner request 10 Nov 2026):
 * a tier is "cancel N or more days before arrival → fee X % of the price", optionally limited to a
 * travel-date season. Season tiers replace the all-year tiers on their dates. The closest tier that is
 * still reachable wins; with no tier (or too late) the fee is 100 %.
 *
 * Tours and excursions keep their tiers in post meta; transfers per vehicle (or all vehicles) in an option.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

use ITour\Core\Admin\Panel;
use ITour\Core\Admin\Rows;

defined( 'ABSPATH' ) || exit;

/**
 * Tier maths, wording and the panel tab.
 */
final class Policies {

	/**
	 * Post meta key for a product's tiers.
	 */
	public const META = '_itour_cancel';

	/**
	 * Tier table columns.
	 *
	 * @param array<int|string,string> $vehicles Vehicle options (transfers) or [] for products.
	 * @return array<string,array<string,mixed>>
	 */
	public static function cols( array $vehicles = array() ): array {
		$cols = array();
		if ( $vehicles ) {
			$cols['vehicle'] = array(
				'label'   => __( 'Vehicle', 'itour-core' ),
				'type'    => 'select',
				'options' => array( '' => __( 'All vehicles', 'itour-core' ) ) + $vehicles,
				'width'   => '12em',
			);
		}
		return $cols + array(
			'from' => array(
				'label' => __( 'Travel from (optional)', 'itour-core' ),
				'type'  => 'date',
				'width' => '10em',
			),
			'to'   => array(
				'label' => __( 'Travel to (optional)', 'itour-core' ),
				'type'  => 'date',
				'width' => '10em',
			),
			'days' => array(
				'label' => __( 'Days before arrival', 'itour-core' ),
				'type'  => 'number',
				'width' => '9em',
			),
			'fee'  => array(
				'label' => __( 'Fee (% of price)', 'itour-core' ),
				'type'  => 'decimal',
				'width' => '9em',
			),
		);
	}

	/**
	 * Clean posted tiers: drop incomplete rows, clamp the fee, order seasons first then most days first.
	 *
	 * @param array<string,array<string,mixed>> $cols Columns.
	 * @param mixed                             $raw  Posted rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function clean( array $cols, $raw ): array {
		$rows = Rows::clean( $cols, $raw, array( 'days', 'fee' ) );
		foreach ( $rows as &$r ) {
			$r['days'] = (int) $r['days'];
			$r['fee']  = min( 100, max( 0, round( (float) $r['fee'], 2 ) ) );
			if ( '' !== $r['from'] && '' !== $r['to'] && $r['from'] > $r['to'] ) {
				list( $r['from'], $r['to'] ) = array( $r['to'], $r['from'] );
			}
		}
		unset( $r );
		usort( $rows, static fn( $a, $b ) => array( '' === $a['from'] ? 1 : 0, $a['from'], -$a['days'] ) <=> array( '' === $b['from'] ? 1 : 0, $b['from'], -$b['days'] ) );
		return $rows;
	}

	/**
	 * Tiers that apply to a travel date (season tiers replace all-year ones).
	 *
	 * @param array<int,array<string,mixed>> $rows   Tiers.
	 * @param string                         $travel Y-m-d.
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_date( array $rows, string $travel ): array {
		$season = array();
		$year   = array();
		foreach ( $rows as $r ) {
			if ( '' === (string) $r['from'] && '' === (string) $r['to'] ) {
				$year[] = $r;
			} elseif ( ( '' === (string) $r['from'] || $travel >= $r['from'] ) && ( '' === (string) $r['to'] || $travel <= $r['to'] ) ) {
				$season[] = $r;
			}
		}
		$out = $season ? $season : $year;
		usort( $out, static fn( $a, $b ) => (int) $b['days'] <=> (int) $a['days'] );
		return $out;
	}

	/**
	 * Fee % when cancelling on $on (today by default) for arrival on $travel.
	 *
	 * @param array<int,array<string,mixed>> $rows   Tiers.
	 * @param string                         $travel Y-m-d.
	 * @param string                         $on     Y-m-d.
	 */
	public static function fee( array $rows, string $travel, string $on = '' ): float {
		$left = (int) floor( ( strtotime( $travel . ' UTC' ) - strtotime( ( '' !== $on ? $on : current_time( 'Y-m-d' ) ) . ' UTC' ) ) / DAY_IN_SECONDS );
		foreach ( self::for_date( $rows, $travel ) as $r ) { // Most days first: the first reachable tier is the closest.
			if ( (int) $r['days'] <= $left ) {
				return (float) $r['fee'];
			}
		}
		return 100.0;
	}

	/**
	 * Fee wording: "free cancellation", "50% fee", "no refund".
	 *
	 * @param float $fee Fee %.
	 */
	private static function fee_text( float $fee ): string {
		if ( $fee <= 0 ) {
			return __( 'free cancellation', 'itour-core' );
		}
		/* translators: %s: fee percentage. */
		return $fee >= 100 ? __( 'no refund', 'itour-core' ) : sprintf( __( '%s%% fee', 'itour-core' ), number_format_i18n( $fee, floor( $fee ) === $fee ? 0 : 2 ) );
	}

	/**
	 * One booking's conditions, dated: "Free cancellation until 13 Dec 2026 · 50% fee until 20 Dec 2026 · then no refund".
	 *
	 * @param array<int,array<string,mixed>> $rows   Tiers.
	 * @param string                         $travel Y-m-d.
	 */
	public static function summary( array $rows, string $travel ): string {
		$tiers = self::for_date( $rows, $travel );
		if ( ! $tiers ) {
			return __( 'Non-refundable', 'itour-core' );
		}
		$t     = strtotime( $travel . ' 12:00:00' );
		$today = current_time( 'Y-m-d' );
		$parts = array();
		foreach ( $tiers as $r ) {
			$until = gmdate( 'Y-m-d', $t - (int) $r['days'] * DAY_IN_SECONDS );
			if ( $until < $today || (float) $r['fee'] >= 100 ) {
				continue; // Past steps and "no refund" steps: the closing "then no refund" covers them.
			}
			/* translators: 1: "free cancellation" / "50% fee", 2: date. */
			$parts[] = sprintf( __( '%1$s until %2$s', 'itour-core' ), self::fee_text( (float) $r['fee'] ), wp_date( 'j M Y', strtotime( $until . ' 12:00:00' ) ) );
		}
		if ( ! $parts ) {
			return __( 'Non-refundable', 'itour-core' );
		}
		$parts[] = __( 'then no refund', 'itour-core' );
		return ucfirst( implode( ' · ', $parts ) );
	}

	/**
	 * The policy for a product page: one list per season, all-year last.
	 *
	 * @param array<int,array<string,mixed>> $rows    Tiers.
	 * @param bool                           $heading Print the "Cancellation" heading (layouts print their own).
	 */
	public static function html( array $rows, bool $heading = true ): string {
		if ( ! $rows ) {
			return '';
		}
		$groups = array();
		foreach ( $rows as $r ) {
			$groups[ $r['from'] . '|' . $r['to'] ][] = $r;
		}
		uksort( $groups, static fn( $a, $b ) => array( '|' === $a ? 1 : 0, $a ) <=> array( '|' === $b ? 1 : 0, $b ) ); // Seasons by date, all-year last.
		$h = ( $heading ? '<h2 class="itour-exc__title">' . esc_html__( 'Cancellation', 'itour-core' ) . '</h2>' : '' ) . '<div class="itour-cancel">';
		foreach ( $groups as $range => $tiers ) {
			list( $from, $to ) = explode( '|', $range );
			usort( $tiers, static fn( $a, $b ) => (int) $b['days'] <=> (int) $a['days'] );
			if ( count( $groups ) > 1 || '|' !== $range ) {
				$label = '|' === $range ? __( 'All other travel dates', 'itour-core' )
					/* translators: 1: from date, 2: to date. */
					: sprintf( __( 'Travel %1$s – %2$s', 'itour-core' ), '' !== $from ? wp_date( 'j M Y', strtotime( $from ) ) : '…', '' !== $to ? wp_date( 'j M Y', strtotime( $to ) ) : '…' );
				$h .= '<h3>' . esc_html( $label ) . '</h3>';
			}
			$h .= '<ul>';
			foreach ( $tiers as $i => $r ) {
				$days = (int) $r['days'];
				$upto = $i ? (int) $tiers[ $i - 1 ]['days'] - 1 : null;
				$when = null === $upto
					/* translators: %d: days. */
					? sprintf( _n( '%d day or more before arrival', '%d days or more before arrival', $days, 'itour-core' ), $days )
					/* translators: 1: fewest days, 2: most days. */
					: ( $upto > $days ? sprintf( __( '%1$d–%2$d days before arrival', 'itour-core' ), $days, $upto ) : sprintf( _n( '%d day before arrival', '%d days before arrival', $days, 'itour-core' ), $days ) );
				$h .= '<li><span>' . esc_html( $when ) . '</span><strong>' . esc_html( ucfirst( self::fee_text( (float) $r['fee'] ) ) ) . '</strong></li>';
			}
			$last = end( $tiers );
			if ( (int) $last['days'] > 0 && (float) $last['fee'] < 100 ) {
				/* translators: %d: days. */
				$h .= '<li><span>' . esc_html( sprintf( _n( 'Less than %d day before arrival', 'Less than %d days before arrival', (int) $last['days'], 'itour-core' ), (int) $last['days'] ) ) . '</span><strong>' . esc_html( ucfirst( self::fee_text( 100 ) ) ) . '</strong></li>';
			}
			$h .= '</ul>';
		}
		return $h . '</div>';
	}

	/**
	 * One line for a booking card, from the all-year tiers: "Free cancellation up to 30 days before arrival",
	 * else the best fee ("50% fee up to 14 days before arrival"), else "Non-refundable".
	 *
	 * @param array<int,array<string,mixed>> $rows Tiers.
	 */
	public static function headline( array $rows ): string {
		$year = array_values( array_filter( $rows, static fn( $r ) => '' === (string) $r['from'] && '' === (string) $r['to'] ) );
		$use  = $year ? $year : $rows;
		if ( ! $use ) {
			return '';
		}
		usort( $use, static fn( $a, $b ) => array( (float) $a['fee'], - (int) $a['days'] ) <=> array( (float) $b['fee'], - (int) $b['days'] ) ); // Cheapest first, then the latest date.
		$best = $use[0];
		if ( (float) $best['fee'] >= 100 ) {
			return __( 'Non-refundable', 'itour-core' );
		}
		$days = (int) $best['days'];
		$when = 0 === $days ? __( 'up to the day of arrival', 'itour-core' )
			/* translators: %d: days. */
			: sprintf( _n( 'up to %d day before arrival', 'up to %d days before arrival', $days, 'itour-core' ), $days );
		return ucfirst( self::fee_text( (float) $best['fee'] ) ) . ' ' . $when;
	}

	/**
	 * "Free cancellation up to 2 days before arrival" when the all-year tiers allow a free cancellation, else ''.
	 *
	 * @param array<int,array<string,mixed>> $rows Tiers.
	 */
	public static function free_line( array $rows ): string {
		$year = array_filter( $rows, static fn( $r ) => '' === (string) $r['from'] && '' === (string) $r['to'] );
		return $year && min( array_map( static fn( $r ) => (float) $r['fee'], $year ) ) <= 0 ? self::headline( $rows ) : '';
	}

	/**
	 * A product's tiers.
	 *
	 * @param int $post Post id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function of( int $post ): array {
		$v = get_post_meta( $post, self::META, true );
		return is_array( $v ) ? $v : array(); // Not "(array) ''", which is [ '' ].
	}

	/**
	 * The "Cancellation" tab for product panels.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function section(): array {
		return array(
			'cancellation' => array(
				'label'  => __( 'Cancellation', 'itour-core' ),
				'badge'  => static fn( int $post ): string => self::of( $post ) ? (string) count( self::of( $post ) ) : '',
				'render' => static function ( \WP_Post $post ): void {
					echo '<div class="itp-row itp-row--wide"><div class="itp-row-text"><p class="itp-help">' . esc_html__( 'One row per tier: cancelling this many days or more before arrival costs this share of the price. Example: 30 days → 0% (free), 14 days → 50%, 0 days → 100%. Closer than every tier = no refund. Add travel dates to make a season tier: on those dates its tiers replace the all-year ones. No rows = non-refundable.', 'itour-core' ) . '</p></div><div class="itp-table-wrap">';
					Rows::render( Panel::name( 'cancellation', 'rows' ), self::cols(), self::of( $post->ID ), __( 'Add tier', 'itour-core' ) );
					echo '</div></div>';
				},
				'save'   => static function ( int $post, array $in ): void {
					update_post_meta( $post, self::META, self::clean( self::cols(), $in['rows'] ?? array() ) );
				},
			),
		);
	}
}
