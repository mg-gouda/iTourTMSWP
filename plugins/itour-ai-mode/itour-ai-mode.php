<?php
/**
 * Plugin Name:       iTour AI Mode
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       A Google Gemini booking assistant on every page: guests describe their trip in their own words (typed or spoken), get matching tours and day trips as cards, and book excursions and transfers in the chat up to checkout. Grounded only in your own trips, prices and rules; prices and availability always come from the site, never from the AI. Needs your own Gemini API key.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-ai-mode
 * Domain Path:       /languages
 *
 * @package ITour\AiMode
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_AI_FILE', __FILE__ );

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\AiMode\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\AiMode\Module() );
	}
);
