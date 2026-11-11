<?php
/**
 * Travel Documents module: routes, email attachments, order links and the Booking report screen.
 *
 * @package ITour\Documents
 */

namespace ITour\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'documents';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Travel Documents', 'itour-documents' );
	}

	/**
	 * Module version.
	 */
	public function version(): string {
		return '0.1.0';
	}

	/**
	 * Hooks.
	 */
	public function boot(): void {
		( new Routes() )->hooks();
	}

	/**
	 * Under the iTour menu.
	 *
	 * @return array<string,mixed>
	 */
	public function menu(): array {
		return array();
	}

	/**
	 * Screens.
	 *
	 * @return array<string,array{title:string,callback:callable,load?:callable}>
	 */
	public function admin_pages(): array {
		return array(
			'report' => array(
				'title'    => __( 'Booking report', 'itour-documents' ),
				'callback' => array( Routes::class, 'report_screen' ),
			),
		);
	}
}
