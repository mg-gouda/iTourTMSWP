<?php
/**
 * iTour Excursions module.
 *
 * @package ITour\Excursions
 */

namespace ITour\Excursions;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Excursions module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'excursions';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Excursions', 'itour-excursions' );
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
