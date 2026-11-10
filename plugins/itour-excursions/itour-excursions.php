<?php
/**
 * Plugin Name:       iTour Excursions
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Day trips and excursions: programmes, pick-up, prices, availability and booking.
 * Version:           0.3.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-excursions
 * Domain Path:       /languages
 *
 * @package ITour\Excursions
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_EXCURSIONS_FILE', __FILE__ );

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Excursions\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Excursions\Module() );
	}
);
