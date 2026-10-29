<?php
/**
 * Plugin Name:       iTour Tour Ops
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Tour operations: files, packages, quotations, flights, sightseeing, guidance, meals and P&L.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-tour-ops
 * Domain Path:       /languages
 *
 * @package ITour\TourOps
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\TourOps\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\TourOps\Module() );
	}
);
