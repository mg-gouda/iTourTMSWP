<?php
/**
 * Login protection: lockouts after failed attempts, error messages that don't reveal
 * which usernames exist, and an email when an administrator signs in from a new IP.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Lockouts and login alerts.
 */
final class Login {

	/**
	 * Hooks.
	 */
	public static function hooks(): void {
		$s = Settings::all();
		if ( $s['login_limit'] ) {
			add_filter( 'authenticate', array( self::class, 'check_lock' ), 99 );
			add_action( 'wp_login_failed', array( self::class, 'failed' ) );
		}
		if ( $s['generic_errors'] ) {
			add_filter( 'authenticate', array( self::class, 'generic' ), 98 );
			add_action( 'lostpassword_post', array( self::class, 'lostpassword' ), 10, 2 );
		}
		add_action( 'wp_login', array( self::class, 'signed_in' ), 5, 2 );
	}

	/**
	 * Transient key for an IP.
	 *
	 * @param string $what fail|lock.
	 */
	private static function key( string $what ): string {
		return 'itour_shield_' . $what . '_' . md5( Log::ip() );
	}

	/**
	 * Refuse any login from a locked-out IP, even with the right password.
	 *
	 * @param mixed $user User, error or null.
	 * @return mixed
	 */
	public static function check_lock( $user ) {
		if ( get_transient( self::key( 'lock' ) ) && ! Firewall::ip_in( Log::ip(), (string) Settings::get( 'allowed_ips' ) ) ) {
			return new \WP_Error(
				'itour_shield_locked',
				/* translators: %d: minutes. */
				sprintf( __( 'Too many failed attempts. Try again in %d minutes.', 'itour-shield' ), (int) Settings::get( 'lockout_minutes' ) )
			);
		}
		return $user;
	}

	/**
	 * Count a failed login; lock the IP when the limit is reached.
	 *
	 * @param string $username Username tried.
	 */
	public static function failed( string $username ): void {
		Log::add( 'login_failed', '', $username );
		if ( get_transient( self::key( 'lock' ) ) || Firewall::ip_in( Log::ip(), (string) Settings::get( 'allowed_ips' ) ) ) {
			return;
		}
		$s     = Settings::all();
		$fails = (int) get_transient( self::key( 'fail' ) ) + 1;
		if ( $fails < max( 1, (int) $s['login_attempts'] ) ) {
			set_transient( self::key( 'fail' ), $fails, HOUR_IN_SECONDS );
			return;
		}
		delete_transient( self::key( 'fail' ) );
		set_transient( self::key( 'lock' ), 1, max( 1, (int) $s['lockout_minutes'] ) * MINUTE_IN_SECONDS );
		/* translators: %d: failed attempts. */
		Log::add( 'lockout', sprintf( __( '%d failed attempts', 'itour-shield' ), $fails ), $username );
		if ( $s['alert_lockouts'] ) {
			Log::alert(
				__( 'login lockout', 'itour-shield' ),
				/* translators: 1: IP, 2: username, 3: minutes. */
				sprintf( __( 'IP %1$s was locked out after repeated failed logins (last username tried: %2$s). The lockout lasts %3$d minutes.', 'itour-shield' ), Log::ip(), $username, (int) $s['lockout_minutes'] )
			);
		}
	}

	/**
	 * One message for "no such user" and "wrong password".
	 *
	 * @param mixed $user User, error or null.
	 * @return mixed
	 */
	public static function generic( $user ) {
		if ( is_wp_error( $user ) && array_intersect( $user->get_error_codes(), array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
			return new \WP_Error( 'itour_shield_failed', __( '<strong>Error:</strong> The username or password is not right.', 'itour-shield' ) );
		}
		return $user;
	}

	/**
	 * Lost password for an unknown account: show the same "check your email" screen as for a real one.
	 *
	 * @param \WP_Error      $errors    Errors so far.
	 * @param \WP_User|false $user_data User found, if any.
	 */
	public static function lostpassword( $errors, $user_data ): void {
		if ( ! $user_data && ! array_diff( $errors->get_error_codes(), array( 'invalid_email' ) ) ) {
			wp_safe_redirect( add_query_arg( 'checkemail', 'confirm', wp_login_url() ) );
			exit;
		}
	}

	/**
	 * Log every sign-in; email when an administrator signs in from an IP not seen before.
	 *
	 * @param string   $login Username.
	 * @param \WP_User $user  User.
	 */
	public static function signed_in( string $login, $user ): void {
		delete_transient( self::key( 'fail' ) );
		$pending = TwoFactor::enabled( $user->ID );
		Log::add( 'login', $pending ? __( 'Password accepted, waiting for the two-factor code', 'itour-shield' ) : __( 'Signed in', 'itour-shield' ), $login );
		if ( ! user_can( $user, 'manage_options' ) || ! Settings::get( 'alert_admin_login' ) ) {
			return;
		}
		$known = (array) get_user_meta( $user->ID, '_itour_shield_ips', true );
		$ip    = Log::ip();
		if ( in_array( $ip, $known, true ) ) {
			return;
		}
		$known[] = $ip;
		update_user_meta( $user->ID, '_itour_shield_ips', array_slice( $known, -20 ) );
		if ( count( $known ) > 1 ) { // The first IP ever is the owner setting up the site.
			Log::alert(
				__( 'administrator signed in from a new IP', 'itour-shield' ),
				/* translators: 1: username, 2: IP. */
				sprintf( __( 'The administrator %1$s signed in with the right password from IP %2$s, which has not been seen for this account before. If this was not you, change the password now.', 'itour-shield' ), $login, $ip )
			);
		}
	}
}
