<?php
/**
 * Plugin Name:       iTour Transfers
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Private airport and city transfers: zones, vehicles, prices and booking, then dispatch, drivers and fleet.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-transfers
 * Domain Path:       /languages
 *
 * @package ITour\Transfers
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_TRANSFERS_FILE', __FILE__ );

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Transfers\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Transfers\Module() );
	}
);
