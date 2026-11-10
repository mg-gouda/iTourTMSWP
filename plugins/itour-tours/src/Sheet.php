<?php
/**
 * Tour Packages bulk import / export: one tour per row. Every part is saved through the tour's own
 * panel tabs, so the spreadsheet follows the same rules as the edit screen. Images are added by hand.
 *
 * @package ITour\Tours
 */

namespace ITour\Tours;

use ITour\Core\Admin\Cells;
use ITour\Core\Admin\Import;
use ITour\Core\Admin\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Columns, export, row import.
 */
final class Sheet {

	/**
	 * The import/export screen.
	 *
	 * @param string $cap Capability.
	 */
	public static function screen( string $cap ): Import {
		return new Import(
			'tour-packages',
			array(
				'title'   => __( 'Tour Packages', 'itour-tours' ),
				'cap'     => $cap,
				'photos'  => true,
				'fill'    => __( 'One tour per row. Itinerary, prices and the other "one per line" cells take one entry per line (Alt+Enter in Excel), parts separated by |. The Instructions sheet explains every column. Photos are added on each tour afterwards.', 'itour-tours' ),
				'columns' => self::columns(),
				'export'  => array( self::class, 'export' ),
				'row'     => array( self::class, 'row' ),
			)
		);
	}

	/**
	 * Columns: key => [ label, help, example, width ].
	 *
	 * @return array<string,array<int,mixed>>
	 */
	private static function columns(): array {
		return Cells::post_columns() + array(
			'destination' => array( __( 'Main destination', 'itour-tours' ), __( 'A destination name from Location Tree.', 'itour-tours' ), 'Hurghada', 16 ),
			'days'        => array( __( 'Days', 'itour-tours' ), __( 'Number of days (nights = days minus one).', 'itour-tours' ), '4', 7 ),
			'booking'     => array( __( 'Booking', 'itour-tours' ), __( 'instant (pay online) or enquiry (you reply with a quote).', 'itour-tours' ), 'instant', 10 ),
			'deposit'     => array( __( 'Deposit %', 'itour-tours' ), __( '0 = full price at checkout.', 'itour-tours' ), '25', 9 ),
			'dates'       => array( __( 'How it starts', 'itour-tours' ), __( 'weekdays (any date on the start days) or fixed (only the departures).', 'itour-tours' ), 'weekdays', 12 ),
			'start_days'  => array( __( 'Start days', 'itour-tours' ), __( 'Comma separated, e.g. Sat or Mon, Thu. "any" for every day.', 'itour-tours' ), 'Sat', 12 ),
			'cutoff'      => array( __( 'Booking closes (days)', 'itour-tours' ), __( 'Days before the start.', 'itour-tours' ), '5', 10 ),
			'departures'  => array( __( 'Departures', 'itour-tours' ), __( 'Fixed departures, one per line: Date | Seats (0 = no limit).', 'itour-tours' ), '2026-12-26 | 16', 18 ),
			'itinerary'   => array( __( 'Itinerary', 'itour-tours' ), __( 'One day per line: Title | Map place (Location Tree name) | Places visited | Meals (B, L, D, e.g. B,L or none) | Overnight | What happens.', 'itour-tours' ), "Arrival in Hurghada | Hurghada International Airport | Airport, hotel | D | Hurghada | Meet and assist.\nLuxor East Bank | East Bank | Karnak, Luxor Temple | B,L | Luxor | Full day with an Egyptologist.", 50 ),
			'prices'      => array( __( 'Prices', 'itour-tours' ), __( 'One price row per line: Hotels (standard, 3, 4 or 5) | Season from | Season to | From travellers | To travellers | Adult | Child | Infant | Single room + | Triple room −. Season dates empty = all year; To travellers empty = no limit.', 'itour-tours' ), "4 |  |  | 2 | 5 | 450 | 225 | 0 | 120 | 30\n4 |  |  | 6 |  | 390 | 195 | 0 | 120 | 30", 44 ),
			'extras'      => array( __( 'Optional extras', 'itour-tours' ), __( 'One per line: Name | Price | person or booking.', 'itour-tours' ), 'Hot-air balloon | 95 | person', 26 ),
			'supplements' => array( __( 'Supplements', 'itour-tours' ), __( 'One per line: Name | Date | Until (optional) | Adult | Child | Infant. Added when the tour dates include them.', 'itour-tours' ), 'New Year\'s Gala Meal | 2026-12-31 |  | 85 | 40 | 0', 30 ),
		) + Cells::info_columns();
	}

