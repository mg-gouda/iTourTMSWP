<?php
/**
 * Base class every iTour module extends.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A module plugin (CRM, Traffic, …) as seen by the core.
 */
abstract class Module {

	/**
	 * Unique slug, e.g. `crm`. Used for options, tables and capabilities.
	 */
	abstract public function slug(): string;

	/**
	 * Human name shown in the admin.
	 */
	abstract public function name(): string;

	/**
	 * Module version; a change triggers the schema migration.
	 */
	abstract public function version(): string;

	/**
	 * Slugs of other modules this one needs.
	 *
	 * @return string[]
	 */
	public function requires(): array {
		return array();
	}

	/**
	 * Hook the module into WordPress. Called once all modules are registered and their requirements are met.
	 */
	abstract public function boot(): void;

	/**
	 * Table definitions for dbDelta, without the prefix: `name => column SQL`.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array();
	}

	/**
	 * Runs after the module's tables were created or updated (e.g. to seed data).
	 *
	 * @param string $from Previous schema version ('' on first install).
	 */
	public function installed( string $from ): void {
	}

	/**
	 * Admin screens: `slug => [ 'title' => …, 'callback' => callable, 'load' => callable ]`, shown under
	 * the iTour menu. `load` runs before output (save, redirect).
	 *
	 * @return array<string,array{title:string,callback:callable,load?:callable}>
	 */
	public function admin_pages(): array {
		return array();
	}

	/**
	 * Capability that manages this module.
	 */
	public function capability(): string {
		return 'itour_manage_' . $this->slug();
	}
}
