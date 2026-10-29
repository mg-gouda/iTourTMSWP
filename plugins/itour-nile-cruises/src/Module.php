<?php
/**
 * iTour Nile Cruises module.
 *
 * @package ITour\NileCruises
 */

namespace ITour\NileCruises;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Nile Cruises module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'nile-cruises';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Nile Cruises', 'itour-nile-cruises' );
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