	/**
	 * Meals cell → stored key ('' for none).
	 *
	 * @param string $v Cell.
	 */
	public static function meals( string $v ): ?string {
		$v = mb_strtolower( trim( $v ) );
		if ( '' === $v || 'none' === $v || '-' === $v ) {
			return '';
		}
		$on = array();
		foreach ( array( 'B' => 'b', 'L' => 'l', 'D' => 'd' ) as $k => $letter ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			if ( preg_match(
				'/(^|[^a-z])' . $letter . '([^a-z]|$)|' . array(
					'b' => 'breakfast',
					'l' => 'lunch',
					'd' => 'dinner',
				)[ $letter ] . '/',
				$v
			) ) {
				$on[] = $k;
			}
		}
		$key = implode( ',', $on );
		return array_key_exists( $key, Tour::meals() ) ? $key : null;
	}

	/**
	 * Hotels cell → class key: standard/empty → '', "4", "4*", "4-star" → '4'.
	 *
	 * @param string $v Cell.
	 */
	public static function hotels( string $v ): ?string {
		$v = mb_strtolower( trim( $v ) );
		if ( '' === $v || 'standard' === $v ) {
			return '';
		}
		$k = (string) preg_replace( '/\D/', '', $v );
		return in_array( $k, array( '3', '4', '5' ), true ) ? $k : null;
	}

	/**
	 * "person" / "booking" (empty = person).
	 *
	 * @param string $v Cell.
	 */
	public static function per( string $v ): ?string {
		$v = mb_strtolower( trim( $v ) );
		if ( '' === $v ) {
			return 'person';
		}
		return in_array( $v, array( 'person', 'booking' ), true ) ? $v : null;
	}

	/**
	 * Parse specs for the tour's own "one per line" cells.
	 *
	 * @return array<string,array<string,array{0:mixed,1:bool}>>
	 */
	private static function specs(): array {
		$num   = static fn( $v ) => Cells::number( $v );
		$count = static fn( $v ) => '' === trim( $v ) ? '' : ( ctype_digit( trim( $v ) ) ? trim( $v ) : null );
		$money = array( $num, false );
		return array(
			'departures'  => array(
				'date'  => array( 'date', true ),
				'seats' => array( $count, false ),
			),
			'itinerary'   => array(
				'title'     => array( 'text', true ),
				'place'     => array( static fn( $v ) => '' === trim( $v ) ? '' : ( Cells::location( $v ) ? (string) Cells::location( $v ) : null ), false ),
				'places'    => array( 'text', false ),
				'meals'     => array( array( self::class, 'meals' ), false ),
				'overnight' => array( 'text', false ),
				'text'      => array( static fn( $v ) => sanitize_textarea_field( $v ), false ),
			),
			'prices'      => array(
				'hotel_class' => array( array( self::class, 'hotels' ), false ),
				'season_from' => array( 'date', false ),
				'season_to'   => array( 'date', false ),
				'pax_min'     => array( $count, false ),
				'pax_max'     => array( $count, false ),
				'adult'       => array( $num, true ),
				'child'       => $money,
				'infant'      => $money,
				'single_supp' => $money,
				'triple_red'  => $money,
			),
			'extras'      => array(
				'name'  => array( 'text', true ),
				'price' => array( $num, true ),
				'per'   => array( array( self::class, 'per' ), false ),
			),
			'supplements' => array(
				'name'   => array( 'text', true ),
				'from'   => array( 'date', true ),
				'to'     => array( 'date', false ),
				'adult'  => $money,
				'child'  => $money,
				'infant' => $money,
			),
		);
	}

