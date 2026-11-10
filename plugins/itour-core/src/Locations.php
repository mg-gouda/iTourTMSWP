<?php
/**
 * Location Tree: destinations → zones → hotels, airports, ports and meeting points. Set up once,
 * shared by every product (excursion pick-ups, transfer routes, tour itineraries). Own top-level menu.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

use ITour\Core\Admin\Crud;

defined( 'ABSPATH' ) || exit;

/**
 * The shared location list and its admin screen.
 */
final class Locations {

	/**
	 * Menu slug.
	 */
	private const PAGE = 'itour-locations';

	/**
	 * Hook the menu.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	/**
	 * Location types and the parent type each one sits under ('' = top level).
	 *
	 * @return array<string,array{0:string,1:string}> type => [ label, parent type ]
	 */
	public static function types(): array {
		return array(
			'destination' => array( __( 'Destination', 'itour-core' ), '' ),
			'zone'        => array( __( 'Zone', 'itour-core' ), 'destination' ),
			'hotel'       => array( __( 'Hotel', 'itour-core' ), 'zone' ),
			'airport'     => array( __( 'Airport', 'itour-core' ), 'zone' ),
			'port'        => array( __( 'Port', 'itour-core' ), 'zone' ),
			'meeting'     => array( __( 'Meeting point', 'itour-core' ), 'zone' ),
		);
	}

