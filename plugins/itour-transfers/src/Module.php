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
		return '0.3.0';
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
		( new Booking() )->hooks();
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
			'transfer_bookings' => 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ref varchar(20) NOT NULL,
  order_id bigint(20) unsigned NOT NULL,
  vehicle_id bigint(20) unsigned NOT NULL,
  vehicle varchar(100) NOT NULL,
  from_place varchar(190) NOT NULL,
  to_place varchar(190) NOT NULL,
  pax smallint(5) unsigned NOT NULL,
  pickup_at datetime NOT NULL,
  flight varchar(20) NULL,
  return_at datetime NULL,
  return_flight varchar(20) NULL,
  child_seats smallint(5) unsigned NOT NULL DEFAULT 0,
  bags smallint(5) unsigned NOT NULL DEFAULT 0,
  notes text NULL,
  total decimal(12,2) NOT NULL,
  paid_now decimal(12,2) NOT NULL,
  currency char(3) NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ref (ref),
  KEY order_id (order_id),
  KEY pickup_at (pickup_at)',
		);
	}

	/**
	 * Own "Transfers" menu in the admin.
	 *
	 * @return array<string,mixed>
	 */
	public function menu(): array {
		return array(
			'title'    => __( 'Transfers', 'itour-transfers' ),
			'icon'     => 'dashicons-car',
			'position' => 8,
		);
	}

	/**
	 * Admin screens (Transfers menu).
	 *
	 * @return array<string,array{title:string,callback:callable,load?:callable}>
	 */
	public function admin_pages(): array {
		$bookings = $this->bookings_screen();
		$pages    = array(
			'bookings' => array(
				'title'    => __( 'Bookings', 'itour-transfers' ),
				'callback' => array( $bookings, 'render' ),
				'load'     => array( $bookings, 'load' ),
			),
		);
		foreach ( array( 'vehicles' => $this->vehicles_screen(), 'prices' => $this->prices_screen() ) as $slug => $crud ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			$pages[ $slug ] = array(
				'title'    => 'vehicles' === $slug ? __( 'Vehicles', 'itour-transfers' ) : __( 'Prices', 'itour-transfers' ),
				'callback' => array( $crud, 'render' ),
				'load'     => array( $crud, 'load' ),
			);
		}
		$pages['settings'] = array(
			'title'    => __( 'Rules', 'itour-transfers' ),
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
	 * Bookings (read-only; created at checkout, changed through the WooCommerce order).
	 */
	private function bookings_screen(): Crud {
		$text = static fn( string $key, string $label ): array => array(
			'key'   => $key,
			'label' => $label,
			'type'  => 'text',
		);
		return new Crud(
			array(
				'table'      => 'transfer_bookings',
				'singular'   => __( 'transfer booking', 'itour-transfers' ),
				'plural'     => __( 'Transfer bookings', 'itour-transfers' ),
				'capability' => $this->capability(),
				'order'      => 'pickup_at',
				'order_desc' => true,
				'readonly'   => true,
				'can_add'    => false,
				'can_delete' => false,
				'search'     => array( 'ref', 'from_place', 'to_place', 'flight' ),
				'empty'      => __( 'No transfer bookings yet. They appear here when a customer checks out.', 'itour-transfers' ),
				'columns'    => array(
					'ref'       => __( 'Booking', 'itour-transfers' ),
					'pickup_at' => __( 'Pick-up', 'itour-transfers' ),
					'route'     => __( 'Route', 'itour-transfers' ),
					'vehicle'   => __( 'Vehicle', 'itour-transfers' ),
					'pax'       => __( 'Passengers', 'itour-transfers' ),
					'status'    => __( 'Order status', 'itour-transfers' ),
				),
				'cell'       => static function ( string $col, array $row ): ?string {
					if ( 'route' === $col ) {
						return $row['from_place'] . ' → ' . $row['to_place'] . ( $row['return_at'] ? ' ⇄' : '' );
					}
					if ( 'status' === $col ) {
						$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $row['order_id'] ) : null;
						return $order ? wc_get_order_status_name( $order->get_status() ) . ' (#' . $order->get_order_number() . ')' : '—';
					}
					return null;
				},
				'on_view'    => static function ( string $id ): void {
					global $wpdb;
					$order_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM %i WHERE id = %d', $wpdb->prefix . 'itour_transfer_bookings', $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$order    = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
					if ( $order ) {
						echo '<p><a class="button" href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Open the order (customer, payment, status)', 'itour-transfers' ) . '</a></p>';
					}
				},
				'fields'     => array(
					$text( 'ref', __( 'Booking', 'itour-transfers' ) ),
					$text( 'from_place', __( 'From', 'itour-transfers' ) ),
					$text( 'to_place', __( 'To', 'itour-transfers' ) ),
					$text( 'pickup_at', __( 'Pick-up', 'itour-transfers' ) ),
					$text( 'flight', __( 'Flight number', 'itour-transfers' ) ),
					$text( 'return_at', __( 'Return pick-up', 'itour-transfers' ) ),
					$text( 'return_flight', __( 'Return flight number', 'itour-transfers' ) ),
					$text( 'vehicle', __( 'Vehicle', 'itour-transfers' ) ),
					$text( 'pax', __( 'Passengers', 'itour-transfers' ) ),
					$text( 'child_seats', __( 'Child seats', 'itour-transfers' ) ),
					$text( 'bags', __( 'Bags', 'itour-transfers' ) ),
					array(
						'key'   => 'notes',
						'label' => __( 'Notes', 'itour-transfers' ),
						'type'  => 'textarea',
					),
					$text( 'total', __( 'Total', 'itour-transfers' ) ),
					$text( 'paid_now', __( 'Paid at checkout', 'itour-transfers' ) ),
					$text( 'currency', __( 'Currency', 'itour-transfers' ) ),
				),
			)
		);
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
	 * Full price of one booking. Extras (child seats, bags above the vehicle's limit) are charged per leg;
	 * the return leg uses the reverse route's price less the return discount. Null if the vehicle
	 * doesn't serve this route for this many passengers.
	 *
	 * @param int  $vehicle Vehicle id.
	 * @param int  $from    Zone id.
	 * @param int  $to      Zone id.
	 * @param int  $pax     Passengers.
	 * @param bool $back    Return trip too.
	 * @param int  $seats   Child seats.
	 * @param int  $bags    Bags.
	 * @return array{vehicle:object,outbound:float,return:float,extras:float,total:float}|null
	 */
	public static function price( int $vehicle, int $from, int $to, int $pax, bool $back = false, int $seats = 0, int $bags = 0 ): ?array {
		$pick = static function ( array $quotes ) use ( $vehicle ): ?array {
			foreach ( $quotes as $q ) {
				if ( (int) $q['vehicle']->id === $vehicle ) {
					return $q;
				}
			}
			return null;
		};

		$out = $pick( self::quote( $from, $to, $pax ) );
		if ( ! $out ) {
			return null;
		}
		$rules  = Settings::get();
		$legs   = $back ? 2 : 1;
		$extras = $legs * ( $seats * (float) $rules['child_seat_price'] + max( 0, $bags - (int) $out['vehicle']->max_bags ) * (float) $rules['extra_bag_price'] );
		$ret    = 0.0;
		if ( $back ) {
			$rev = $pick( self::quote( $to, $from, $pax ) );
			$ret = round( ( $rev ? $rev['price'] : $out['price'] ) * ( 1 - (float) $rules['return_discount'] / 100 ), 2 );
		}
		return array(
			'vehicle'  => $out['vehicle'],
			'outbound' => $out['price'],
			'return'   => $ret,
			'extras'   => round( $extras, 2 ),
			'total'    => round( $out['price'] + $ret + $extras, 2 ),
		);
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
