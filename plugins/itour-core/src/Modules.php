<?php
/**
 * Registry of installed modules.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Holds modules and boots the ones whose requirements are met.
 */
final class Modules {

	/**
	 * Registered modules by slug.
	 *
	 * @var array<string,Module>
	 */
	private array $registered = array();

	/**
	 * Booted modules by slug.
	 *
	 * @var array<string,Module>
	 */
	private array $active = array();

	/**
	 * Modules skipped for missing requirements: slug => missing slugs.
	 *
	 * @var array<string,string[]>
	 */
	private array $missing = array();

	/**
	 * Register a module (called from `itour/register_modules`).
	 *
	 * @param Module $module The module.
	 */
	public function add( Module $module ): void {
		$this->registered[ $module->slug() ] = $module;
	}

	/**
	 * Boot every module whose required modules are registered.
	 */
	public function boot(): void {
		foreach ( $this->registered as $slug => $module ) {
			$missing = array_diff( $module->requires(), array_keys( $this->registered ) );
			if ( $missing ) {
				$this->missing[ $slug ] = $missing;
				continue;
			}
			$this->active[ $slug ] = $module;
		}
		foreach ( $this->active as $module ) {
			$module->boot();
		}
	}

	/**
	 * Whether a module is installed and running.
	 *
	 * @param string $slug Module slug.
	 */
	public function active( string $slug ): bool {
		return isset( $this->active[ $slug ] );
	}

	/**
	 * Running modules.
	 *
	 * @return array<string,Module>
	 */
	public function all(): array {
		return $this->active;
	}

	/**
	 * Tell admins which modules could not start.
	 */
	public function render_notices(): void {
		if ( ! $this->missing || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		foreach ( $this->missing as $slug => $needs ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: module name, 2: comma-separated module slugs. */
						__( 'iTour: %1$s is not running because it needs these modules: %2$s.', 'itour-core' ),
						$this->registered[ $slug ]->name(),
						implode( ', ', $needs )
					)
				)
			);
		}
	}
}
