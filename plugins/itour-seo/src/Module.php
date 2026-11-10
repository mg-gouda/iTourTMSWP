<?php
/**
 * iTour SEO module: own "SEO" admin menu (Settings), SEO tabs on every page, head output, structured
 * data, indexing rules and Yoast import.
 *
 * @package ITour\Seo
 */

namespace ITour\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the SEO module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'seo';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'SEO', 'itour-seo' );
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
		( new Editor() )->hooks();
		( new Head() )->hooks();
		( new Indexing() )->hooks();
		( new Import() )->hooks();
		$cap = $this->capability();
		add_action(
			'init',
			static function () use ( $cap ): void {
				Settings::register( $cap );
			},
			20
		);
	}

	/**
	 * Own "SEO" menu.
	 *
	 * @return array<string,mixed>
	 */
	public function menu(): array {
		return array(
			'title'    => __( 'SEO', 'itour-seo' ),
			'icon'     => 'dashicons-search',
			'position' => 81,
		);
	}

	/**
	 * Screens.
	 *
	 * @return array<string,array{title:string,callback:callable,load?:callable}>
	 */
	public function admin_pages(): array {
		return array(
			'settings' => array(
				'title'    => __( 'Settings', 'itour-seo' ),
				'callback' => array( Settings::class, 'render' ),
			),
		);
	}
}
