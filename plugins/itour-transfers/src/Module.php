<?php
/**
 * iTour Transfers module. Built from scratch; booking functionality is added once its rules are agreed.
 *
 * @package ITour\Transfers
 */

namespace ITour\Transfers;

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
		$action            = self::page_url( 'results_page' );
		$action            = '' !== $action ? $action : self::page_url( 'listing_page' );
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
				),
				array(
					'name'        => 'to',
					'label'       => __( 'To', 'itour-transfers' ),
					'type'        => 'text',
					'placeholder' => __( 'Hotel or address', 'itour-transfers' ),
					'icon'        => 'car',
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
			'description' => __( 'Pages used by Transfers. Layout and booking settings appear here as the module is built.', 'itour-transfers' ),
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
					'help'    => __( 'Where the booking widget sends transfer searches (vehicles and prices). Leave empty to use the transfers page.', 'itour-transfers' ),
				),
			),
		);
		return $sections;
	}
}
