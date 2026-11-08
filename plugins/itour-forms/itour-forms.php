<?php
/**
 * Plugin Name:       iTour Forms
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Contact and enquiry forms: form block, spam protection, submissions in the dashboard, email notifications.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-forms
 * Domain Path:       /languages
 *
 * @package ITour\Forms
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_FORMS_FILE', __FILE__ );

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Forms\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Forms\Module() );
	}
);
