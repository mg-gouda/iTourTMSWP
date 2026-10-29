<?php
/**
 * iTour Reservations module.
 *
 * @package ITour\Reservations
 */

namespace ITour\Reservations;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Reservations module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'reservations';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Reservations', 'itour-reservations' );
	}

	/**
	 * Module version.
	 */
	public function version(): string {
		return '0.1.0';
	}

	/**
	 * Required modules.
	 *
	 * @return string[]
	 */
	public function requires(): array {
		return array( 'contracting' );
	}

	/**
	 * Hook into WordPress.
	 */
	public function boot(): void {
	}
}
