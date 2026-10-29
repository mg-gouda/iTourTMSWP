<?php
/**
 * Plugin Name:       iTour Reservations
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Hotel bookings priced from contracts: rooming, guests, payments, vouchers, deadlines and cancellations.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core, itour-contracting
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-reservations
 * Domain Path:       /languages
 *
 * @package ITour\Reservations
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Reservations\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Reservations\Module() );
	}
);