	/**
	 * Every tour as a row.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function export(): array {
		$rows = array();
		foreach ( get_posts( array( 'post_type' => Tour::TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			$c      = Tour::config( $post->ID );
			$rows[] = Cells::post_export( $post ) + array(
				'destination' => Cells::location_name( (int) $c['destination'] ),
				'days'        => (string) $c['days'],
				'booking'     => (string) $c['booking'],
				'deposit'     => (string) $c['deposit'],
				'dates'       => (string) $c['dates'],
				'start_days'  => Cells::days_text( array_map( 'intval', (array) $c['start_days'] ) ),
				'cutoff'      => (string) $c['cutoff'],
				'departures'  => Cells::export_rows( (array) $c['departures'], array( 'date', 'seats' ) ),
				'itinerary'   => Cells::export_rows( (array) $c['itinerary'], array( 'title', 'place', 'places', 'meals', 'overnight', 'text' ), array( 'place' => static fn( $v ) => Cells::location_name( (int) $v ), 'text' => static fn( $v ) => str_replace( array( "\r\n", "\n" ), ' ', (string) $v ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
				'prices'      => Cells::export_rows( Tour::rates( $post->ID ), array( 'hotel_class', 'season_from', 'season_to', 'pax_min', 'pax_max', 'adult', 'child', 'infant', 'single_supp', 'triple_red' ), array( 'hotel_class' => static fn( $v ) => '' === (string) $v ? 'standard' : (string) $v, 'pax_max' => static fn( $v ) => (int) $v ? (string) $v : '' ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
				'extras'      => Cells::export_rows( (array) $c['extras'], array( 'name', 'price', 'per' ) ),
				'supplements' => Cells::export_rows( (array) $c['supplements'], array( 'name', 'from', 'to', 'adult', 'child', 'infant' ) ),
			) + Cells::info_export( $post->ID );
		}
		return $rows;
	}

	/**
	 * Check one row; with $apply, save it.
	 *
	 * @param array<string,string> $row   Row.
	 * @param bool                 $apply Save.
	 * @return array{action:string,title:string,errors:string[]}
	 */
	public static function row( array $row, bool $apply ): array {
		$set    = static fn( string $k ): bool => '' !== ( $row[ $k ] ?? '' );
		$post   = Cells::post( Tour::TYPE, $row, false );
		$errors = $post['errors'];
		$parsed = array();
		foreach ( self::specs() as $k => $spec ) {
			$parsed[ $k ] = Cells::parse( (string) ( $row[ $k ] ?? '' ), $spec, self::columns()[ $k ][0] );
			$errors       = array_merge( $errors, $parsed[ $k ]['errors'] );
		}
		$dest = $set( 'destination' ) && ! Cells::clear( $row['destination'] ) ? Cells::location( $row['destination'], array( 'destination' ) ) : 0;
		if ( $set( 'destination' ) && ! Cells::clear( $row['destination'] ) && ! $dest ) {
			/* translators: %s: name. */
			$errors[] = sprintf( __( 'Main destination “%s” isn’t in Location Tree.', 'itour-tours' ), $row['destination'] );
		}
		$start = $set( 'start_days' ) ? Cells::days( $row['start_days'] ) : array();
		if ( null === $start ) {
			$errors[] = __( 'Start days: use day names like Mon, Sat, or any.', 'itour-tours' );
		}
		foreach ( array( 'booking' => array( 'instant', 'enquiry' ), 'dates' => array( 'weekdays', 'fixed' ) ) as $k => $ok ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			if ( $set( $k ) && ! in_array( mb_strtolower( $row[ $k ] ), $ok, true ) ) {
				/* translators: 1: column, 2: allowed values. */
				$errors[] = sprintf( __( '%1$s must be %2$s.', 'itour-tours' ), self::columns()[ $k ][0], implode( ' / ', $ok ) );
			}
		}
		foreach ( array( 'days', 'deposit', 'cutoff' ) as $k ) {
			if ( $set( $k ) && ! ctype_digit( $row[ $k ] ) ) {
				/* translators: %s: column. */
				$errors[] = sprintf( __( '%s must be a whole number.', 'itour-tours' ), self::columns()[ $k ][0] );
			}
		}
		$errors = array_merge( $errors, Cells::info_import( Tour::TYPE, 0, $row, false ) );
		$out    = array(
			'action' => $post['action'],
			'title'  => $post['title'],
			'errors' => $errors,
		);
		if ( ! $apply || $errors ) {
			return $out;
		}

		$post = Cells::post( Tour::TYPE, $row, true );
		$id   = $post['id'];
		if ( $post['errors'] || ! $id ) {
			$out['errors'] = $post['errors'];
			return $out;
		}
		$c   = Tour::config( $id );
		$val = static fn( string $k, $cur ) => ! $set( $k ) ? $cur : ( Cells::clear( $row[ $k ] ) ? '' : $row[ $k ] );
		if ( $set( 'destination' ) || $set( 'days' ) || $set( 'booking' ) || $set( 'deposit' ) ) {
			Panel::save_section(
				Tour::TYPE,
				'basics',
				$id,
				array(
					'destination' => $set( 'destination' ) ? $dest : $c['destination'],
					'days'        => $val( 'days', $c['days'] ),
					'booking'     => mb_strtolower( (string) $val( 'booking', $c['booking'] ) ),
					'deposit'     => $val( 'deposit', $c['deposit'] ),
				)
			);
		}
		if ( $set( 'dates' ) || $set( 'start_days' ) || $set( 'cutoff' ) || $set( 'departures' ) ) {
			Panel::save_section(
				Tour::TYPE,
				'dates',
				$id,
				array(
					'dates'      => mb_strtolower( (string) $val( 'dates', $c['dates'] ) ),
					'start_days' => $set( 'start_days' ) ? $start : $c['start_days'],
					'cutoff'     => $val( 'cutoff', $c['cutoff'] ),
					'departures' => $set( 'departures' ) ? ( Cells::clear( $row['departures'] ) ? array() : $parsed['departures']['rows'] ) : $c['departures'],
				)
			);
		}
		foreach ( array( 'itinerary' => 'itinerary', 'prices' => 'prices', 'extras' => 'extras', 'supplements' => 'supplements' ) as $k => $tab ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			if ( $set( $k ) ) {
				Panel::save_section( Tour::TYPE, $tab, $id, array( 'rows' => Cells::clear( $row[ $k ] ) ? array() : $parsed[ $k ]['rows'] ) );
			}
		}
		Cells::info_import( Tour::TYPE, $id, $row, true );
		return $out;
	}
}
