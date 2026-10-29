<?php
/**
 * Plugin Name:       iTour B2B Portal
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Partner portal for tour operators and travel agents: onboarding, users, markups, credit, rate sheets and bookings.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-b2b
 * Domain Path:       /languages
 *
 * @package ITour\B2b
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\B2b\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\B2b\Module() );
	}
);
