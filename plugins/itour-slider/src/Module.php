<?php
/**
 * iTour Slider module: registers the image slider block.
 *
 * @package ITour\Slider
 */

namespace ITour\Slider;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Slider module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'slider';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Slider', 'itour-slider' );
	}

	/**
	 * Module version.
	 */
	public function version(): string {
		return '0.1.1';
	}

	/**
	 * Register the block (block.json holds everything else).
	 */
	public function boot(): void {
		add_action(
			'init',
			static function (): void {
				register_block_type( dirname( ITOUR_SLIDER_FILE ) . '/blocks/slider' );
			}
		);
	}
}
