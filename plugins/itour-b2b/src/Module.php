<?php
/**
 * iTour B2B Portal module.
 *
 * @package ITour\B2b
 */

namespace ITour\B2b;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the B2B Portal module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'b2b';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'B2B Portal', 'itour-b2b' );
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
