<?php
/**
 * iTour Traffic module.
 *
 * @package ITour\Traffic
 */

namespace ITour\Traffic;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Traffic module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'traffic';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Traffic', 'itour-traffic' );
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
