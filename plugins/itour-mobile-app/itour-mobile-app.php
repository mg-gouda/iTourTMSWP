<?php
/**
 * Plugin Name:       iTour Mobile App
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       On phones (up to 767px) the site behaves like an app: a bottom tab bar (Explore, Tours, Day trips, Transfers, More) and a "More" sheet with the account, contact buttons and the main menu. Tour and day-trip pages keep their own "Book" bar instead; cart and checkout show no bar. Tablets and desktops are unchanged. Made for the WP iTourTMS theme.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-mobile-app
 *
 * @package ITour\MobileApp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Line icon (24px grid, 1.8 stroke, like the theme's).
 *
 * @param string $name Icon.
 */
function itour_mobile_icon( string $name ): string {
	$paths = array(
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'map'      => '<path d="M9 4 3 6.5v13.5l6-2.5 6 2.5 6-2.5V4l-6 2.5L9 4Z"/><path d="M9 4v13.5M15 6.5V20"/>',
		'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>',
		'car'      => '<path d="M5 17H3.5a1 1 0 0 1-1-1v-3.5c0-.6.2-1.1.6-1.5L5 9l1.6-3.2A1.5 1.5 0 0 1 8 5h8a1.5 1.5 0 0 1 1.4.8L19 9l1.9 2c.4.4.6.9.6 1.5V16a1 1 0 0 1-1 1H19"/><path d="M5 9h14"/><circle cx="7.5" cy="17" r="2"/><circle cx="16.5" cy="17" r="2"/><path d="M9.5 17h5"/>',
		'more'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'chev'     => '<path d="m9 6 6 6-6 6"/>',
		'phone'    => '<path d="M21 16.4v2.8a1.8 1.8 0 0 1-2 1.8A17.8 17.8 0 0 1 3 5a1.8 1.8 0 0 1 1.8-2h2.8a1.8 1.8 0 0 1 1.8 1.5c.1.9.4 1.7.7 2.5a1.8 1.8 0 0 1-.4 1.9L8.5 10.1a14.4 14.4 0 0 0 5.4 5.4l1.2-1.2a1.8 1.8 0 0 1 1.9-.4c.8.3 1.6.6 2.5.7a1.8 1.8 0 0 1 1.5 1.8Z"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
		'whatsapp' => '<path d="M20.5 11.6a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5 5 16.2a8.5 8.5 0 1 1 15.5-4.6Z"/><path d="M9 8.5c0 3.3 3.2 6.5 6.5 6.5l1-1.6-2-1-1 .8a5 5 0 0 1-2.2-2.2l.8-1-1-2L9 8.5Z"/>',
	);
	return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}

/**
 * Bar shown on this page? Not in wp-admin, cart or checkout (it would cover the pay button), nor on
 * tour / day-trip pages, which have their own "Book" bar at the bottom.
 */
function itour_mobile_tabbar(): bool {
	if ( is_admin() || ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) ) {
		return false;
	}
	return ! is_singular( array( 'itour_tour', 'itour_excursion' ) );
}

/**
 * Tabs: key => [ url, icon, label ]. Modules that are not active, or have no listing page chosen, drop out.
 *
 * @return array<string,array{0:string,1:string,2:string}>
 */
function itour_mobile_tabs(): array {
	$tabs = array( 'explore' => array( home_url( '/' ), 'search', __( 'Explore', 'itour-mobile-app' ) ) );
	$mods = array(
		'tours'     => array( '\ITour\Tours\Module', 'map', __( 'Tours', 'itour-mobile-app' ) ),
		'trips'     => array( '\ITour\Excursions\Module', 'sun', __( 'Day trips', 'itour-mobile-app' ) ),
		'transfers' => array( '\ITour\Transfers\Module', 'car', __( 'Transfers', 'itour-mobile-app' ) ),
	);
	foreach ( $mods as $key => $m ) {
		$url = class_exists( $m[0] ) ? (string) call_user_func( array( $m[0], 'page_url' ), 'listing_page' ) : '';
		if ( '' !== $url ) {
			$tabs[ $key ] = array( $url, $m[1], $m[2] );
		}
	}
	return $tabs;
}

/**
 * The tab of the current page ('' = none).
 *
 * @param array<string,array{0:string,1:string,2:string}> $tabs Tabs.
 */
function itour_mobile_active( array $tabs ): string {
	if ( is_front_page() ) {
		return 'explore';
	}
	$here = untrailingslashit( (string) strtok( home_url( add_query_arg( array() ) ), '?' ) );
	foreach ( $tabs as $key => $t ) {
		if ( 'explore' !== $key && untrailingslashit( $t[0] ) === $here ) {
			return $key;
		}
	}
	return '';
}

add_filter(
	'body_class',
	static function ( $classes ) {
		if ( itour_mobile_tabbar() ) {
			$classes[] = 'itour-tabs';
		}
		return $classes;
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! itour_mobile_tabbar() ) {
			return;
		}
		$url = plugin_dir_url( __FILE__ );
		wp_enqueue_style( 'itour-mobile-app', $url . 'assets/mobile.css', array(), '0.1.0' );
		wp_enqueue_script( 'itour-mobile-app', $url . 'assets/mobile.js', array(), '0.1.0', array( 'strategy' => 'defer' ) );
	}
);

add_action(
	'wp_footer',
	static function (): void {
		if ( ! itour_mobile_tabbar() ) {
			return;
		}
		$tabs   = itour_mobile_tabs();
		$active = itour_mobile_active( $tabs );
		require __DIR__ . '/templates/bar.php';
	},
	50
);
