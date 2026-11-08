<?php
/**
 * iTour Tour Packages module.
 *
 * @package ITour\Tours
 */

namespace ITour\Tours;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Tour Packages module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'tours';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Tour Packages', 'itour-tours' );
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
