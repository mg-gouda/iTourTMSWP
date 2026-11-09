<?php
/**
 * Page cache, run from wp-content/advanced-cache.php before WordPress loads.
 * Guests' GET requests without a query string get a saved copy of the page; the first
 * visit saves it. Signed-in users, carts, forms with errors and 404s are never cached.
 *
 * No WordPress functions are available here until the page is being saved.
 *
 * @package ITour\Shield
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors -- runs before WordPress; values only build a hashed file name.
( static function () {
	$dir = WP_CONTENT_DIR . '/cache/itour-shield';
	$cfg = is_readable( $dir . '/config.php' ) ? include $dir . '/config.php' : null;
	if ( ! is_array( $cfg ) || PHP_SAPI === 'cli' ) {
		return;
	}
	$method = $_SERVER['REQUEST_METHOD'] ?? '';
	if ( ( 'GET' !== $method && 'HEAD' !== $method ) || ! empty( $_GET ) ) {
		return;
	}
	foreach ( array_keys( $_COOKIE ) as $c ) {
		if ( preg_match( '/^(wordpress_logged_in_|wp-postpass_|comment_author_|woocommerce_items_in_cart|wp_woocommerce_session_|itour_nocache)/', (string) $c ) ) {
			return;
		}
	}
	$uri = (string) strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), '?' );
	if ( preg_match( '#/wp-(admin|json|login)|\.(php|xml|txt)$|/feed/?$#', $uri ) ) {
		return;
	}
	foreach ( (array) $cfg['exclude'] as $part ) {
		if ( '' !== $part && false !== strpos( $uri, (string) $part ) ) {
			return;
		}
	}
	if ( '' !== ( $cfg['blocked'] ?? '' ) ) {
		require_once __DIR__ . '/src/Firewall.php';
		if ( \ITour\Shield\Firewall::ip_in( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), (string) $cfg['blocked'] ) ) {
			return; // WordPress loads and the firewall refuses the request.
		}
	}
	$https = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( (string) $_SERVER['HTTPS'] ) ) || 'https' === ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' );
	$file  = $dir . '/pages/' . md5( ( $https ? 'https://' : 'http://' ) . strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) . $uri ) . '.html';

	if ( is_file( $file ) && filemtime( $file ) > time() - (int) $cfg['ttl'] ) {
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-iTour-Cache: HIT' );
		readfile( $file );
		exit;
	}
	header( 'X-iTour-Cache: MISS' );
	ob_start(
		static function ( $html ) use ( $file, $dir ) {
			if ( strlen( (string) $html ) < 255 || false === stripos( (string) $html, '</html>' ) || 200 !== http_response_code() || ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) ) {
				return $html;
			}
			if ( ! function_exists( 'is_user_logged_in' ) || is_user_logged_in() || is_404() || is_search() || is_preview() || is_feed() || is_trackback() ) {
				return $html;
			}
			foreach ( headers_list() as $h ) {
				if ( 0 === stripos( $h, 'Set-Cookie:' ) || ( 0 === stripos( $h, 'Content-Type:' ) && false === stripos( $h, 'text/html' ) ) ) {
					return $html;
				}
			}
			if ( ! is_dir( $dir . '/pages' ) ) {
				@mkdir( $dir . '/pages', 0755, true );
			}
			$tmp = $file . '.' . uniqid( '', true ) . '.tmp';
			if ( false !== @file_put_contents( $tmp, $html . "\n<!-- iTour Shield cache " . gmdate( 'Y-m-d H:i:s' ) . " UTC -->\n" ) ) {
				@rename( $tmp, $file );
			}
			return $html;
		}
	);
} )();
