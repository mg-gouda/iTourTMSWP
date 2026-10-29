<?php
/**
 * Theme license (commercial edition only). PHP port of MGLicensesApp's client/license-verify.ts.
 *
 * The license key is an Ed25519-signed token issued by the MG License Server. It is verified offline
 * on every check (signature, issuer, product, domain, expiry) and confirmed online about once a day
 * through POST {server}/api/v1/heartbeat (renewals, revocation, install and IP limits).
 *
 * The license unlocks updates and support. It never switches off the site's front end.
 * This file is left out of the WordPress.org build (bin/package.sh), which has no license tab.
 *
 * Configure in wp-config.php (or ship inc/license-pubkey.pem):
 *   define( 'WP_ITOURTMS_LICENSE_SERVER', 'https://licenses.example.com' );
 *   define( 'WP_ITOURTMS_LICENSE_PUBKEY', "-----BEGIN PUBLIC KEY-----\n…\n-----END PUBLIC KEY-----" );
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

const WP_ITOURTMS_LICENSE_PRODUCT = 'wp-itourtms';
const WP_ITOURTMS_LICENSE_OPTION  = 'wp_itourtms_license';

/**
 * License server base URL.
 */
function wp_itourtms_license_server(): string {
	$url = defined( 'WP_ITOURTMS_LICENSE_SERVER' ) ? (string) WP_ITOURTMS_LICENSE_SERVER : '';
	return untrailingslashit( (string) apply_filters( 'wp_itourtms_license_server', $url ) );
}

/**
 * The server's Ed25519 public key as 32 raw bytes, from the constant or inc/license-pubkey.pem. '' when missing.
 */
function wp_itourtms_license_public_key(): string {
	$pem  = defined( 'WP_ITOURTMS_LICENSE_PUBKEY' ) ? (string) WP_ITOURTMS_LICENSE_PUBKEY : '';
	$file = __DIR__ . '/license-pubkey.pem';
	if ( '' === $pem && is_readable( $file ) ) {
		$pem = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	}
	try {
		$der = sodium_base642bin( (string) preg_replace( '/-----[^-]+-----|\s+/', '', $pem ), SODIUM_BASE64_VARIANT_ORIGINAL );
	} catch ( Throwable $e ) {
		return '';
	}
	// Ed25519 SPKI = 12-byte header 302a300506032b6570032100 + 32-byte key.
	if ( 44 !== strlen( $der ) || '302a300506032b6570032100' !== bin2hex( substr( $der, 0, 12 ) ) ) {
		return '';
	}
	return substr( $der, 12 );
}

/**
 * Whether the license system is configured (server and key).
 */
function wp_itourtms_license_configured(): bool {
	return '' !== wp_itourtms_license_server() && '' !== wp_itourtms_license_public_key();
}

/**
 * Base64url decode.
 *
 * @param string $s Input.
 */
function wp_itourtms_b64u( string $s ): string {
	try {
		return sodium_base642bin( $s, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING );
	} catch ( Throwable $e ) {
		return '';
	}
}

/**
 * This site's host name as the license sees it.
 */
function wp_itourtms_license_host(): string {
	return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
}

/**
 * Offline verdict: signature, issuer, product, domain, expiry. No network.
 *
 * @param string $token License key.
 * @return array{status:string,payload?:array<string,mixed>}
 */
function wp_itourtms_license_verify_offline( string $token ): array {
	$parts = explode( '.', trim( $token ) );
	$key   = wp_itourtms_license_public_key();
	if ( 3 !== count( $parts ) || '' === $key ) {
		return array( 'status' => 'invalid' );
	}
	$sig = wp_itourtms_b64u( $parts[2] );
	try {
		$valid = 64 === strlen( $sig ) && sodium_crypto_sign_verify_detached( $sig, $parts[0] . '.' . $parts[1], $key );
	} catch ( Throwable $e ) {
		$valid = false;
	}
	$payload = json_decode( wp_itourtms_b64u( $parts[1] ), true );
	if ( ! $valid || ! is_array( $payload ) || 'MGLicenses' !== ( $payload['iss'] ?? '' ) || WP_ITOURTMS_LICENSE_PRODUCT !== ( $payload['product'] ?? '' ) ) {
		return array( 'status' => 'invalid' );
	}
	$domains = array_map( static fn( $d ) => strtolower( explode( ':', (string) $d )[0] ), (array) ( $payload['domains'] ?? array() ) );
	if ( ! in_array( '*', $domains, true ) && ! in_array( wp_itourtms_license_host(), $domains, true ) ) {
		return array(
			'status'  => 'domain_mismatch',
			'payload' => $payload,
		);
	}
	return array(
		'status'  => (int) ( $payload['exp'] ?? 0 ) <= time() ? 'expired' : 'active',
		'payload' => $payload,
	);
}

