<?php
/**
 * All Shield settings live in one option, `itour_shield`.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Defaults, reading and sanitizing.
 */
final class Settings {

	public const OPTION = 'itour_shield';

	/**
	 * Defaults. Safe protections start on; anything that changes URLs or caches pages starts off.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Speed.
			'cache'             => 0,
			'cache_ttl'         => 10, // Hours.
			'cache_exclude'     => "/cart/\n/checkout/\n/my-account/",
			'cache_preload'     => 1,
			'defer_js'          => 0,
			'no_emoji'          => 1,
			'no_embeds'         => 1,
			'heartbeat'         => 60,
			'preconnect_fonts'  => 1,
			// Paths.
			'login_slug'        => '',
			'rest_prefix'       => '',
			'mask_paths'        => 0,
			'mask_content'      => 'assets',
			'mask_includes'     => 'lib',
			'mask_plugins'      => 'modules',
			'mask_themes'       => 'skins',
			'mask_uploads'      => 'media',
			// Firewall.
			'firewall'          => 1,
			'rate_limit'        => 120, // Requests per minute per IP; 0 = off.
			'block_bots'        => 1,
			'blocked_ips'       => '',
			'allowed_ips'       => '',
			// Login.
			'login_limit'       => 1,
			'login_attempts'    => 5,
			'lockout_minutes'   => 30,
			'generic_errors'    => 1,
			'two_factor'        => 1,
			'two_factor_force'  => 0, // Require 2FA for administrators.
			// Hardening.
			'headers'           => 1,
			'hsts'              => 0,
			'no_file_edit'      => 1,
			'no_xmlrpc'         => 1,
			'no_user_enum'      => 1,
			'hide_version'      => 1,
			'no_app_passwords'  => 0,
			'protect_files'     => 1,
			'uploads_no_php'    => 1,
			'no_indexes'        => 1,
			// Alerts and log.
			'alert_email'       => '',
			'alert_lockouts'    => 1,
			'alert_admin_login' => 1,
			'alert_scan'        => 1,
			'log_days'          => 30,
		);
	}

	/**
	 * Current settings with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		return array_merge( self::defaults(), (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * Sanitize submitted values over the current ones and save.
	 *
	 * @param array<string,mixed> $input Submitted values.
	 * @return array<string,mixed> Saved settings.
	 */
	public static function save( array $input ): array {
		$cur = self::all();
		$out = $cur;
		foreach ( self::defaults() as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$v = $input[ $key ];
			if ( is_int( $default ) ) {
				$out[ $key ] = max( 0, (int) $v );
			} elseif ( in_array( $key, array( 'cache_exclude', 'blocked_ips', 'allowed_ips' ), true ) ) {
				$out[ $key ] = sanitize_textarea_field( (string) $v );
			} elseif ( 'alert_email' === $key ) {
				$out[ $key ] = sanitize_email( (string) $v );
			} else {
				// Slugs and folder names: lowercase letters, digits and hyphens only.
				$out[ $key ] = trim( (string) preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $v ) ), '-' );
			}
		}
		foreach ( array( 'mask_content', 'mask_includes', 'mask_plugins', 'mask_themes', 'mask_uploads' ) as $k ) {
			if ( '' === $out[ $k ] ) {
				$out[ $k ] = self::defaults()[ $k ];
			}
		}
		if ( in_array( $out['login_slug'], array( 'wp-admin', 'wp-login', 'admin', 'login', 'dashboard' ), true ) ) {
			$out['login_slug'] = $cur['login_slug']; // Too easy to guess.
		}
		update_option( self::OPTION, $out, true );
		return $out;
	}
}
