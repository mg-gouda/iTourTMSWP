<?php
/**
 * Boot, activation and deactivation.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every feature.
 */
final class Plugin {

	/**
	 * Hook everything (plugins_loaded, priority 1).
	 */
	public static function boot(): void {
		if ( get_option( 'itour_shield_version' ) !== ITOUR_SHIELD_VERSION ) {
			self::install();
		}
		Hardening::hooks();
		Login::hooks();
		TwoFactor::hooks();
		Paths::hooks();
		Speed::hooks();
		Cache::hooks();
		Scan::hooks();
		if ( is_admin() ) {
			Admin::hooks();
		}
		add_action( 'itour_shield_daily', array( Log::class, 'prune' ) );
		add_action( 'itour_shield_daily', array( Scan::class, 'cron' ) );
	}

	/**
	 * Tables, cron, drop-ins and server rules.
	 */
	public static function install(): void {
		Log::install();
		if ( ! wp_next_scheduled( 'itour_shield_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'itour_shield_daily' );
		}
		Firewall::install();
		Htaccess::sync();
		Cache::sync();
		update_option( 'itour_shield_version', ITOUR_SHIELD_VERSION, false );
	}

	/**
	 * Activation.
	 */
	public static function activate(): void {
		self::install();
	}

	/**
	 * Deactivation: take every file change back so the site works exactly as before.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'itour_shield_daily' );
		wp_clear_scheduled_hook( 'itour_shield_preload' );
		Firewall::remove();
		Cache::disable();
		Htaccess::remove();
		delete_option( 'rewrite_rules' ); // REST prefix goes back to wp-json.
		delete_option( 'itour_shield_version' );
	}
}
