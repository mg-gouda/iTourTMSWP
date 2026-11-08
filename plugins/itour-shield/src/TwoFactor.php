<?php
/**
 * Two-factor login with an authenticator app (TOTP, RFC 6238) plus one-time backup codes.
 *
 * Flow: after the password is accepted (wp_login) the session cookie is cleared and a code
 * screen is shown; the cookie is set again only when the code is right.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * TOTP.
 */
final class TwoFactor {

	private const SECRET  = '_itour_shield_2fa_secret';
	private const PENDING = '_itour_shield_2fa_pending';
	private const BACKUP  = '_itour_shield_2fa_backup';
	private const LAST    = '_itour_shield_2fa_last';
	private const TOKEN   = '_itour_shield_2fa_token';

	/**
	 * Hooks.
	 */
	public static function hooks(): void {
		if ( ! Settings::get( 'two_factor' ) ) {
			return;
		}
		add_action( 'wp_login', array( self::class, 'intercept' ), 10, 2 );
		add_action( 'login_form_itour_2fa', array( self::class, 'verify' ) );
		add_filter( 'authenticate', array( self::class, 'no_xmlrpc_password' ), 100 );
		add_action( 'show_user_profile', array( self::class, 'profile' ) );
		add_action( 'edit_user_profile', array( self::class, 'profile' ) );
		add_action( 'personal_options_update', array( self::class, 'save' ) );
		add_action( 'edit_user_profile_update', array( self::class, 'save' ) );
		add_action( 'admin_init', array( self::class, 'enforce' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
	}

	/**
	 * Is 2FA on for a user?
	 *
	 * @param int $user_id User.
	 */
	public static function enabled( int $user_id ): bool {
		return Settings::get( 'two_factor' ) && '' !== (string) get_user_meta( $user_id, self::SECRET, true );
	}

	/**
	 * Random base32 secret (160 bits).
	 */
	private static function new_secret(): string {
		$alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$out   = '';
		foreach ( str_split( random_bytes( 32 ) ) as $c ) {
			$out .= $alpha[ ord( $c ) & 31 ];
		}
		return $out;
	}

	/**
	 * Base32 decode.
	 *
	 * @param string $b32 Secret.
	 */
	private static function b32decode( string $b32 ): string {
		$alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$bits  = '';
		foreach ( str_split( strtoupper( $b32 ) ) as $c ) {
			$i = strpos( $alpha, $c );
			if ( false !== $i ) {
				$bits .= str_pad( decbin( $i ), 5, '0', STR_PAD_LEFT );
			}
		}
		$out = '';
		foreach ( str_split( $bits, 8 ) as $byte ) {
			if ( 8 === strlen( $byte ) ) {
				$out .= chr( (int) bindec( $byte ) );
			}
		}
		return $out;
	}

	/**
	 * The 6-digit code for a 30-second step.
	 *
	 * @param string $secret Base32 secret.
	 * @param int    $step   Time step.
	 */
	public static function code( string $secret, int $step ): string {
		$h = hash_hmac( 'sha1', pack( 'J', $step ), self::b32decode( $secret ), true );
		$o = ord( $h[19] ) & 0xF;
		$n = ( unpack( 'N', substr( $h, $o, 4 ) )[1] & 0x7FFFFFFF ) % 1000000;
		return str_pad( (string) $n, 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Check a code against a secret (±30 s for clock drift). Returns the matching step or 0.
	 *
	 * @param string $secret Secret.
	 * @param string $code   Code typed.
	 * @param int    $after  Only accept steps after this one (no replays).
	 */
	private static function match( string $secret, string $code, int $after = 0 ): int {
		$code = preg_replace( '/\D/', '', $code );
		$now  = (int) floor( time() / 30 );
		for ( $s = $now - 1; $s <= $now + 1; $s++ ) {
			if ( $s > $after && hash_equals( self::code( $secret, $s ), (string) $code ) ) {
				return $s;
			}
		}
		return 0;
	}

	/**
	 * Check a code or backup code for a user, and use it up.
	 *
	 * @param int    $user_id User.
	 * @param string $code    Code.
	 */
	private static function check_user( int $user_id, string $code ): bool {
		$step = self::match( (string) get_user_meta( $user_id, self::SECRET, true ), $code, (int) get_user_meta( $user_id, self::LAST, true ) );
		if ( $step ) {
			update_user_meta( $user_id, self::LAST, $step );
			return true;
		}
		$code   = strtolower( (string) preg_replace( '/[^a-z0-9]/i', '', $code ) );
		$hashes = (array) get_user_meta( $user_id, self::BACKUP, true );
		foreach ( $hashes as $i => $hash ) {
			if ( strlen( $code ) === 10 && wp_check_password( $code, (string) $hash ) ) {
				unset( $hashes[ $i ] );
				update_user_meta( $user_id, self::BACKUP, array_values( $hashes ) );
				Log::add( 'two_factor', __( 'Backup code used', 'itour-shield' ), (string) get_userdata( $user_id )->user_login );
				return true;
			}
		}
		return false;
	}

	/**
	 * Ten new backup codes; only hashes are stored.
	 *
	 * @param int $user_id User.
	 * @return array<int,string> Codes to show once.
	 */
	private static function new_backup_codes( int $user_id ): array {
		$codes = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$codes[] = strtolower( wp_generate_password( 10, false ) );
		}
		update_user_meta( $user_id, self::BACKUP, array_map( 'wp_hash_password', $codes ) );
		return $codes;
	}

	/**
	 * Password was right: drop the session and ask for the code.
	 *
	 * @param string   $login Username.
	 * @param \WP_User $user  User.
	 */
	public static function intercept( string $login, $user ): void {
		if ( ! self::enabled( $user->ID ) ) {
			return;
		}
		wp_clear_auth_cookie();
		$token = wp_generate_password( 40, false );
		update_user_meta(
			$user->ID,
			self::TOKEN,
			array(
				'hash'  => wp_hash( $token ),
				'until' => time() + 10 * MINUTE_IN_SECONDS,
				'tries' => 0,
			)
		);
		// phpcs:disable WordPress.Security.NonceVerification -- core login form values, passed through.
		self::screen(
			$user->ID,
			$token,
			isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : admin_url(),
			! empty( $_POST['rememberme'] )
		);
		// phpcs:enable
	}

	/**
	 * The code screen.
	 *
	 * @param int    $user_id  User.
	 * @param string $token    Login token.
	 * @param string $redirect Where to go next.
	 * @param bool   $remember Remember me.
	 * @param string $msg      Error to show.
	 * @return never
	 */
	private static function screen( int $user_id, string $token, string $redirect, bool $remember, string $msg = '' ) {
		global $error, $interim_login, $action, $user_login; // Used by login_header().
		login_header( __( 'Two-factor code', 'itour-shield' ), '', $msg ? new \WP_Error( 'itour_shield_2fa', $msg ) : null );
		?>
		<form name="itour_2fa" id="loginform" action="<?php echo esc_url( add_query_arg( 'action', 'itour_2fa', wp_login_url() ) ); ?>" method="post" autocomplete="off">
			<p><?php esc_html_e( 'Open your authenticator app and type the 6-digit code for this site. Lost your phone? Use one of your backup codes.', 'itour-shield' ); ?></p>
			<p>
				<label for="itour_shield_code"><?php esc_html_e( 'Code', 'itour-shield' ); ?></label>
				<input type="text" name="itour_shield_code" id="itour_shield_code" class="input" inputmode="numeric" autocomplete="one-time-code" size="20" maxlength="12" autofocus required>
			</p>
			<input type="hidden" name="itour_uid" value="<?php echo (int) $user_id; ?>">
			<input type="hidden" name="itour_token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
			<input type="hidden" name="rememberme" value="<?php echo $remember ? '1' : ''; ?>">
			<p class="submit"><button type="submit" class="button button-primary button-large" id="wp-submit"><?php esc_html_e( 'Sign in', 'itour-shield' ); ?></button></p>
		</form>
		<?php
		login_footer( 'itour_shield_code' );
		exit;
	}

	/**
	 * Check the code and finish signing in.
	 */
	public static function verify(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the one-time login token is the CSRF guard here.
		$uid      = isset( $_POST['itour_uid'] ) ? absint( $_POST['itour_uid'] ) : 0;
		$token    = isset( $_POST['itour_token'] ) ? sanitize_text_field( wp_unslash( $_POST['itour_token'] ) ) : '';
		$code     = isset( $_POST['itour_shield_code'] ) ? sanitize_text_field( wp_unslash( $_POST['itour_shield_code'] ) ) : '';
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : admin_url();
		$remember = ! empty( $_POST['rememberme'] );
		// phpcs:enable
		$saved = $uid ? get_user_meta( $uid, self::TOKEN, true ) : array();
		$user  = $uid ? get_userdata( $uid ) : false;
		if ( ! $user || ! is_array( $saved ) || empty( $saved['hash'] ) || $saved['until'] < time() || ! hash_equals( $saved['hash'], wp_hash( $token ) ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}
		if ( ! self::check_user( $uid, $code ) ) {
			++$saved['tries'];
			Log::add( 'two_factor_failed', '', $user->user_login );
			if ( $saved['tries'] >= 5 ) {
				delete_user_meta( $uid, self::TOKEN );
				do_action( 'wp_login_failed', $user->user_login, new \WP_Error( 'itour_shield_2fa' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core hook; counts toward the IP lockout.
				wp_safe_redirect( wp_login_url() );
				exit;
			}
			update_user_meta( $uid, self::TOKEN, $saved );
			self::screen( $uid, $token, $redirect, $remember, __( 'That code is not right. Try the newest code in your app.', 'itour-shield' ) );
		}
		delete_user_meta( $uid, self::TOKEN );
		wp_set_auth_cookie( $uid, $remember );
		wp_set_current_user( $uid );
		Log::add( 'login', __( 'Signed in with two-factor code', 'itour-shield' ), $user->user_login );
		wp_safe_redirect( apply_filters( 'login_redirect', $redirect, $redirect, $user ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core hook.
		exit;
	}

	/**
	 * Users with 2FA can't sign in through XML-RPC with only their password.
	 *
	 * @param mixed $user User or error.
	 * @return mixed
	 */
	public static function no_xmlrpc_password( $user ) {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && $user instanceof \WP_User && self::enabled( $user->ID ) ) {
			return new \WP_Error( 'itour_shield_2fa', __( 'Two-factor login is on for this account.', 'itour-shield' ) );
		}
		return $user;
	}

	/**
	 * Profile section.
	 *
	 * @param \WP_User $user User being edited.
	 */
	public static function profile( $user ): void {
		$own = get_current_user_id() === $user->ID;
		$on  = self::enabled( $user->ID );
		$new = $own ? get_transient( 'itour_shield_codes_' . $user->ID ) : false;
		delete_transient( 'itour_shield_codes_' . $user->ID );
		echo '<h2 id="itour-2fa">' . esc_html__( 'Two-factor login', 'itour-shield' ) . '</h2><table class="form-table" role="presentation"><tr><th scope="row">' . esc_html__( 'Status', 'itour-shield' ) . '</th><td>';
		if ( is_array( $new ) ) {
			echo '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'Save these backup codes now. Each works once, and they will not be shown again.', 'itour-shield' ) . '</strong></p><p><code style="display:block;white-space:pre;font-size:14px;line-height:1.7">' . esc_html( implode( "\n", $new ) ) . '</code></p></div>';
		}
		if ( $on ) {
			$left = count( (array) get_user_meta( $user->ID, self::BACKUP, true ) );
			/* translators: %d: backup codes left. */
			echo '<p><strong>' . esc_html__( 'On', 'itour-shield' ) . '</strong> · ' . esc_html( sprintf( _n( '%d backup code left', '%d backup codes left', $left, 'itour-shield' ), $left ) ) . '</p>';
			if ( $own ) {
				echo '<p><label for="itour_shield_code">' . esc_html__( 'To turn it off or get new backup codes, type a current code:', 'itour-shield' ) . '</label><br><input type="text" id="itour_shield_code" name="itour_shield_code" inputmode="numeric" autocomplete="one-time-code" class="regular-text" style="max-width:10em"></p>';
				echo '<p><label><input type="radio" name="itour_shield_do" value="codes"> ' . esc_html__( 'Make new backup codes', 'itour-shield' ) . '</label><br><label><input type="radio" name="itour_shield_do" value="off"> ' . esc_html__( 'Turn two-factor login off', 'itour-shield' ) . '</label></p>';
			} elseif ( current_user_can( 'edit_users' ) ) {
				echo '<p><label><input type="checkbox" name="itour_shield_do" value="reset"> ' . esc_html__( 'Turn off for this user (for a lost phone). They can set it up again.', 'itour-shield' ) . '</label></p>';
			}
		} elseif ( $own ) {
			$secret = (string) get_user_meta( $user->ID, self::PENDING, true );
			if ( '' === $secret ) {
				$secret = self::new_secret();
				update_user_meta( $user->ID, self::PENDING, $secret );
			}
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$uri  = 'otpauth://totp/' . rawurlencode( $site . ':' . $user->user_login ) . '?secret=' . $secret . '&issuer=' . rawurlencode( $site );
			echo '<p>' . esc_html__( 'Off. Scan the code with an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Authy…), then type the 6-digit code it shows.', 'itour-shield' ) . '</p>';
			echo '<div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin:12px 0">' . Qr::svg( $uri, 176 ) . '<div><p>' . esc_html__( 'Can’t scan? Enter this key:', 'itour-shield' ) . '</p><p><code style="font-size:14px;letter-spacing:.06em">' . esc_html( trim( chunk_split( $secret, 4, ' ' ) ) ) . '</code></p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG built from our own path data.
			echo '<p><label for="itour_shield_code">' . esc_html__( 'Code from the app', 'itour-shield' ) . '</label><br><input type="text" id="itour_shield_code" name="itour_shield_code" inputmode="numeric" autocomplete="one-time-code" class="regular-text" style="max-width:10em"> <input type="hidden" name="itour_shield_do" value="on"></p>';
		} else {
			echo '<p>' . esc_html__( 'Off. Only the user can turn it on, from their own profile.', 'itour-shield' ) . '</p>';
		}
		wp_nonce_field( 'itour_shield_2fa_' . $user->ID, 'itour_shield_2fa_nonce' );
		echo '</td></tr></table>';
	}

	/**
	 * Save from the profile form.
	 *
	 * @param int $user_id User being edited.
	 */
	public static function save( int $user_id ): void {
		if ( ! isset( $_POST['itour_shield_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['itour_shield_2fa_nonce'] ), 'itour_shield_2fa_' . $user_id ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		$do    = isset( $_POST['itour_shield_do'] ) ? sanitize_key( $_POST['itour_shield_do'] ) : '';
		$code  = isset( $_POST['itour_shield_code'] ) ? sanitize_text_field( wp_unslash( $_POST['itour_shield_code'] ) ) : '';
		$own   = get_current_user_id() === $user_id;
		$login = (string) get_userdata( $user_id )->user_login;
		if ( 'reset' === $do && ! $own && current_user_can( 'edit_users' ) ) {
			self::clear( $user_id );
			Log::add( 'two_factor', __( 'Turned off by an administrator', 'itour-shield' ), $login );
			return;
		}
		if ( ! $own || '' === $code ) {
			return;
		}
		if ( 'on' === $do ) {
			$secret = (string) get_user_meta( $user_id, self::PENDING, true );
			$step   = $secret ? self::match( $secret, $code ) : 0;
			if ( ! $step ) {
				set_transient( 'itour_shield_2fa_msg_' . $user_id, __( 'Two-factor login was not turned on: the code did not match. Check the time on your phone and try the newest code.', 'itour-shield' ), 60 );
				return;
			}
			update_user_meta( $user_id, self::SECRET, $secret );
			update_user_meta( $user_id, self::LAST, $step );
			delete_user_meta( $user_id, self::PENDING );
			set_transient( 'itour_shield_codes_' . $user_id, self::new_backup_codes( $user_id ), 10 * MINUTE_IN_SECONDS );
			Log::add( 'two_factor', __( 'Turned on', 'itour-shield' ), $login );
			return;
		}
		if ( ! self::check_user( $user_id, $code ) ) {
			set_transient( 'itour_shield_2fa_msg_' . $user_id, __( 'Nothing changed: the two-factor code did not match.', 'itour-shield' ), 60 );
			return;
		}
		if ( 'off' === $do ) {
			self::clear( $user_id );
			Log::add( 'two_factor', __( 'Turned off', 'itour-shield' ), $login );
		} elseif ( 'codes' === $do ) {
			set_transient( 'itour_shield_codes_' . $user_id, self::new_backup_codes( $user_id ), 10 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Remove a user's 2FA.
	 *
	 * @param int $user_id User.
	 */
	private static function clear( int $user_id ): void {
		foreach ( array( self::SECRET, self::BACKUP, self::LAST, self::TOKEN, self::PENDING ) as $k ) {
			delete_user_meta( $user_id, $k );
		}
	}

	/**
	 * When 2FA is required for administrators, keep them on their profile until it is set up.
	 */
	public static function enforce(): void {
		global $pagenow;
		if ( ! Settings::get( 'two_factor_force' ) || wp_doing_ajax() || ! current_user_can( 'manage_options' ) || self::enabled( get_current_user_id() ) || 'profile.php' === $pagenow ) {
			return;
		}
		wp_safe_redirect( admin_url( 'profile.php#itour-2fa' ) );
		exit;
	}

	/**
	 * Profile messages.
	 */
	public static function notices(): void {
		$uid = get_current_user_id();
		$msg = get_transient( 'itour_shield_2fa_msg_' . $uid );
		if ( $msg ) {
			delete_transient( 'itour_shield_2fa_msg_' . $uid );
			echo '<div class="notice notice-error"><p>' . esc_html( (string) $msg ) . '</p></div>';
		}
		if ( Settings::get( 'two_factor_force' ) && current_user_can( 'manage_options' ) && ! self::enabled( $uid ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'This site requires two-factor login for administrators. Set it up below to continue.', 'itour-shield' ) . '</p></div>';
		}
	}
}