/**
 * Ask the license server about this key (renewal, revocation, limits).
 *
 * @param string $token      License key.
 * @param string $install_id This site's install id.
 * @return array<string,mixed> `reached` plus the server's flags.
 */
function wp_itourtms_license_heartbeat( string $token, string $install_id ): array {
	$r = wp_remote_post(
		wp_itourtms_license_server() . '/api/v1/heartbeat',
		array(
			'timeout' => 6,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'token'     => $token,
					'installId' => $install_id,
					'hostname'  => wp_itourtms_license_host(),
				)
			),
		)
	);
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return array( 'reached' => false );
	}
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	return array( 'reached' => true ) + ( is_array( $j ) ? $j : array() );
}

/**
 * Saved license state with defaults; creates the install id once.
 *
 * @return array{token:string,install_id:string,last_good:int,status:string,checked_at:int}
 */
function wp_itourtms_license_state(): array {
	$s = (array) get_option( WP_ITOURTMS_LICENSE_OPTION, array() ) + array(
		'token'      => '',
		'install_id' => '',
		'last_good'  => 0,
		'status'     => 'none',
		'checked_at' => 0,
	);
	if ( '' === $s['install_id'] ) {
		$s['install_id'] = wp_generate_uuid4();
		update_option( WP_ITOURTMS_LICENSE_OPTION, $s, false );
	}
	return $s;
}

/**
 * Full hybrid check (same rules as license-verify.ts). Persists renewals and the last good check.
 *
 * @param bool $force Confirm online now instead of at most once a day.
 * @return array<string,mixed> ok, status, and details for the screen.
 */
function wp_itourtms_license_check( bool $force = false ): array {
	$s = wp_itourtms_license_state();
	if ( '' === $s['token'] ) {
		return array(
			'ok'     => false,
			'status' => 'none',
		);
	}
	$off = wp_itourtms_license_verify_offline( $s['token'] );
	if ( in_array( $off['status'], array( 'invalid', 'domain_mismatch' ), true ) ) {
		return wp_itourtms_license_store( $s, array( 'status' => $off['status'] ) );
	}
	$p      = $off['payload'] ?? array();
	$now    = time();
	$grace  = 7 * DAY_IN_SECONDS;
	$last   = $s['last_good'] > 0 && $s['last_good'] <= $now ? $s['last_good'] : 0; // A future value can't be trusted.
	$anchor = $last > 0 ? $last : (int) ( $p['iat'] ?? 0 );
	$in_exp = $now - (int) $p['exp'] <= $grace;

	if ( $force || 0 === $last || $now - $last >= DAY_IN_SECONDS ) {
		$on = wp_itourtms_license_heartbeat( $s['token'], $s['install_id'] );
		if ( $on['reached'] ) {
			$s['last_good'] = $now;
			if ( ! empty( $on['refreshedToken'] ) && 'active' === wp_itourtms_license_verify_offline( (string) $on['refreshedToken'] )['status'] ) {
				$s['token'] = (string) $on['refreshedToken'];
				$p          = wp_itourtms_license_verify_offline( $s['token'] )['payload'] ?? $p;
			}
			foreach ( array(
				'revoked'        => 'revoked',
				'ipBlocked'      => 'ip_mismatch',
				'installBlocked' => 'install_blocked',
			) as $flag => $status ) {
				if ( ! empty( $on[ $flag ] ) ) {
					return wp_itourtms_license_store( $s, array( 'status' => $status ), $p );
				}
			}
			// The signed expiry counts even if the server forgets to flag it (a renewal arrives as refreshedToken).
			if ( ! empty( $on['expired'] ) || (int) ( $p['exp'] ?? 0 ) <= $now ) {
				return wp_itourtms_license_store( $s, array( 'status' => $in_exp ? 'grace' : 'expired' ), $p );
			}
			return wp_itourtms_license_store( $s, array( 'status' => 'active' ), $p );
		}
	}
	if ( $now - $anchor > $grace ) {
		return wp_itourtms_license_store( $s, array( 'status' => 'grace_expired' ), $p );
	}
	if ( 'expired' === $off['status'] ) {
		return wp_itourtms_license_store( $s, array( 'status' => $in_exp ? 'grace' : 'expired' ), $p );
	}
	return wp_itourtms_license_store( $s, array( 'status' => $last > 0 ? 'active' : 'grace' ), $p );
}

/**
 * Save state and build the result the screen shows.
 *
 * @param array<string,mixed> $s       State.
 * @param array<string,mixed> $result  Status.
 * @param array<string,mixed> $payload Token payload.
 * @return array<string,mixed>
 */
