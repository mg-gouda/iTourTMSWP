<?php
/**
 * iTour Tour Ops module.
 *
 * @package ITour\TourOps
 */

namespace ITour\TourOps;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Tour Ops module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'tour-ops';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Tour Ops', 'itour-tour-ops' );
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
