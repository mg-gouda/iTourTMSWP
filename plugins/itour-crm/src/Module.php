<?php
/**
 * iTour CRM module.
 *
 * @package ITour\Crm
 */

namespace ITour\Crm;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the CRM module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'crm';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'CRM', 'itour-crm' );
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
