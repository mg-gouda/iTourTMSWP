<?php
/**
 * Plugin Name:       iTour Travel Documents
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       PDF travel documents: a Travel Voucher for the guest (attached to the order emails and linked from My account), a Service Order for drivers and suppliers (no guest prices, cash to collect), and a Booking Report for any date range with totals per currency. Covers excursion and transfer bookings.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-documents
 * Domain Path:       /languages
 *
 * @package ITour\Documents
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_DOCUMENTS_FILE', __FILE__ );

add_action(
	'itour/register_modules',
	static function ( \ITour\Core\Modules $modules ): void {
		\ITour\Core\Autoloader::register( 'ITour\\Documents\\', __DIR__ . '/src/' );
		$modules->add( new \ITour\Documents\Module() );
	}
);
