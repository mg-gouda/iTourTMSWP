<?php
/**
 * Class autoloader. Shared by the plugin file and the firewall loader in mu-plugins.
 *
 * @package ITour\Shield
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ITOUR_SHIELD_DIR' ) ) {
	define( 'ITOUR_SHIELD_VERSION', '0.1.2' );
	define( 'ITOUR_SHIELD_DIR', __DIR__ );
	define( 'ITOUR_SHIELD_FILE', __DIR__ . '/itour-shield.php' );
	spl_autoload_register(
		static function ( string $class_name ): void {
			if ( 0 === strpos( $class_name, 'ITour\\Shield\\' ) ) {
				$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, 13 ) ) . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				}
			}
		}
	);
}
