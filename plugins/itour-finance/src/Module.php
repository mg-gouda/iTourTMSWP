<?php
/**
 * iTour Finance module.
 *
 * @package ITour\Finance
 */

namespace ITour\Finance;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Finance module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'finance';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Finance', 'itour-finance' );
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
