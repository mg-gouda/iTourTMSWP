<?php
/**
 * Front-end account: the header "Sign in" / "My account" link (theme action wp_itourtms_header_account)
 * and a compact sign-in / sign-up popup. Signing in goes through wp_authenticate(), so iTour Shield's
 * lockout applies; users with two-factor codes fall back to the normal login form. Sign-up is protected
 * by iTour Registration Guard when that plugin is active, and creates a WooCommerce customer when
 * WooCommerce is active.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Account link, popup and its two AJAX actions.
 */
final class Account {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'wp_itourtms_header_account', array( $this, 'link' ) );
		add_action( 'wp_ajax_nopriv_itour_signin', array( $this, 'signin' ) );
		add_action( 'wp_ajax_nopriv_itour_signup', array( $this, 'signup' ) );
		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				if ( 'wp-itourtms' === get_template() ) {
					wp_enqueue_style( 'itour-account', plugins_url( 'assets/account.css', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION ); // In the head: the link is in the header.
				}
			}
		);
	}

	/**
	 * Sign-ups open? (Settings → General → Membership.)
	 */
	private static function open(): bool {
		return (bool) get_option( 'users_can_register' );
	}

	/**
	 * Header link; the popup comes with it in the footer.
	 */
	public function link(): void {
		$icon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8.5" r="3.75"/><path d="M4.75 19.5c1.2-3.3 4-5 7.25-5s6.05 1.7 7.25 5"/></svg>';
		if ( is_user_logged_in() ) {
			$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : get_edit_profile_url();
			printf( '<a class="itour-account" href="%s">%s<span>%s</span></a>', esc_url( $url ), $icon, esc_html__( 'My account', 'itour-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			return;
		}
		printf( '<a class="itour-account" href="%s" data-itour-auth aria-haspopup="dialog">%s<span>%s</span></a>', esc_url( wp_login_url() ), $icon, esc_html__( 'Sign in', 'itour-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		wp_enqueue_script( 'itour-account', plugins_url( 'assets/account.js', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION, array( 'strategy' => 'defer' ) );
		wp_localize_script(
			'itour-account',
			'itourAccount',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'busyIn'  => __( 'Signing in…', 'itour-core' ),
				'busyUp'  => __( 'Creating your account…', 'itour-core' ),
				'offline' => __( 'No connection. Check your internet and try again.', 'itour-core' ),
			)
		);
		add_action( 'wp_footer', array( $this, 'popup' ) );
	}

	/**
	 * One labelled field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param string $type  Input type.
	 * @param string $auto  Autocomplete token.
	 * @param string $extra Extra attributes (static).
	 */
	private static function field( string $name, string $label, string $type, string $auto, string $extra = '' ): void {
		$id  = 'itour-auth-' . str_replace( '_', '-', $name ) . ( 'pwd' === $name || 'log' === $name ? '-in' : '' );
		$eye = 'password' === $type ? sprintf(
			'<button type="button" class="itour-auth__eye" aria-pressed="false" aria-label="%s"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M2.75 12S6 5.75 12 5.75 21.25 12 21.25 12 18 18.25 12 18.25 2.75 12 2.75 12Z"/><circle cx="12" cy="12" r="2.75"/></svg></button>',
			esc_attr__( 'Show password', 'itour-core' )
		) : '';
		printf(
			'<p class="itour-auth__field"><label for="%1$s">%2$s</label><span class="itour-auth__input"><input id="%1$s" name="%3$s" type="%4$s" autocomplete="%5$s" required %6$s>%7$s</span></p>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $name ),
			esc_attr( $type ),
			esc_attr( $auto ),
			$extra, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attributes.
			$eye // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above.
		);
	}

	/**
	 * The popup (native <dialog>; the dialog itself is the blurred backdrop).
	 */
	public function popup(): void {
		$open  = self::open();
		$close = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
		?>
		<dialog class="itour-auth" aria-labelledby="itour-auth-title">
			<div class="itour-auth__card">
				<button type="button" class="itour-auth__close" aria-label="<?php esc_attr_e( 'Close', 'itour-core' ); ?>"><?php echo $close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
				<h2 id="itour-auth-title" class="itour-auth__title"><?php bloginfo( 'name' ); ?></h2>
				<?php if ( $open ) : ?>
					<div class="itour-auth__tabs" role="tablist">
						<button type="button" role="tab" id="itour-auth-tab-in" aria-controls="itour-auth-in" aria-selected="true"><?php esc_html_e( 'Sign in', 'itour-core' ); ?></button>
						<button type="button" role="tab" id="itour-auth-tab-up" aria-controls="itour-auth-up" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Sign up', 'itour-core' ); ?></button>
					</div>
				<?php endif; ?>

				<form id="itour-auth-in" class="itour-auth__form" method="post" action="<?php echo esc_url( wp_login_url() ); ?>" data-action="itour_signin" <?php echo $open ? 'role="tabpanel" aria-labelledby="itour-auth-tab-in"' : ''; ?>>
					<?php
					self::field( 'log', __( 'Email or username', 'itour-core' ), 'text', 'username', 'autocapitalize="off" spellcheck="false"' );
					self::field( 'pwd', __( 'Password', 'itour-core' ), 'password', 'current-password' );
					?>
					<p class="itour-auth__row">
						<label class="itour-auth__check"><input type="checkbox" name="rememberme" value="forever"> <?php esc_html_e( 'Keep me signed in', 'itour-core' ); ?></label>
						<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'itour-core' ); ?></a>
					</p>
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/' ) ); ?>">
					<p class="itour-auth__msg" role="alert"></p>
					<button type="submit" class="itour-auth__submit wp-element-button"><?php esc_html_e( 'Sign in', 'itour-core' ); ?></button>
				</form>

				<?php if ( $open ) : ?>
					<form id="itour-auth-up" class="itour-auth__form" method="post" data-action="itour_signup" role="tabpanel" aria-labelledby="itour-auth-tab-up" hidden>
						<?php
						self::field( 'name', __( 'Full name', 'itour-core' ), 'text', 'name' );
						self::field( 'email', __( 'Email', 'itour-core' ), 'email', 'email', 'spellcheck="false"' );
						self::field( 'password', __( 'Password', 'itour-core' ), 'password', 'new-password', 'minlength="8" aria-describedby="itour-auth-pwd-hint"' );
						?>
						<p id="itour-auth-pwd-hint" class="itour-auth__hint"><?php esc_html_e( 'At least 8 characters.', 'itour-core' ); ?></p>
						<?php
						if ( function_exists( 'itour_reg_fields' ) ) {
							itour_reg_fields(); // iTour Registration Guard.
						}
						?>
						<p class="itour-auth__msg" role="alert"></p>
						<button type="submit" class="itour-auth__submit wp-element-button"><?php esc_html_e( 'Create account', 'itour-core' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</dialog>
		<?php
	}

	/**
	 * Error text without core's "<strong>Error:</strong>" prefix (the popup already shows it as an error).
	 *
	 * @param \WP_Error $e Error.
	 */
	private static function plain( \WP_Error $e ): string {
		return wp_strip_all_tags( (string) preg_replace( '#^\s*<strong>[^<]*</strong>\s*#', '', $e->get_error_message() ) );
	}

	/**
	 * Sign in. Answers {ok} or {native} (two-factor: post the form to the login page) or an error.
	 */
	public function signin(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- login form: the password is the proof; pages are cached, so no nonce.
		$login = sanitize_user( wp_unslash( $_POST['log'] ?? '' ) );
		$pass  = (string) wp_unslash( $_POST['pwd'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.
		$keep  = ! empty( $_POST['rememberme'] );
		// phpcs:enable
		$user = wp_authenticate( $login, $pass ); // Runs the authenticate filters (iTour Shield lockout).
		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => '' !== $login && '' !== $pass ? self::plain( $user ) : __( 'Enter your email or username and your password.', 'itour-core' ) ) );
		}
		if ( class_exists( '\ITour\Shield\TwoFactor' ) && \ITour\Shield\TwoFactor::enabled( $user->ID ) ) {
			wp_send_json_success( array( 'native' => true ) );
		}
		wp_set_auth_cookie( $user->ID, $keep, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		wp_send_json_success();
	}

	/**
	 * Sign up, then sign in.
	 */
	public function signup(): void {
		if ( ! self::open() ) {
			wp_send_json_error( array( 'message' => __( 'New accounts are closed on this site.', 'itour-core' ) ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Registration Guard's signed token instead (cache-safe).
		$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$pass  = (string) wp_unslash( $_POST['password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.
		// phpcs:enable
		if ( '' === $name || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter your name and a valid email address.', 'itour-core' ) ) );
		}
		if ( strlen( $pass ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Choose a password of at least 8 characters.', 'itour-core' ) ) );
		}
		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'This email already has an account. Sign in instead, or reset your password.', 'itour-core' ) ) );
		}
		$err = function_exists( 'itour_reg_verify' ) ? itour_reg_verify() : '';
		if ( '' !== $err ) {
			wp_send_json_error( array( 'message' => $err ) );
		}
		list( $first, $last ) = array_pad( explode( ' ', $name, 2 ), 2, '' );
		$base                 = sanitize_user( (string) strtok( $email, '@' ), true );
		$base                 = '' !== $base ? $base : 'traveller';
		$user                 = $base;
		$i                    = 1;
		while ( username_exists( $user ) ) {
			$user = $base . ( ++$i );
		}
		if ( function_exists( 'wc_create_new_customer' ) ) {
			$id = wc_create_new_customer(
				$email,
				$user,
				$pass,
				array(
					'first_name' => $first,
					'last_name'  => $last,
				)
			);
		} else {
			$id = wp_insert_user(
				array(
					'user_login'   => $user,
					'user_email'   => $email,
					'user_pass'    => $pass,
					'first_name'   => $first,
					'last_name'    => $last,
					'display_name' => $name,
					'role'         => get_option( 'default_role' ),
				)
			);
			if ( ! is_wp_error( $id ) ) {
				wp_new_user_notification( $id, null, 'admin' );
			}
		}
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => self::plain( $id ) ) );
		}
		wp_set_auth_cookie( $id, true, is_ssl() );
		wp_send_json_success();
	}
}
