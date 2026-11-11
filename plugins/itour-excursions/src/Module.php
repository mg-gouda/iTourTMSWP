<?php
/**
 * iTour Excursions module. Built from scratch; booking functionality is added once its rules are agreed.
 *
 * @package ITour\Excursions
 */

namespace ITour\Excursions;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Excursions module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Option holding this module's page choices (Theme Options → Excursions).
	 */
	public const PAGES = 'itour_excursions_pages';

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'excursions';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Excursions', 'itour-excursions' );
	}

	/**
	 * Module version.
	 */
	public function version(): string {
		return '0.3.0';
	}

	/**
	 * Hook the post type (own "Excursions" menu), its details box, the page display, the booking-widget
	 * tab and the Theme Options tab.
	 */
	public function boot(): void {
		add_action(
			'init',
			static function (): void {
				\ITour\Core\Products::register( Excursion::TYPE, 'excursion', __( 'Excursions', 'itour-excursions' ), __( 'excursion', 'itour-excursions' ), 'dashicons-tickets-alt', 7 );
			}
		);
		( new Editor() )->hooks();
		( new Booking() )->hooks();
		\ITour\Core\Layouts::register( Excursion::TYPE, array( Page::class, 'parts' ) );
		add_filter( 'itour_booking_widget_tabs', array( $this, 'booking_tab' ) );
		// Cards (core Cards): tab, destination, duration.
		add_filter( 'itour/card_types', static fn( $t ) => $t + array( 'excursions' => array( Excursion::TYPE, __( 'Day trips', 'itour-excursions' ), self::page_url( 'listing_page' ) ) ) );
		add_filter( 'itour/destination', static fn( $d, $post ) => Excursion::TYPE === get_post_type( $post ) ? (int) Excursion::config( (int) $post )['destination'] : $d, 10, 2 );
		// Search cards: the total for the searched group (cheapest trip type), with the trip's own rules.
		add_filter(
			'itour/quote',
			static function ( $total, $post, $date, $pax ) {
				if ( Excursion::TYPE !== get_post_type( $post ) ) {
					return $total;
				}
				$best = null;
				foreach ( array_keys( Excursion::variants() ) as $v ) {
					$p = Excursion::price( (int) $post, (string) $v, (string) $date, (int) $pax, 0, 0 );
					if ( $p && ( null === $best || (float) $p['total'] < $best ) ) {
						$best = (float) $p['total'];
					}
				}
				return $best;
			},
			10,
			4
		);
		add_filter( 'itour/card_facts', static fn( $f, $post ) => Excursion::TYPE === get_post_type( $post ) ? array_merge( $f, array( (string) Excursion::config( (int) $post )['duration'] ) ) : $f, 10, 2 );
		add_filter( 'wp_itourtms_option_sections', array( $this, 'options_tab' ) );
	}

	/**
	 * Module screens go under the Excursions menu.
	 *
	 * @return array<string,mixed>
	 */
	public function menu(): array {
		return array( 'parent' => 'edit.php?post_type=' . Excursion::TYPE );
	}

	/**
	 * Price rows and pick-up zones per excursion.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array(
			'excursion_rates'    => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  variant varchar(10) NOT NULL DEFAULT 'shared',
  season_from date NULL,
  season_to date NULL,
  pax_min smallint(5) unsigned NOT NULL DEFAULT 1,
  pax_max smallint(5) unsigned NOT NULL DEFAULT 0,
  adult decimal(12,2) NOT NULL,
  child decimal(12,2) NOT NULL DEFAULT 0,
  infant decimal(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY post_id (post_id)",
			'excursion_pickups'  => 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  zone_id bigint(20) unsigned NOT NULL,
  pickup_time time NOT NULL,
  extra decimal(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY post_id (post_id)',
			'excursion_bookings' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ref varchar(20) NOT NULL,
  order_id bigint(20) unsigned NOT NULL,
  post_id bigint(20) unsigned NOT NULL,
  title varchar(190) NOT NULL,
  variant varchar(10) NOT NULL,
  start_at datetime NOT NULL,
  adults smallint(5) unsigned NOT NULL,
  children smallint(5) unsigned NOT NULL DEFAULT 0,
  infants smallint(5) unsigned NOT NULL DEFAULT 0,
  pickup varchar(255) NULL,
  pickup_time time NULL,
  addons varchar(255) NULL,
  notes text NULL,
  total decimal(12,2) NOT NULL,
  paid_now decimal(12,2) NOT NULL,
  currency char(3) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ref (ref),
  KEY order_id (order_id),
  KEY slot (post_id,start_at)",
		);
	}

	/**
	 * Screens under the Excursions menu.
	 *
	 * @return array<string,array{title:string,callback:callable,load?:callable}>
	 */
	public function admin_pages(): array {
		$crud = Booking::screen( $this->capability() );
		return array(
			'bookings' => array(
				'title'    => __( 'Bookings', 'itour-excursions' ),
				'callback' => array( $crud, 'render' ),
				'load'     => array( $crud, 'load' ),
			),
			'import'   => Sheet::screen( 'edit_posts' )->page( __( 'Import / export', 'itour-excursions' ) ),
		);
	}

	/**
	 * New post type → refresh permalinks once.
	 *
	 * @param string $from Previous version.
	 */
	public function installed( string $from ): void {
		flush_rewrite_rules( false );
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
		$action             = self::page_url( 'results_page' );
		$action             = '' !== $action ? $action : self::page_url( 'listing_page' );
		$tabs['excursions'] = array(
			'label'  => __( 'Excursions', 'itour-excursions' ),
			'button' => __( 'Find excursions', 'itour-excursions' ),
			'action' => '' !== $action ? $action : home_url( '/' ),
			'fields' => array(
				array(
					'name'        => 'where',
					'label'       => __( 'Staying in', 'itour-excursions' ),
					'type'        => 'text',
					'placeholder' => __( 'Hurghada, Sharm, Luxor…', 'itour-excursions' ),
					'icon'        => 'pin',
				),
				array(
					'name'  => 'date',
					'label' => __( 'Date', 'itour-excursions' ),
					'type'  => 'date',
					'icon'  => 'calendar',
				),
				array(
					'name'    => 'pax',
					'label'   => __( 'Travellers', 'itour-excursions' ),
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
	 * Theme Options → Excursions: the pages this module uses (saved with the plugin, not the theme).
	 *
	 * @param array<string,mixed> $sections Option sections.
	 * @return array<string,mixed>
	 */
	public function options_tab( array $sections ): array {
		$sections['excursions'] = array(
			'title'       => __( 'Excursions', 'itour-excursions' ),
			'description' => __( 'Pages used by Excursions. Layout and booking settings appear here as the module is built.', 'itour-excursions' ),
			'store'       => self::PAGES,
			'fields'      => array(
				'listing_page' => array(
					'label'   => __( 'Excursions page', 'itour-excursions' ),
					'type'    => 'page',
					'default' => 0,
					'help'    => __( 'The page that lists all excursions and day trips.', 'itour-excursions' ),
				),
				'results_page' => array(
					'label'   => __( 'Search results page', 'itour-excursions' ),
					'type'    => 'page',
					'default' => 0,
					'help'    => __( 'Where the booking widget sends excursion searches. Leave empty to use the excursions page.', 'itour-excursions' ),
				),
			),
		);
		return $sections;
	}
}
