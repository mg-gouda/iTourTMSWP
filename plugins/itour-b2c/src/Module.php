<?php
/**
 * iTour B2C module.
 *
 * @package ITour\B2c
 */

namespace ITour\B2c;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the B2C module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'b2c';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'B2C', 'itour-b2c' );
	}

	/**
	 * Module version.
	 */
	public function version(): string {
		return '0.1.0';
	}

	/**
	 * Hook into WordPress.
	 */
	public function boot(): void {
	}
}
