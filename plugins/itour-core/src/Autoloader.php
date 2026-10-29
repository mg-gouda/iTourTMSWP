<?php
/**
 * PSR-4 autoloader shared by iTour plugins (they ship without Composer).
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Maps a namespace prefix to a directory.
 */
final class Autoloader {

	/**
	 * Register a namespace prefix, e.g. `ITour\Crm\` → `…/itour-crm/src/`.
	 *
	 * @param string $prefix Namespace prefix ending in a backslash.
	 * @param string $dir    Directory holding the classes, ending in a slash.
	 */
	public static function register( string $prefix, string $dir ): void {
		spl_autoload_register(
			static function ( string $class_name ) use ( $prefix, $dir ): void {
				if ( 0 !== strpos( $class_name, $prefix ) ) {
					return;
				}
				$file = $dir . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				}
			}
		);
	}
}