	/**
	 * Destinations and zones for the "Inside" select, e.g. "Hurghada › Sahl Hasheesh".
	 *
	 * @return array<string,string>
	 */
	public static function parent_options(): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, parent_id, type, name FROM %i WHERE type IN ('destination','zone') ORDER BY name", $wpdb->prefix . 'itour_locations' ), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( $rows as $id => $r ) {
			$out[ (string) $id ] = 'zone' === $r->type && isset( $rows[ $r->parent_id ] ) ? $rows[ $r->parent_id ]->name . ' › ' . $r->name : $r->name;
		}
		asort( $out );
		return $out;
	}

	/**
	 * Every active location as "Destination › Zone › Place" (for itinerary maps). Includes '' = none.
	 *
	 * @return array<string,string>
	 */
	public static function all_options(): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, parent_id, name FROM %i WHERE active = 1', $wpdb->prefix . 'itour_locations' ), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( $rows as $id => $r ) {
			$path = array( $r->name );
			for ( $p = $r->parent_id, $guard = 0; $p && isset( $rows[ $p ] ) && $guard < 5; $p = $rows[ $p ]->parent_id, $guard++ ) {
				array_unshift( $path, $rows[ $p ]->name );
			}
			$out[ (string) $id ] = implode( ' › ', $path );
		}
		asort( $out );
		return array( '' => '—' ) + $out;
	}

	/**
	 * Zones only, labelled "Destination › Zone" (for price tables).
	 *
	 * @return array<string,string>
	 */
	public static function zone_options(): array {
		global $wpdb;
		$zones = array_flip( (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE type = 'zone'", $wpdb->prefix . 'itour_locations' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_intersect_key( self::parent_options(), $zones );
	}

	/**
	 * Names of active zones and places, for search suggestions.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		global $wpdb;
		return array_values( array_unique( (array) $wpdb->get_col( $wpdb->prepare( "SELECT name FROM %i WHERE active = 1 AND type <> 'destination' ORDER BY name", $wpdb->prefix . 'itour_locations' ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Active location by exact name (case-insensitive), or null.
	 *
	 * @param string $name Name as typed.
	 */
	public static function find( string $name ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 AND name = %s ORDER BY id LIMIT 1', $wpdb->prefix . 'itour_locations', trim( $name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	/**
	 * The zone a location belongs to: itself for a zone, its parent for a place, 0 otherwise.
	 *
	 * @param object $loc Location row.
	 */
	public static function zone_of( object $loc ): int {
		if ( 'zone' === $loc->type ) {
			return (int) $loc->id;
		}
		return 'destination' === $loc->type ? 0 : (int) $loc->parent_id;
	}

	/**
	 * Add the "Location Tree" menu.
	 */
	public function menu(): void {
		$crud = $this->crud();
		$hook = add_menu_page( __( 'Location Tree', 'itour-core' ), __( 'Location Tree', 'itour-core' ), ( new CoreModule() )->capability(), self::PAGE, array( $crud, 'render' ), 'dashicons-location-alt', 4 );
		add_action( "load-{$hook}", array( $crud, 'load' ) );
	}

	/**
	 * The list + form.
	 */
	private function crud(): Crud {
		$types = self::types();
		return new Crud(
			array(
				'table'      => 'locations',
				'tree'       => 'parent_id',
				'singular'   => __( 'location', 'itour-core' ),
				'plural'     => __( 'Location Tree', 'itour-core' ),
				'capability' => ( new CoreModule() )->capability(),
				'order'      => 'name',
				'search'     => array( 'name', 'code', 'address' ),
				'empty'      => __( 'No locations yet. Add a destination (e.g. Hurghada), then its zones (e.g. Sahl Hasheesh, El Gouna), then the hotels and airports in each zone. Excursions, transfers and tours all use this list.', 'itour-core' ),
				'columns'    => array(
					'name'   => __( 'Name', 'itour-core' ),
					'type'   => __( 'Type', 'itour-core' ),
					'code'   => __( 'Code', 'itour-core' ),
					'active' => __( 'Active', 'itour-core' ),
				),
				'validate'   => static function ( array $row, string $id ) use ( $types ): array {
					global $wpdb;
					$need = $types[ $row['type'] ][1] ?? '';
					$has  = '' === (string) $row['parent_id'] ? '' : (string) $wpdb->get_var( $wpdb->prepare( 'SELECT type FROM %i WHERE id = %d', $wpdb->prefix . 'itour_locations', $row['parent_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					if ( '' === $need && '' !== $has ) {
						return array( 'parent_id' => __( 'A destination is top level: leave “Inside” empty.', 'itour-core' ) );
					}
					if ( '' !== $need && ( $need !== $has || (string) $row['parent_id'] === $id ) ) {
						return array(
							/* translators: 1: location type, e.g. "Hotel"; 2: parent type, e.g. "zone". */
							'parent_id' => sprintf( __( 'A %1$s must be inside a %2$s.', 'itour-core' ), strtolower( $types[ $row['type'] ][0] ), strtolower( $types[ $need ][0] ) ),
						);
					}
					return array();
				},
				'fields'     => array(
					array(
						'key'      => 'type',
						'label'    => __( 'Type', 'itour-core' ),
						'type'     => 'select',
						'required' => true,
						'options'  => array_map( static fn( $t ) => $t[0], $types ),
					),
					array(
						'key'      => 'name',
						'label'    => __( 'Name', 'itour-core' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'     => 'parent_id',
						'label'   => __( 'Inside', 'itour-core' ),
						'type'    => 'select',
						'options' => array( self::class, 'parent_options' ),
						'help'    => __( 'Zones go inside a destination; hotels, airports, ports and meeting points go inside a zone.', 'itour-core' ),
					),
					array(
						'key'       => 'code',
						'label'     => __( 'Code', 'itour-core' ),
						'type'      => 'text',
						'maxlength' => 8,
						'help'      => __( 'Optional short code, e.g. the airport code HRG.', 'itour-core' ),
					),
					array(
						'key'     => 'country',
						'label'   => __( 'Country', 'itour-core' ),
						'type'    => 'select',
						'options' => array( Company::class, 'country_options' ),
						'help'    => __( 'For destinations.', 'itour-core' ),
					),
					array(
						'key'   => 'address',
						'label' => __( 'Address', 'itour-core' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'lat',
						'label' => __( 'Latitude', 'itour-core' ),
						'type'  => 'decimal',
						'help'  => __( 'Optional. Used for maps; copy from Google Maps (right-click the place).', 'itour-core' ),
					),
					array(
						'key'   => 'lng',
						'label' => __( 'Longitude', 'itour-core' ),
						'type'  => 'decimal',
					),
					array(
						'key'     => 'active',
						'label'   => __( 'Active', 'itour-core' ),
						'type'    => 'checkbox',
						'default' => 1,
					),
				),
			)
		);
	}
}
