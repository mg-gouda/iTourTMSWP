<?php
/**
 * Plugin Name:       iTour Nile Cruises
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Nile cruises: boats, cabins, itineraries, departures, cruise contracts, allotments and bookings.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-nile-cruises
 * Domain Path:       /languages
 *
 * @package ITour\NileCruises
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\NileCruises\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\NileCruises\Module() );
	}
);
