<?php
/**
 * Excursions bulk import / export: one excursion per row, saved through the excursion's own panel tabs.
 * Images are added by hand.
 *
 * @package ITour\Excursions
 */

namespace ITour\Excursions;

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
			'excursions',
			array(
				'title'   => __( 'Excursions', 'itour-excursions' ),
				'cap'     => $cap,
				'photos'  => true,
				'fill'    => __( 'One excursion per row. Prices, pick-up zones and the other "one per line" cells take one entry per line (Alt+Enter in Excel), parts separated by |. The Instructions sheet explains every column. Photos are added on each excursion afterwards.', 'itour-excursions' ),
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
			'destination'   => array( __( 'Destination', 'itour-excursions' ), __( 'A destination name from Location Tree.', 'itour-excursions' ), 'Hurghada', 16 ),
			'duration'      => array( __( 'Duration', 'itour-excursions' ), __( 'As shown on the page, e.g. 12 hours or Half day.', 'itour-excursions' ), '14 hours', 12 ),
			'deposit'       => array( __( 'Deposit %', 'itour-excursions' ), __( '0 = full price at checkout.', 'itour-excursions' ), '30', 9 ),
			'days'          => array( __( 'Runs on', 'itour-excursions' ), __( 'Comma separated weekdays, e.g. Mon, Wed, Fri. "any" for every day.', 'itour-excursions' ), 'Mon, Wed, Fri', 14 ),
			'times'         => array( __( 'Start times', 'itour-excursions' ), __( 'Comma separated, 24-hour, e.g. 04:00, 09:30.', 'itour-excursions' ), '04:00', 12 ),
			'cutoff'        => array( __( 'Booking closes (hours)', 'itour-excursions' ), __( 'Hours before the start.', 'itour-excursions' ), '24', 10 ),
			'seats'         => array( __( 'Seats per day', 'itour-excursions' ), __( '0 = no limit.', 'itour-excursions' ), '20', 9 ),
			'blocked'       => array( __( 'Closed dates', 'itour-excursions' ), __( 'One per line: From | To (To empty for a single day).', 'itour-excursions' ), '2026-12-31 | 2027-01-01', 22 ),
			'pickup_modes'  => array( __( 'Pick-up', 'itour-excursions' ), __( 'Comma separated: hotel (hotels in the zones), typed (customer types the hotel), meeting (meeting point).', 'itour-excursions' ), 'hotel, meeting', 14 ),
			'zones'         => array( __( 'Pick-up zones', 'itour-excursions' ), __( 'One per line: Zone (Location Tree name) | Pick-up time | Extra per person.', 'itour-excursions' ), "Hurghada City | 03:30 | 0\nSahl Hasheesh | 03:00 | 5", 26 ),
			'meeting_point' => array( __( 'Meeting point', 'itour-excursions' ), __( 'Shown to customers who choose it.', 'itour-excursions' ), 'Marina Boulevard, 03:45', 22 ),
			'prices'        => array( __( 'Prices', 'itour-excursions' ), __( 'One price row per line: Type (shared or private) | Season from | Season to | From travellers | To travellers | Adult | Child | Infant. Season empty = all year; To travellers empty = no limit.', 'itour-excursions' ), "shared |  |  | 1 | 3 | 60 | 30 | 0\nshared |  |  | 4 |  | 50 | 25 | 0\nprivate |  |  | 1 |  | 120 | 60 | 0", 40 ),
			'addons'        => array( __( 'Add-ons', 'itour-excursions' ), __( 'One per line: Name | Price | person or booking.', 'itour-excursions' ), 'Lunch on the Nile | 10 | person', 26 ),
			'supplements'   => array( __( 'Supplements', 'itour-excursions' ), __( 'One per line: Name | Date | Until (optional) | Adult | Child | Infant.', 'itour-excursions' ), 'Christmas Gala Meal | 2026-12-24 |  | 40 | 20 | 0', 30 ),
		) + Cells::info_columns();
	}

	/**
	 * "shared" / "private".
	 *
	 * @param string $v Cell.
	 */
	public static function variant( string $v ): ?string {
		$v = mb_strtolower( trim( $v ) );
		if ( '' === $v ) {
			return 'shared';
		}
		return array_key_exists( $v, Excursion::variants() ) ? $v : null;
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
	 * HH:MM (also 4:00 or 0400).
	 *
	 * @param string $v Cell.
	 */
	public static function time( string $v ): ?string {
		if ( ! preg_match( '/^(\d{1,2}):?(\d{2})$/', trim( $v ), $m ) || (int) $m[1] > 23 || (int) $m[2] > 59 ) {
			return null;
		}
		return sprintf( '%02d:%02d', $m[1], $m[2] );
	}

	/**
	 * Parse specs for the "one per line" cells.
	 *
	 * @return array<string,array<string,array{0:mixed,1:bool}>>
	 */
	private static function specs(): array {
		$num   = static fn( $v ) => Cells::number( $v );
		$count = static fn( $v ) => '' === trim( $v ) ? '' : ( ctype_digit( trim( $v ) ) ? trim( $v ) : null );
		$money = array( $num, false );
		return array(
			'blocked'     => array(
				'from' => array( 'date', true ),
				'to'   => array( 'date', false ),
			),
			'zones'       => array(
				'zone_id'     => array( static fn( $v ) => Cells::location( $v, array( 'zone' ) ) ? (string) Cells::location( $v, array( 'zone' ) ) : null, true ),
				'pickup_time' => array( array( self::class, 'time' ), true ),
				'extra'       => $money,
			),
			'prices'      => array(
				'variant'     => array( array( self::class, 'variant' ), false ),
				'season_from' => array( 'date', false ),
				'season_to'   => array( 'date', false ),
				'pax_min'     => array( $count, false ),
				'pax_max'     => array( $count, false ),
				'adult'       => array( $num, true ),
				'child'       => $money,
				'infant'      => $money,
			),
			'addons'      => array(
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
	 * Every excursion as a row.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function export(): array {
		$rows = array();
		foreach ( get_posts( array( 'post_type' => Excursion::TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			$c      = Excursion::config( $post->ID );
			$rows[] = Cells::post_export( $post ) + array(
				'destination'   => Cells::location_name( (int) $c['destination'] ),
				'duration'      => (string) $c['duration'],
				'deposit'       => (string) $c['deposit'],
				'days'          => Cells::days_text( array_map( 'intval', (array) $c['days'] ) ),
				'times'         => implode( ', ', (array) $c['times'] ),
				'cutoff'        => (string) $c['cutoff'],
				'seats'         => (string) $c['seats'],
				'blocked'       => Cells::export_rows( (array) $c['blocked'], array( 'from', 'to' ) ),
				'pickup_modes'  => implode( ', ', (array) $c['pickup_modes'] ),
				'zones'         => Cells::export_rows( Excursion::pickups( $post->ID ), array( 'zone_id', 'pickup_time', 'extra' ), array( 'zone_id' => static fn( $v ) => Cells::location_name( (int) $v ), 'pickup_time' => static fn( $v ) => substr( (string) $v, 0, 5 ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
				'meeting_point' => (string) $c['meeting_point'],
				'prices'        => Cells::export_rows( Excursion::rates( $post->ID ), array( 'variant', 'season_from', 'season_to', 'pax_min', 'pax_max', 'adult', 'child', 'infant' ), array( 'pax_max' => static fn( $v ) => (int) $v ? (string) $v : '' ) ),
				'addons'        => Cells::export_rows( (array) $c['addons'], array( 'name', 'price', 'per' ) ),
				'supplements'   => Cells::export_rows( (array) $c['supplements'], array( 'name', 'from', 'to', 'adult', 'child', 'infant' ) ),
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
		$label  = static fn( string $k ): string => self::columns()[ $k ][0];
		$post   = Cells::post( Excursion::TYPE, $row, false );
		$errors = $post['errors'];
		$parsed = array();
		foreach ( self::specs() as $k => $spec ) {
			$parsed[ $k ] = Cells::parse( (string) ( $row[ $k ] ?? '' ), $spec, $label( $k ) );
			$errors       = array_merge( $errors, $parsed[ $k ]['errors'] );
		}
		$dest = $set( 'destination' ) && ! Cells::clear( $row['destination'] ) ? Cells::location( $row['destination'], array( 'destination' ) ) : 0;
		if ( $set( 'destination' ) && ! Cells::clear( $row['destination'] ) && ! $dest ) {
			/* translators: %s: name. */
			$errors[] = sprintf( __( 'Destination “%s” isn’t in Location Tree.', 'itour-excursions' ), $row['destination'] );
		}
		$days = $set( 'days' ) ? Cells::days( $row['days'] ) : array();
		if ( null === $days ) {
			$errors[] = __( 'Runs on: use day names like Mon, Wed, Fri, or any.', 'itour-excursions' );
		}
		$times = array();
		foreach ( $set( 'times' ) && ! Cells::clear( $row['times'] ) ? Cells::items( $row['times'] ) : array() as $t ) {
			$ok = self::time( $t );
			if ( null === $ok ) {
				/* translators: %s: value. */
				$errors[] = sprintf( __( 'Start times: “%s” isn’t a time like 04:00.', 'itour-excursions' ), $t );
			}
			$times[] = array( 'time' => (string) $ok );
		}
		$modes = $set( 'pickup_modes' ) && ! Cells::clear( $row['pickup_modes'] ) ? array_map( 'mb_strtolower', Cells::items( $row['pickup_modes'] ) ) : array();
		if ( array_diff( $modes, array( 'hotel', 'typed', 'meeting' ) ) ) {
			$errors[] = __( 'Pick-up: use hotel, typed and/or meeting.', 'itour-excursions' );
		}
		foreach ( array( 'deposit', 'cutoff', 'seats' ) as $k ) {
			if ( $set( $k ) && ! ctype_digit( $row[ $k ] ) ) {
				/* translators: %s: column. */
				$errors[] = sprintf( __( '%s must be a whole number.', 'itour-excursions' ), $label( $k ) );
			}
		}
		$errors = array_merge( $errors, Cells::info_import( Excursion::TYPE, 0, $row, false ) );
		$out    = array(
			'action' => $post['action'],
			'title'  => $post['title'],
			'errors' => $errors,
		);
		if ( ! $apply || $errors ) {
			return $out;
		}

		$post = Cells::post( Excursion::TYPE, $row, true );
		$id   = $post['id'];
		if ( $post['errors'] || ! $id ) {
			$out['errors'] = $post['errors'];
			return $out;
		}
		$c    = Excursion::config( $id );
		$val  = static fn( string $k, $cur ) => ! $set( $k ) ? $cur : ( Cells::clear( $row[ $k ] ) ? '' : $row[ $k ] );
		$list = static fn( string $k, $cur ) => ! $set( $k ) ? $cur : ( Cells::clear( $row[ $k ] ) ? array() : $parsed[ $k ]['rows'] );
		if ( $set( 'destination' ) || $set( 'duration' ) || $set( 'deposit' ) ) {
			Panel::save_section(
				Excursion::TYPE,
				'basics',
				$id,
				array(
					'destination' => $set( 'destination' ) ? $dest : $c['destination'],
					'duration'    => $val( 'duration', $c['duration'] ),
					'deposit'     => $val( 'deposit', $c['deposit'] ),
				)
			);
		}
		if ( $set( 'days' ) || $set( 'times' ) || $set( 'cutoff' ) || $set( 'seats' ) || $set( 'blocked' ) ) {
			Panel::save_section(
				Excursion::TYPE,
				'schedule',
				$id,
				array(
					'days'    => $set( 'days' ) ? $days : $c['days'],
					'times'   => $set( 'times' ) ? $times : array_map( static fn( $t ) => array( 'time' => $t ), (array) $c['times'] ),
					'cutoff'  => $val( 'cutoff', $c['cutoff'] ),
					'seats'   => $val( 'seats', $c['seats'] ),
					'blocked' => $list( 'blocked', $c['blocked'] ),
				)
			);
		}
		if ( $set( 'pickup_modes' ) || $set( 'zones' ) || $set( 'meeting_point' ) ) {
			Panel::save_section(
				Excursion::TYPE,
				'pickup',
				$id,
				array(
					'modes'         => $set( 'pickup_modes' ) ? $modes : $c['pickup_modes'],
					'zones'         => $list( 'zones', array_map( static fn( $p ) => array( 'pickup_time' => substr( (string) $p['pickup_time'], 0, 5 ) ) + $p, Excursion::pickups( $id ) ) ),
					'meeting_point' => $val( 'meeting_point', $c['meeting_point'] ),
				)
			);
		}
		foreach ( array( 'prices', 'addons', 'supplements' ) as $k ) {
			if ( $set( $k ) ) {
				Panel::save_section( Excursion::TYPE, $k, $id, array( 'rows' => Cells::clear( $row[ $k ] ) ? array() : $parsed[ $k ]['rows'] ) );
			}
		}
		Cells::info_import( Excursion::TYPE, $id, $row, true );
		return $out;
	}
}
