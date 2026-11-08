<?php
/**
 * iTour Transfers module.
 *
 * @package ITour\Transfers
 */

namespace ITour\Transfers;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Transfers module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'transfers';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Transfers', 'itour-transfers' );
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
