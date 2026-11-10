<?php
/**
 * Transfer prices bulk import / export: one route per row (From zone, To zone) with a price column per
 * vehicle. An empty price keeps what is saved; "-" removes that vehicle's price on the route.
 *
 * @package ITour\Transfers
 */

namespace ITour\Transfers;

use ITour\Core\Admin\Cells;
use ITour\Core\Admin\Import;
use ITour\Core\Locations;

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
			'transfer-prices',
			array(
				'title'   => __( 'Transfer prices', 'itour-transfers' ),
				'cap'     => $cap,
				'fill'    => __( 'One route per row: the two zones, then the price per vehicle (one way, per vehicle, in your base currency). Vehicles and zones must exist first (Vehicles, Location Tree). A route priced one way also serves the way back unless the return route has its own row.', 'itour-transfers' ),
				'columns' => self::columns(),
				'export'  => array( self::class, 'export' ),
				'row'     => array( self::class, 'row' ),
			)
		);
	}

	/**
	 * Columns: from, to, then one per vehicle.
	 *
	 * @return array<string,array<int,mixed>>
	 */
	private static function columns(): array {
		$cols = array(
			'from' => array( __( 'From zone', 'itour-transfers' ), __( 'A zone name from Location Tree.', 'itour-transfers' ), 'Hurghada City', 22 ),
			'to'   => array( __( 'To zone', 'itour-transfers' ), __( 'A zone name from Location Tree.', 'itour-transfers' ), 'Sahl Hasheesh', 22 ),
		);
		$eg   = array( 20, 30, 55, 80 );
		$i    = 0;
		foreach ( Module::vehicle_options() as $id => $name ) {
			/* translators: %s: vehicle name. */
			$cols[ 'v' . $id ] = array( sprintf( __( 'Price: %s', 'itour-transfers' ), $name ), __( 'One way, per vehicle. Empty = keep the saved price; - = no price for this vehicle.', 'itour-transfers' ), (string) ( $eg[ $i++ ] ?? '' ), 14 );
		}
		return $cols;
	}

	/**
	 * Every route as a row.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function export(): array {
		global $wpdb;
		$rows  = array();
		$zones = Locations::zone_options();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT from_zone, to_zone, vehicle_id, price FROM %i ORDER BY from_zone, to_zone', $wpdb->prefix . 'itour_transfer_rates' ) ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$k = $r->from_zone . '-' . $r->to_zone;
			if ( ! isset( $rows[ $k ] ) ) {
				$rows[ $k ] = array(
					'from' => $zones[ $r->from_zone ] ?? '',
					'to'   => $zones[ $r->to_zone ] ?? '',
				);
			}
			$rows[ $k ][ 'v' . $r->vehicle_id ] = (string) (float) $r->price;
		}
		return array_values( $rows );
	}

	/**
	 * Check one row; with $apply, save it.
	 *
	 * @param array<string,string> $row   Row.
	 * @param bool                 $apply Save.
	 * @return array{action:string,title:string,errors:string[]}
	 */
	public static function row( array $row, bool $apply ): array {
		global $wpdb;
		$errors = array();
		$zone   = array();
		foreach ( array( 'from', 'to' ) as $k ) {
			$zone[ $k ] = Cells::location( (string) ( $row[ $k ] ?? '' ), array( 'zone' ) );
			if ( ! $zone[ $k ] ) {
				/* translators: 1: column, 2: value. */
				$errors[] = sprintf( __( '%1$s “%2$s” isn’t a zone in Location Tree.', 'itour-transfers' ), self::columns()[ $k ][0], $row[ $k ] ?? '' );
			}
		}
		$prices = array();
		foreach ( $row as $k => $v ) {
			if ( ! preg_match( '/^v(\d+)$/', $k, $m ) || '' === $v ) {
				continue;
			}
			$n = Cells::number( $v );
			if ( ! Cells::clear( $v ) && ( null === $n || (float) $n < 0 ) ) {
				/* translators: 1: column, 2: value. */
				$errors[] = sprintf( __( '%1$s: “%2$s” isn’t a price.', 'itour-transfers' ), self::columns()[ $k ][0] ?? $k, $v );
				continue;
			}
			$prices[ (int) $m[1] ] = Cells::clear( $v ) ? null : (float) $n;
		}
		$table  = $wpdb->prefix . 'itour_transfer_rates';
		$exists = ! $errors && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE from_zone = %d AND to_zone = %d', $table, $zone['from'], $zone['to'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out    = array(
			'action' => $exists ? 'update' : 'create',
			'title'  => trim( ( $row['from'] ?? '' ) . ' → ' . ( $row['to'] ?? '' ) ),
			'errors' => $errors,
		);
		if ( ! $apply || $errors ) {
			return $out;
		}
		foreach ( $prices as $vehicle => $price ) {
			$where = array(
				'from_zone'  => $zone['from'],
				'to_zone'    => $zone['to'],
				'vehicle_id' => $vehicle,
			);
			$wpdb->delete( $table, $where ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( null !== $price ) {
				$wpdb->insert( $table, $where + array( 'price' => $price ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		return $out;
	}
}
