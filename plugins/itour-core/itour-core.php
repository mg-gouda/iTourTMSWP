<?php
/**
 * Plugin Name:       iTour Core
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Foundation for the iTourTMS modules: company settings, module registry, database migrations and shared services. Install this first, then add the modules you need.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-core
 * Domain Path:       /languages
 *
 * @package ITour\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_CORE_VERSION', '0.1.0' );
define( 'ITOUR_CORE_FILE', __FILE__ );

require_once __DIR__ . '/src/Autoloader.php';
\ITour\Core\Autoloader::register( 'ITour\\Core\\', __DIR__ . '/src/' );

/**
 * The iTour core instance.
 */
function itour(): \ITour\Core\Plugin {
	return \ITour\Core\Plugin::instance();
}

// Core boots before any module, so modules can register on `itour/register_modules`.
add_action( 'plugins_loaded', array( itour(), 'boot' ), 0 );
