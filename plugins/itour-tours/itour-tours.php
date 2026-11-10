<?php
/**
 * Plugin Name:       iTour Tour Packages
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Multi-day tour packages: itineraries, departures, prices, availability and booking.
 * Version:           0.3.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-tours
 * Domain Path:       /languages
 *
 * @package ITour\Tours
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_TOURS_FILE', __FILE__ );

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Tours\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Tours\Module() );
	}
);
