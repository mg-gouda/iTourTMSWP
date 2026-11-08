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
		return '0.1.0';
	}

	/**
	 * Hook the booking-widget tab and the Theme Options tab.
	 */
	public function boot(): void {
		add_filter( 'itour_booking_widget_tabs', array( $this, 'booking_tab' ) );
		add_filter( 'wp_itourtms_option_sections', array( $this, 'options_tab' ) );
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
