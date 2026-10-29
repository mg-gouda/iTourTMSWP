<?php
/**
 * Plugin Name:       iTour B2C
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Public site extras: B2C markup rules, hero slides, testimonials, newsletter and enquiries.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-b2c
 * Domain Path:       /languages
 *
 * @package ITour\B2c
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\B2c\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\B2c\Module() );
	}
);
