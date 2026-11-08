<?php
/**
 * Activity log (logins, lockouts, blocks, scans, setting changes) and email alerts.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Writes to {prefix}itour_shield_log.
 */
final class Log {

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'itour_shield_log';
	}

	/**
	 * Create the table.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  type varchar(30) NOT NULL,
  ip varchar(45) NOT NULL DEFAULT '',
  user_login varchar(60) NOT NULL DEFAULT '',
  detail varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY created_at (created_at),
  KEY type (type)
) " . $wpdb->get_charset_collate() . ';'
		);
	}

	/**
	 * Record an event.
	 *
	 * @param string $type   Event type (login, login_failed, lockout, blocked, scan, settings, two_factor…).
	 * @param string $detail Short description.
	 * @param string $user   User login, if any.
	 */
	public static function add( string $type, string $detail = '', string $user = '' ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'created_at' => current_time( 'mysql', true ),
				'type'       => substr( $type, 0, 30 ),
				'ip'         => self::ip(),
				'user_login' => substr( $user, 0, 60 ),
				'detail'     => mb_substr( $detail, 0, 255 ),
			)
		);
	}

	/**
	 * Latest events.
	 *
	 * @param int    $limit Rows.
	 * @param string $type  Filter by type ('' = all).
	 * @return array<int,array<string,string>>
	 */
	public static function latest( int $limit = 100, string $type = '' ): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$rows = '' === $type
			? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', self::table(), $limit ), ARRAY_A )
			: $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE type = %s ORDER BY id DESC LIMIT %d', self::table(), $type, $limit ), ARRAY_A );
		// phpcs:enable
		return (array) $rows;
	}

	/**
	 * Count events of a type in the last N hours.
	 *
	 * @param string $type  Type.
	 * @param int    $hours Window.
	 */
	public static function count( string $type, int $hours = 24 ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE type = %s AND created_at > %s', self::table(), $type, gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Delete events older than the retention period (daily cron).
	 */
	public static function prune(): void {
		global $wpdb;
		$days = max( 1, (int) Settings::get( 'log_days' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', self::table(), gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Email the site owner.
	 *
	 * @param string $subject Subject (site name is added).
	 * @param string $body    Plain-text body.
	 */
	public static function alert( string $subject, string $body ): void {
		$to = (string) Settings::get( 'alert_email' );
		$to = is_email( $to ) ? $to : (string) get_option( 'admin_email' );
		/* translators: 1: site name, 2: alert subject. */
		wp_mail( $to, sprintf( __( '[%1$s] Shield: %2$s', 'itour-shield' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $subject ), $body . "\n\n" . admin_url( 'admin.php?page=itour-shield&tab=activity' ) );
	}

	/**
	 * The visitor's IP. Uses REMOTE_ADDR only; proxy headers are trusted only when
	 * ITOUR_SHIELD_TRUSTED_PROXY is defined (otherwise attackers could fake their IP).
	 */
	public static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( defined( 'ITOUR_SHIELD_TRUSTED_PROXY' ) && ITOUR_SHIELD_TRUSTED_PROXY && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$first = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) )[0] );
			$ip    = filter_var( $first, FILTER_VALIDATE_IP ) ? $first : $ip;
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}
