<?php
/**
 * Creates and updates module tables when a module's version changes.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Runs dbDelta for each module whose stored schema version is out of date.
 */
final class Migrator {

	/**
	 * Option holding `slug => version` of the last migrated schema.
	 */
	private const OPTION = 'itour_schema_versions';

	/**
	 * The module registry.
	 *
	 * @var Modules
	 */
	private Modules $modules;

	/**
	 * Constructor.
	 *
	 * @param Modules $modules The module registry.
	 */
	public function __construct( Modules $modules ) {
		$this->modules = $modules;
	}

	/**
	 * Migrate any module that changed version. Cheap when nothing changed.
	 */
	public function maybe_migrate(): void {
		$done    = (array) get_option( self::OPTION, array() );
		$changed = false;
		foreach ( $this->modules->all() as $slug => $module ) {
			if ( ( $done[ $slug ] ?? '' ) === $module->version() ) {
				continue;
			}
			$this->migrate( $module );
			$module->installed( (string) ( $done[ $slug ] ?? '' ) );
			$this->grant_capability( $module->capability() );
			$done[ $slug ] = $module->version();
			$changed       = true;
		}
		if ( $changed ) {
			update_option( self::OPTION, $done, false );
		}
	}

	/**
	 * Create or alter a module's tables.
	 *
	 * @param Module $module The module.
	 */
	private function migrate( Module $module ): void {
		global $wpdb;
		$tables = $module->tables();
		if ( ! $tables ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		foreach ( $tables as $name => $columns ) {
			dbDelta( "CREATE TABLE {$wpdb->prefix}itour_{$name} (\n{$columns}\n) {$charset};" );
		}
	}

	/**
	 * Give administrators a module's capability.
	 *
	 * @param string $cap Capability name.
	 */
	private function grant_capability( string $cap ): void {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( $cap ) ) {
			$role->add_cap( $cap );
		}
	}
}