function wp_itourtms_license_store( array $s, array $result, array $payload = array() ): array {
	$s['status']     = $result['status'];
	$s['checked_at'] = time();
	update_option( WP_ITOURTMS_LICENSE_OPTION, $s, false );
	$exp = (int) ( $payload['exp'] ?? 0 );
	return array(
		'ok'        => in_array( $result['status'], array( 'active', 'grace' ), true ),
		'status'    => $result['status'],
		'tenant'    => (string) ( $payload['tenant'] ?? '' ),
		'plan'      => (string) ( $payload['plan'] ?? '' ),
		'domains'   => array_values( array_map( 'strval', (array) ( $payload['domains'] ?? array() ) ) ),
		'expiresAt' => $exp ? wp_date( get_option( 'date_format' ), $exp ) : '',
		'daysLeft'  => $exp ? (int) ceil( ( $exp - time() ) / DAY_IN_SECONDS ) : null,
		'checkedAt' => $s['last_good'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $s['last_good'] ) : '',
		'keyEnd'    => '' !== $s['token'] ? substr( (string) $s['token'], -6 ) : '',
	);
}

/**
 * Activate a key: verify offline first, then confirm online.
 *
 * @param string $token License key pasted by the user.
 * @return array<string,mixed>
 */
function wp_itourtms_license_activate( string $token ): array {
	$token = (string) preg_replace( '/\s+/', '', $token );
	$off   = wp_itourtms_license_verify_offline( $token );
	if ( in_array( $off['status'], array( 'invalid', 'domain_mismatch' ), true ) ) {
		return array(
			'ok'     => false,
			'status' => $off['status'],
		);
	}
	$s              = wp_itourtms_license_state();
	$s['token']     = $token;
	$s['last_good'] = 0;
	update_option( WP_ITOURTMS_LICENSE_OPTION, $s, false );
	return wp_itourtms_license_check( true );
}

/**
 * Remove the key from this site (keeps the install id).
 *
 * @return array{ok:bool,status:string}
 */
function wp_itourtms_license_deactivate(): array {
	$s           = wp_itourtms_license_state();
	$s['token']  = '';
	$s['status'] = 'none';
	update_option( WP_ITOURTMS_LICENSE_OPTION, $s, false );
	return array(
		'ok'     => false,
		'status' => 'none',
	);
}

/**
 * REST routes for the license tab.
 */
function wp_itourtms_license_routes(): void {
	$can = static fn() => current_user_can( 'edit_theme_options' );
	register_rest_route(
		'wp-itourtms/v1',
		'/license',
		array(
			array(
				'methods'             => 'GET',
				'permission_callback' => $can,
				'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( wp_itourtms_license_check( (bool) $r->get_param( 'refresh' ) ) ),
			),
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'args'                => array(
					'key' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( wp_itourtms_license_activate( (string) $r->get_param( 'key' ) ) ),
			),
			array(
				'methods'             => 'DELETE',
				'permission_callback' => $can,
				'callback'            => static fn() => rest_ensure_response( wp_itourtms_license_deactivate() ),
			),
		)
	);
}
add_action( 'rest_api_init', 'wp_itourtms_license_routes' );

/**
 * Add the License section to the manual (commercial edition only).
 *
 * @param array<string,mixed> $manual Manual content.
 * @return array<string,mixed>
 */
function wp_itourtms_license_manual( array $manual ): array {
	$manual['start']['sections'][] = array(
		'id'     => 'license',
		'title'  => __( 'Activate your license', 'wp-itourtms' ),
		'blocks' => array(
			array(
				'steps',
				array(
					__( 'Open Appearance → Theme Options → License.', 'wp-itourtms' ),
					__( 'Paste the license key from your purchase email and click Activate.', 'wp-itourtms' ),
					__( 'The status turns Active once the license server confirms it. The license is checked again about once a day.', 'wp-itourtms' ),
				),
			),
			array( 'note', __( 'Your site keeps working without a license; the license unlocks updates and support. A key works only on the domains it was issued for.', 'wp-itourtms' ) ),
		),
	);
	$manual['docs']['sections'][]  = array(
		'id'     => 'license-config',
		'title'  => __( 'License server', 'wp-itourtms' ),
		'blocks' => array(
			array( 'p', __( 'License keys are Ed25519-signed tokens from the MG License Server. They are verified on the site (signature, product, domain, expiry) and confirmed online about once a day; if the server cannot be reached the license keeps working for 7 days. Set the server and its public key in wp-config.php, or ship the key as inc/license-pubkey.pem:', 'wp-itourtms' ) ),
			array( 'code', "define( 'WP_ITOURTMS_LICENSE_SERVER', 'https://licenses.example.com' );\ndefine( 'WP_ITOURTMS_LICENSE_PUBKEY', \"-----BEGIN PUBLIC KEY-----\\n…\\n-----END PUBLIC KEY-----\" );\n// Public key: curl https://licenses.example.com/api/v1/pubkey" ),
			array( 'p', __( 'Other code can read the status with wp_itourtms_license_check() — it returns ok, status, plan and expiry.', 'wp-itourtms' ) ),
		),
	);
	return $manual;
}
add_filter( 'wp_itourtms_manual', 'wp_itourtms_license_manual' );
