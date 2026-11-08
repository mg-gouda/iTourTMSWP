<?php
/**
 * Plugin Name:       iTour Slider
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Image slider block: accessible, touch-friendly, no jQuery.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-slider
 * Domain Path:       /languages
 *
 * @package ITour\Slider
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_SLIDER_FILE', __FILE__ );

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Slider\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Slider\Module() );
	}
);
