<?php
/**
 * iTour Transfers: private transfers priced zone to zone per vehicle (rules: docs/PRODUCTS.md).
 * Places and zones come from the core Location Tree.
 *
 * @package ITour\Transfers
 */

namespace ITour\Transfers;

use ITour\Core\Admin\Crud;
use ITour\Core\Locations;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Transfers module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Option holding this module's page choices (Theme Options → Transfers).
	 */
	public const PAGES = 'itour_transfers_pages';

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'transfers';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Transfers', 'itour-transfers' );
	}

	/**
	 * Module version.
	 */
	public function version(): string {
		return '0.2.0';
	}

	/**
	 * Hook the booking-widget tab, the Theme Options tab and the results block.
	 */
	public function boot(): void {
		add_filter( 'itour_booking_widget_tabs', array( $this, 'booking_tab' ) );
		add_filter( 'wp_itourtms_option_sections', array( $this, 'options_tab' ) );
		add_action(
			'init',
			static function (): void {
				register_block_type( dirname( ITOUR_TRANSFERS_FILE ) . '/blocks/transfer-results' );
			}
		);
	}

	/**
	 * Vehicles and the zone-to-zone price table.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array(
			'transfer_vehicles' => 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  max_pax smallint(5) unsigned NOT NULL DEFAULT 3,
  max_bags smallint(5) unsigned NOT NULL DEFAULT 3,
  sort smallint(5) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id)',
			'transfer_rates'    => 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  from_zone bigint(20) unsigned NOT NULL,
  to_zone bigint(20) unsigned NOT NULL,
  vehicle_id bigint(20) unsigned NOT NULL,
  price decimal(12,2) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY route (from_zone,to_zone,vehicle_id)',
		);
	}

	/**
	 * Admin screens under iTour.
	 *
	 * @return array<string,array{title:string,callback:callable,load?:callable}>
	 */
	public function admin_pages(): array {
		$pages = array();
		foreach ( array( 'vehicles' => $this->vehicles_screen(), 'prices' => $this->prices_screen() ) as $slug => $crud ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			$pages[ $slug ] = array(
				'title'    => 'vehicles' === $slug ? __( 'Transfer vehicles', 'itour-transfers' ) : __( 'Transfer prices', 'itour-transfers' ),
				'callback' => array( $crud, 'render' ),
				'load'     => array( $crud, 'load' ),
			);
		}
		$pages['settings'] = array(
			'title'    => __( 'Transfer rules', 'itour-transfers' ),
			'callback' => array( Settings::class, 'render' ),
			'load'     => array( Settings::class, 'load' ),
		);
		return $pages;
	}

	/**
	 * Active vehicles: id => name.
	 *
	 * @return array<string,string>
	 */
	public static function vehicle_options(): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, name FROM %i ORDER BY sort, name', $wpdb->prefix . 'itour_transfer_vehicles' ), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( static fn( $r ) => $r->name, $rows );
	}

	/**
	 * Vehicles screen.
	 */
	private function vehicles_screen(): Crud {
		return new Crud(
			array(
				'table'      => 'transfer_vehicles',
				'singular'   => __( 'vehicle', 'itour-transfers' ),
				'plural'     => __( 'Transfer vehicles', 'itour-transfers' ),
				'capability' => $this->capability(),
				'order'      => 'sort',
				'empty'      => __( 'No vehicles yet. Add the cars you sell, e.g. Sedan (3 passengers), Van (7), Minibus (14), Bus (28).', 'itour-transfers' ),
				'columns'    => array(
					'name'     => __( 'Vehicle', 'itour-transfers' ),
					'max_pax'  => __( 'Passengers', 'itour-transfers' ),
					'max_bags' => __( 'Bags', 'itour-transfers' ),
					'active'   => __( 'Active', 'itour-transfers' ),
				),
				'fields'     => array(
					array(
						'key'      => 'name',
						'label'    => __( 'Name', 'itour-transfers' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'      => 'max_pax',
						'label'    => __( 'Maximum passengers', 'itour-transfers' ),
						'type'     => 'number',
						'required' => true,
						'default'  => 3,
					),
					array(
						'key'     => 'max_bags',
						'label'   => __( 'Bags included', 'itour-transfers' ),
						'type'    => 'number',
						'default' => 3,
						'help'    => __( 'Bags above this pay the extra luggage fee (Transfer rules).', 'itour-transfers' ),
					),
					array(
						'key'     => 'sort',
						'label'   => __( 'Order', 'itour-transfers' ),
						'type'    => 'number',
						'default' => 0,
						'help'    => __( 'Lower numbers show first.', 'itour-transfers' ),
					),
					array(
						'key'     => 'active',
						'label'   => __( 'Active', 'itour-transfers' ),
						'type'    => 'checkbox',
						'default' => 1,
					),
				),
			)
		);
	}

	/**
	 * Price table screen: one row per route and vehicle.
	 */
	private function prices_screen(): Crud {
		return new Crud(
			array(
				'table'      => 'transfer_rates',
				'singular'   => __( 'price', 'itour-transfers' ),
				'plural'     => __( 'Transfer prices', 'itour-transfers' ),
				'capability' => $this->capability(),
				'order'      => 'from_zone',
				'empty'      => __( 'No prices yet. Set up zones under Location Tree and vehicles under Transfer vehicles, then add a price per route and vehicle. A price works both ways unless you add the return route separately.', 'itour-transfers' ),
				'columns'    => array(
					'from_zone'  => __( 'From zone', 'itour-transfers' ),
					'to_zone'    => __( 'To zone', 'itour-transfers' ),
					'vehicle_id' => __( 'Vehicle', 'itour-transfers' ),
					'price'      => __( 'Price per vehicle', 'itour-transfers' ),
				),
				'validate'   => static function ( array $row, string $id ): array {
					global $wpdb;
					$clash = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE from_zone = %d AND to_zone = %d AND vehicle_id = %d AND id <> %d', $wpdb->prefix . 'itour_transfer_rates', $row['from_zone'], $row['to_zone'], $row['vehicle_id'], (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					return $clash ? array( 'vehicle_id' => __( 'This route already has a price for this vehicle.', 'itour-transfers' ) ) : array();
				},
				'fields'     => array(
					array(
						'key'      => 'from_zone',
						'label'    => __( 'From zone', 'itour-transfers' ),
						'type'     => 'select',
						'required' => true,
						'options'  => array( Locations::class, 'zone_options' ),
					),
					array(
						'key'      => 'to_zone',
						'label'    => __( 'To zone', 'itour-transfers' ),
						'type'     => 'select',
						'required' => true,
						'options'  => array( Locations::class, 'zone_options' ),
					),
					array(
						'key'      => 'vehicle_id',
						'label'    => __( 'Vehicle', 'itour-transfers' ),
						'type'     => 'select',
						'required' => true,
						'options'  => array( self::class, 'vehicle_options' ),
					),
					array(
						'key'      => 'price',
						'label'    => __( 'Price per vehicle', 'itour-transfers' ),
						'type'     => 'decimal',
						'required' => true,
						'help'     => __( 'One way, in your base currency (iTour → Company).', 'itour-transfers' ),
					),
				),
			)
		);
	}

	/**
	 * Vehicles that fit the group on a route, cheapest first. A route priced only the other way round
	 * uses that price.
	 *
	 * @param int $from Zone id.
	 * @param int $to   Zone id.
	 * @param int $pax  Passengers (infants included: they need a seat).
	 * @return array<int,array{vehicle:object,price:float}>
	 */
	public static function quote( int $from, int $to, int $pax ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT v.*, r.price, r.from_zone FROM %i r JOIN %i v ON v.id = r.vehicle_id
				WHERE v.active = 1 AND v.max_pax >= %d AND ( ( r.from_zone = %d AND r.to_zone = %d ) OR ( r.from_zone = %d AND r.to_zone = %d ) )
				ORDER BY r.price',
				$wpdb->prefix . 'itour_transfer_rates',
				$wpdb->prefix . 'itour_transfer_vehicles',
				$pax,
				$from,
				$to,
				$to,
				$from
			)
		);

		$out = array();
		foreach ( $rows as $r ) {
			// The exact direction wins over the reverse one.
			if ( ! isset( $out[ $r->id ] ) || (int) $r->from_zone === $from ) {
				$out[ $r->id ] = array(
					'vehicle' => $r,
					'price'   => (float) $r->price,
				);
			}
		}
		usort( $out, static fn( $a, $b ) => $a['price'] <=> $b['price'] );
		return $out;
	}

	/**
	 * URL a saved page choice points to ('' when unset).
	 *
	 * @param string $key Page field.
	 */
	public static function page_url( string $key ): string {
		$id = (int) ( ( (array) get_option( self::PAGES, array() ) )[ $key ] ?? 0 );
		return $id ? (string) get_permalink( $id ) : '';
	}

	/**
	 * This module's search in the booking widget.
	 *
	 * @param array<string,array<string,mixed>> $tabs Tabs so far.
	 * @return array<string,array<string,mixed>>
	 */
	public function booking_tab( array $tabs ): array {
		$action            = self::page_url( 'results_page' );
		$action            = '' !== $action ? $action : self::page_url( 'listing_page' );
		$places            = Locations::names();
		$tabs['transfers'] = array(
			'label'  => __( 'Transfers', 'itour-transfers' ),
			'button' => __( 'See prices', 'itour-transfers' ),
			'action' => '' !== $action ? $action : home_url( '/' ),
			'fields' => array(
				array(
					'name'        => 'from',
					'label'       => __( 'From', 'itour-transfers' ),
					'type'        => 'text',
					'placeholder' => __( 'Airport, hotel or city', 'itour-transfers' ),
					'icon'        => 'pin',
					'list'        => $places,
				),
				array(
					'name'        => 'to',
					'label'       => __( 'To', 'itour-transfers' ),
					'type'        => 'text',
					'placeholder' => __( 'Hotel or address', 'itour-transfers' ),
					'icon'        => 'car',
					'list'        => $places,
				),
				array(
					'name'  => 'date',
					'label' => __( 'Pick-up date', 'itour-transfers' ),
					'type'  => 'date',
					'icon'  => 'calendar',
				),
				array(
					'name'    => 'pax',
					'label'   => __( 'Passengers', 'itour-transfers' ),
					'type'    => 'number',
					'min'     => 1,
					'default' => 2,
					'icon'    => 'people',
				),
			),
		);
		return $tabs;
	}

	/**
	 * Theme Options → Transfers: the pages this module uses (saved with the plugin, not the theme).
	 *
	 * @param array<string,mixed> $sections Option sections.
	 * @return array<string,mixed>
	 */
	public function options_tab( array $sections ): array {
		$sections['transfers'] = array(
			'title'       => __( 'Transfers', 'itour-transfers' ),
			'description' => __( 'Pages used by Transfers. Prices, vehicles and booking rules are under the iTour menu.', 'itour-transfers' ),
			'store'       => self::PAGES,
			'fields'      => array(
				'listing_page' => array(
					'label'   => __( 'Transfers page', 'itour-transfers' ),
					'type'    => 'page',
					'default' => 0,
					'help'    => __( 'The page that explains and books transfers.', 'itour-transfers' ),
				),
				'results_page' => array(
					'label'   => __( 'Search results page', 'itour-transfers' ),
					'type'    => 'page',
					'default' => 0,
					'help'    => __( 'Where the booking widget sends transfer searches. Put the “Transfer results” block on it. Leave empty to use the transfers page.', 'itour-transfers' ),
				),
			),
		);
		return $sections;
	}
}
