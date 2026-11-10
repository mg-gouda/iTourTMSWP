<?php
/**
 * Plugin Name:       iTour Registration Guard
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Bot protection for every public way to create an account: the WordPress register form, WooCommerce My Account registration and "create an account" at checkout. Honeypot, signed form token (works with page caching), human-interaction check and 5 sign-ups per hour per connection. Guest bookings are never blocked.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-registration-guard
 *
 * @package ITour\RegistrationGuard
 */

defined( 'ABSPATH' ) || exit;

const ITOUR_REG_LIMIT_PER_HOUR = 5;
const ITOUR_REG_TOKEN_DAYS     = 7; // Pages may come from a cache; tokens stay valid this long.

/**
 * Signed token for a day (today by default).
 *
 * @param int|null $day Days since 1970.
 */
function itour_reg_token( ?int $day = null ): string {
	$day = $day ?? (int) floor( time() / DAY_IN_SECONDS );
	return $day . '.' . substr( hash_hmac( 'sha256', 'itour-reg-' . $day, wp_salt( 'nonce' ) ), 0, 24 );
}

/**
 * Token is genuine and recent.
 *
 * @param string $tk Posted token.
 */
function itour_reg_token_ok( string $tk ): bool {
	$parts = explode( '.', $tk );
	if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
		return false;
	}
	$today = (int) floor( time() / DAY_IN_SECONDS );
	$day   = (int) $parts[0];
	return $day <= $today && $day > $today - ITOUR_REG_TOKEN_DAYS && hash_equals( itour_reg_token( $day ), $tk );
}

/**
 * Hidden fields for a protected form, plus the script that marks real interaction.
 */
function itour_reg_fields(): void {
	static $script = false;
	?>
	<input type="hidden" name="itour_tk" value="<?php echo esc_attr( itour_reg_token() ); ?>">
	<input type="hidden" name="itour_h" value="">
	<div style="position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label>Company website<input type="text" name="itour_company_site" value="" tabindex="-1" autocomplete="off"></label></div>
	<?php
	if ( $script ) {
		return;
	}
	$script = true;
	?>
	<script>
	(function () {
		function arm() {
			document.querySelectorAll('input[name="itour_tk"]').forEach(function (t) {
				var f = t.form;
				if (!f || f.dataset.itourArmed) return;
				f.dataset.itourArmed = '1';
				var mark = function () { var h = f.querySelector('input[name="itour_h"]'); if (h) h.value = '1'; };
				['keydown', 'pointerdown', 'touchstart', 'focusin'].forEach(function (ev) { f.addEventListener(ev, mark, { passive: true }); });
			});
		}
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', arm); else arm();
		if (window.jQuery) jQuery(document.body).on('updated_checkout', arm); // WooCommerce re-renders checkout.
	})();
	</script>
	<?php
}

/**
 * Check the posted form: '' when it looks human, else the message to show.
 *
 * @param bool $count Whether a passing attempt uses one of the connection's hourly sign-ups.
 */
function itour_reg_verify( bool $count = true ): string {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- signed token instead of a nonce (nonces break with page caching).
	if ( ! empty( $_POST['itour_company_site'] ) || empty( $_POST['itour_h'] ) || ! itour_reg_token_ok( sanitize_text_field( wp_unslash( $_POST['itour_tk'] ?? '' ) ) ) ) {
		return __( 'Your sign-up could not be verified. Please reload the page and try again.', 'itour-registration-guard' );
	}
	// phpcs:enable
	$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key = 'itour_reg_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= ITOUR_REG_LIMIT_PER_HOUR ) {
		return __( 'Too many sign-ups from your connection. Please try again later.', 'itour-registration-guard' );
	}
	if ( $count ) {
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	}
	return '';
}

// 1) WordPress register form (wp-login.php?action=register).
add_action( 'register_form', 'itour_reg_fields' );
add_filter(
	'registration_errors',
	static function ( $errors ) {
		$err = itour_reg_verify( ! $errors->has_errors() );
		if ( $err ) {
			$errors->add( 'itour_reg_guard', $err );
		}
		return $errors;
	}
);

// 2) WooCommerce My Account registration.
add_action( 'woocommerce_register_form', 'itour_reg_fields' );
add_filter(
	'woocommerce_process_registration_errors',
	static function ( $errors ) {
		$err = itour_reg_verify();
		if ( $err ) {
			$errors->add( 'itour_reg_guard', $err );
		}
		return $errors;
	}
);

// 3) WooCommerce checkout, only when an account is being created.
add_action(
	'woocommerce_after_checkout_registration_form',
	static function (): void {
		if ( ! is_user_logged_in() ) {
			itour_reg_fields();
		}
	}
);
add_action(
	'woocommerce_after_checkout_validation',
	static function ( $data, $errors ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checked its own nonce.
		if ( is_user_logged_in() || ! ( ! empty( $_POST['createaccount'] ) || WC()->checkout()->is_registration_required() ) ) {
			return;
		}
		$err = itour_reg_verify( ! $errors->has_errors() );
		if ( $err ) {
			$errors->add( 'itour_reg_guard', $err );
		}
	},
	10,
	2
);
