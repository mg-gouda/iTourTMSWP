<?php
/**
 * Plugin Name:       iTour SEO
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Search titles and descriptions with a live Google preview and checklist, social sharing cards, structured data for tours and excursions, indexing rules and sitemap control. Imports Yoast SEO data.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-seo
 * Domain Path:       /languages
 *
 * @package ITour\Seo
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_SEO_FILE', __FILE__ );

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Seo\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Seo\Module() );
	}
);
