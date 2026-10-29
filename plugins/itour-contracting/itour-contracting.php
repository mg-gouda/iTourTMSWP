<?php
/**
 * Plugin Name:       iTour Contracting
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Hotels and hotel contracts: seasons, rates, supplements, offers, allotments, stop sales, markets and tariffs.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-contracting
 * Domain Path:       /languages
 *
 * @package ITour\Contracting
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Contracting\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Contracting\Module() );
	}
);
