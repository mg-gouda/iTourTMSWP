<?php
/**
 * Plugin Name:       iTour CRM
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Sales CRM: leads, opportunities, customers and activities.
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

define( 'ITOUR_CRM_FILE', __FILE__ );

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Crm\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Crm\Module() );
	}
);
