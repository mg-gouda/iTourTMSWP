<?php
/**
 * iTour Contracting module.
 *
 * @package ITour\Contracting
 */

namespace ITour\Contracting;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Contracting module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'contracting';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Contracting', 'itour-contracting' );
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
