<?php
/**
 * Booking widget: one block, three models (tabs, bar, card).
 *
 * Product plugins add their tab with the `itour_booking_widget_tabs` filter:
 *
 *   add_filter( 'itour_booking_widget_tabs', function ( $tabs ) {
 *       $tabs['tours'] = array(
 *           'label'  => 'Tour packages',
 *           'action' => home_url( '/tours/' ),   // GET target (results page)
 *           'button' => 'Find tours',
 *           'fields' => array(
 *               array( 'name' => 'where', 'label' => 'Destination', 'type' => 'text', 'placeholder' => 'Cairo, Luxor…', 'icon' => 'pin' ),
 *               array( 'name' => 'date',  'label' => 'Start date',  'type' => 'date', 'icon' => 'calendar' ),
 *               array( 'name' => 'pax',   'label' => 'Travellers',  'type' => 'number', 'min' => 1, 'default' => 2, 'icon' => 'people' ),
 *           ),
 *       );
 *       return $tabs;
 *   } );
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the block and exposes the tab registry.
 */
final class BookingWidget {

	public const MODELS = array( 'tabs', 'bar', 'card' );

	/**
	 * Hook the block.
	 */
	public function hooks(): void {
		add_action(
			'init',
			static function (): void {
				register_block_type( dirname( ITOUR_CORE_FILE ) . '/blocks/booking-widget' );
			}
		);
	}

	/**
	 * Tabs from the active product plugins, in a stable order.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function tabs(): array {
		$tabs  = (array) apply_filters( 'itour_booking_widget_tabs', array() );
		$order = array_flip( array( 'tours', 'excursions', 'transfers' ) );
		uksort( $tabs, static fn( $a, $b ) => array( $order[ $a ] ?? 99, $a ) <=> array( $order[ $b ] ?? 99, $b ) );
		return $tabs;
	}

	/**
	 * The model to use: the block's choice, or the site default (the theme's design sets it).
	 *
	 * @param string $chosen Block attribute.
	 */
	public static function model( string $chosen ): string {
		if ( in_array( $chosen, self::MODELS, true ) ) {
			return $chosen;
		}
		$default = (string) apply_filters( 'itour_booking_widget_default_model', 'tabs' );
		return in_array( $default, self::MODELS, true ) ? $default : 'tabs';
	}

	/**
	 * A small inline icon for a field.
	 *
	 * @param string $name pin|calendar|clock|people|car|search.
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'pin'      => 'M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z',
			'calendar' => 'M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2zm-2 7h14v10H5z',
			'clock'    => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 5v5.4l3.5 2.1-.8 1.3L11 13V7z',
			'people'   => 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM1 20c0-3.3 3.6-6 8-6s8 2.7 8 6v1H1zm16.5-5.9c2.9.3 5.5 2.4 5.5 4.9v1h-4v-1c0-1.9-.6-3.6-1.5-4.9z',
			'car'      => 'M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11a2 2 0 0 1 2 2v4h-2v2h-2v-2H7v2H5v-2H3v-4a2 2 0 0 1 2-2zm2.1 0h9.8l-1-3H8.1zM6.5 15a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm11 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z',
			'search'   => 'M10 2a8 8 0 0 1 6.3 12.9l5.4 5.4-1.4 1.4-5.4-5.4A8 8 0 1 1 10 2zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12z',
		);
		return isset( $paths[ $name ] ) ? '<svg class="itour-bw__icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . esc_attr( $paths[ $name ] ) . '"/></svg>' : '';
	}
}
