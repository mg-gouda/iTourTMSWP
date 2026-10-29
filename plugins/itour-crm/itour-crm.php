<?php
/**
 * Plugin Name:       iTour CRM
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Tours and excursions: catalogue, programmes, pricing, availability, bookings and customers, plus leads, opportunities and dispatch.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-crm
 * Domain Path:       /languages
 *
 * @package ITour\Crm
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Crm\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Crm\Module() );
	}
);
