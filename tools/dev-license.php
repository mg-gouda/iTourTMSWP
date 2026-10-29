<?php
/**
 * Dev only: make a local Ed25519 key pair and sign test licence tokens like the MG License Server does.
 * Usage (dev site): bin/dev.sh wp eval-file dev-license.php <out-dir>
 * Writes <out-dir>/license-pubkey.pem and prints test tokens (valid, wrong domain, expired).
 *
 * @package ITour
 */

$dir  = rtrim( $args[0] ?? '/tmp', '/' );
$pair = sodium_crypto_sign_keypair();
$sk   = sodium_crypto_sign_secretkey( $pair );
$pk   = sodium_crypto_sign_publickey( $pair );
$pem  = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( hex2bin( '302a300506032b6570032100' ) . $pk ), 64, "\n" ) . "-----END PUBLIC KEY-----\n"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
file_put_contents( $dir . '/license-pubkey.pem', $pem ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

$b64u = static fn( string $s ): string => rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
$mint = static function ( array $over ) use ( $sk, $b64u ): string {
	$p   = $over + array(
		'jti'         => wp_generate_uuid4(),
		'tid'         => 't1',
		'iss'         => 'MGLicenses',
		'product'     => 'wp-itourtms',
		'tenant'      => 'Nile & Sea Travel',
		'domains'     => array( 'localhost' ),
		'ips'         => array(),
		'maxInstalls' => 1,
		'plan'        => 'Agency',
		'iat'         => time(),
		'exp'         => time() + 365 * DAY_IN_SECONDS,
		'ver'         => 1,
	);
	$h   = $b64u( wp_json_encode( array( 'alg' => 'EdDSA', 'typ' => 'JWT' ) ) );
	$b   = $b64u( wp_json_encode( $p ) );
	return $h . '.' . $b . '.' . $b64u( sodium_crypto_sign_detached( $h . '.' . $b, $sk ) );
};
echo 'VALID ', $mint( array() ), "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo 'WRONG_DOMAIN ', $mint( array( 'domains' => array( 'example.com' ) ) ), "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo 'EXPIRED ', $mint( array( 'exp' => time() - 30 * DAY_IN_SECONDS ) ), "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
