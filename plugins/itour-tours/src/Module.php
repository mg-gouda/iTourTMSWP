<?php
/**
 * iTour Tour Packages module. Built from scratch; booking functionality is added once its rules are agreed.
 *
 * @package ITour\Tours
 */

namespace ITour\Tours;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Tour Packages module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Option holding this module's page choices (Theme Options → Tour Packages).
	 */
	public const PAGES = 'itour_tours_pages';

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'tours';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Tour Packages', 'itour-tours' );
	}

	/**
	 * Module version.
	 */
	public function version(): string {
		return '0.3.0';
	}

	/**
	 * Price rows per tour package.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array(
			'tour_rates' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  hotel_class varchar(10) NOT NULL DEFAULT '',
  season_from date NULL,
  season_to date NULL,
  pax_min smallint(5) unsigned NOT NULL DEFAULT 1,
  pax_max smallint(5) unsigned NOT NULL DEFAULT 0,
  adult decimal(12,2) NOT NULL,
  child decimal(12,2) NOT NULL DEFAULT 0,
  infant decimal(12,2) NOT NULL DEFAULT 0,
  single_supp decimal(12,2) NOT NULL DEFAULT 0,
  triple_red decimal(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY post_id (post_id)",
		);
	}

	/**
	 * Hook the post type (own "Tour Packages" menu), the booking-widget tab and the Theme Options tab.
	 */
	public function boot(): void {
		add_action(
			'init',
			static function (): void {
				\ITour\Core\Products::register( Tour::TYPE, 'tour', __( 'Tour Packages', 'itour-tours' ), __( 'tour package', 'itour-tours' ), 'dashicons-admin-site-alt3', 6 );
			}
		);
		( new Editor() )->hooks();
		\ITour\Core\Layouts::register( Tour::TYPE, array( Page::class, 'parts' ) );
		add_filter( 'itour_booking_widget_tabs', array( $this, 'booking_tab' ) );
		add_filter( 'wp_itourtms_option_sections', array( $this, 'options_tab' ) );
		// Cards (core Cards): tab, destination, "4 days".
		add_filter( 'itour/card_types', static fn( $t ) => $t + array( 'tours' => array( Tour::TYPE, __( 'Tour packages', 'itour-tours' ), self::page_url( 'listing_page' ) ) ) );
		add_filter( 'itour/destination', static fn( $d, $post ) => Tour::TYPE === get_post_type( $post ) ? (int) Tour::config( (int) $post )['destination'] : $d, 10, 2 );
		// Search cards: the total for the searched group (cheapest hotel class), with the tour's own rules.
		add_filter(
			'itour/quote',
			static function ( $total, $post, $date, $pax ) {
				if ( Tour::TYPE !== get_post_type( $post ) ) {
					return $total;
				}
				$classes = array_unique( array_map( 'strval', array_column( Tour::rates( (int) $post ), 'hotel_class' ) ) );
				$best    = null;
				foreach ( $classes as $stars ) {
					$p = Tour::price( (int) $post, $stars, (string) $date, (int) $pax, 0, 0 );
					if ( $p && ( null === $best || (float) $p['total'] < $best ) ) {
						$best = (float) $p['total'];
					}
				}
				return $best;
			},
			10,
			4
		);
		add_filter(
			'itour/card_facts',
			static function ( $facts, $post ) {
				if ( Tour::TYPE === get_post_type( $post ) ) {
					$days = (int) Tour::config( (int) $post )['days'];
					/* translators: %d: number of days. */
					$facts[] = sprintf( _n( '%d day', '%d days', $days, 'itour-tours' ), $days );
				}
				return $facts;
			},
			10,
			2
		);
	}

	/**
	 * Module screens go under the Tour Packages menu.
	 *
	 * @return array<string,mixed>
	 */
	public function menu(): array {
		return array( 'parent' => 'edit.php?post_type=' . Tour::TYPE );
	}

	/**
	 * Import / export.
	 *
	 * @return array<string,array{title:string,callback:callable,load?:callable}>
	 */
	public function admin_pages(): array {
		return array( 'import' => Sheet::screen( 'edit_posts' )->page( __( 'Import / export', 'itour-tours' ) ) );
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
		$action        = self::page_url( 'results_page' );
		$action        = '' !== $action ? $action : self::page_url( 'listing_page' );
		$tabs['tours'] = array(
			'label'  => __( 'Tour packages', 'itour-tours' ),
			'button' => __( 'Find tours', 'itour-tours' ),
			'action' => '' !== $action ? $action : home_url( '/' ),
			'fields' => array(
				array(
					'name'        => 'where',
					'label'       => __( 'Destination', 'itour-tours' ),
					'type'        => 'text',
					'placeholder' => __( 'Cairo, Luxor, Nile cruise…', 'itour-tours' ),
					'icon'        => 'pin',
				),
				array(
					'name'  => 'date',
					'label' => __( 'Start date', 'itour-tours' ),
					'type'  => 'date',
					'icon'  => 'calendar',
				),
				array(
					'name'    => 'pax',
					'label'   => __( 'Travellers', 'itour-tours' ),
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
	 * Theme Options → Tour Packages: the pages this module uses (saved with the plugin, not the theme).
	 *
	 * @param array<string,mixed> $sections Option sections.
	 * @return array<string,mixed>
	 */
	public function options_tab( array $sections ): array {
		$sections['tours'] = array(
			'title'       => __( 'Tour Packages', 'itour-tours' ),
			'description' => __( 'Pages used by Tour Packages. Layout and booking settings appear here as the module is built.', 'itour-tours' ),
			'store'       => self::PAGES,
			'fields'      => array(
				'listing_page' => array(
					'label'   => __( 'Tour packages page', 'itour-tours' ),
					'type'    => 'page',
					'default' => 0,
					'help'    => __( 'The page that lists all tour packages.', 'itour-tours' ),
				),
				'results_page' => array(
					'label'   => __( 'Search results page', 'itour-tours' ),
					'type'    => 'page',
					'default' => 0,
					'help'    => __( 'Where the booking widget sends tour searches. Leave empty to use the tour packages page.', 'itour-tours' ),
				),
			),
		);
		return $sections;
	}
}
