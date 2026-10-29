<?php
/**
 * Plugin Name:       iTour Traffic
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Transfers: zones, vehicles, airports, prices, guest transfer bookings, jobs, flights, drivers, reps and dispatch.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-traffic
 * Domain Path:       /languages
 *
 * @package ITour\Traffic
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Traffic\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Traffic\Module() );
	}
);
