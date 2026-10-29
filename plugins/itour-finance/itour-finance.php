<?php
/**
 * Plugin Name:       iTour Finance
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Double-entry accounting: chart of accounts, journals, invoices, payments, reconciliation, taxes and reports.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-finance
 * Domain Path:       /languages
 *
 * @package ITour\Finance
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Finance\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Finance\Module() );
	}
);
