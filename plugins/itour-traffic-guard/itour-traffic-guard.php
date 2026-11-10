<?php
/**
 * Plugin Name:       iTour Traffic Guard
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Keeps crawlers from overloading the server. Aggressive SEO and AI crawlers get a short "403" instead of the heavy search, filter and deep paging pages; robots.txt asks every crawler to skip those endless addresses; old links repeating "¤cy=EUR" (a broken "&currency=" link) are redirected once to the clean address. Real trips and pages stay open to everyone.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-traffic-guard
 *
 * @package ITour\TrafficGuard
 */

defined( 'ABSPATH' ) || exit;

// Crawlers that ignore crawl-delay and hammer filter URLs. Search engines (Google, Bing) are not listed.
const ITOUR_TRAFFIC_BOTS = '/meta-externalagent|Amazonbot|PetalBot|Bytespider|GeedoShopProductFinder|AhrefsBot|SemrushBot|MJ12bot|DotBot|DataForSeoBot|BLEXBot|Barkrowler|ImagesiftBot/i';

( static function (): void {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- only matched, never output or stored.
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		return;
	}
	$uri   = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
	$ua    = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	$qpos  = strpos( $uri, '?' );
	$path  = false === $qpos ? $uri : substr( $uri, 0, $qpos );
	$query = false === $qpos ? '' : substr( $uri, $qpos + 1 );
	// phpcs:enable

	// Crawl trap: an unescaped "&curren" became "¤", so each visit added another "¤cy=EUR".
	if ( '' !== $query && preg_match( '/%C2%A4|\xC2\xA4/i', $query ) ) {
		$clean = (string) preg_replace( '/&?(?:%C2%A4|\xC2\xA4)cy(?:%3D|=)[A-Za-z]{3}/i', '', $query );
		header( 'X-Robots-Tag: noindex', true );
		header( 'Location: ' . $path . ( '' !== trim( $clean, '&' ) ? '?' . trim( $clean, '&' ) : '' ), true, 301 );
		exit;
	}

	// Aggressive crawlers: no search, filter or paging pages (each costs database work).
	if ( preg_match( ITOUR_TRAFFIC_BOTS, $ua ) && ( '' !== $query || preg_match( '#/page/\d+/?$#', $path ) ) ) {
		header( 'X-Robots-Tag: noindex', true );
		header( 'Retry-After: 86400' );
		http_response_code( 403 );
		exit( 'Crawling of search and filter pages is not allowed. See /robots.txt.' );
	}
} )();

add_filter(
	'robots_txt',
	static function ( $txt ): string {
		$rules = "Disallow: /*?s=\nDisallow: /*?*orderby=\nDisallow: /*?*currency=\nDisallow: /*?*filter_\n";
		$txt   = (string) $txt;
		$pos   = strpos( $txt, "User-agent: *\n" );
		return false === $pos ? "User-agent: *\n" . $rules . "\n" . $txt : substr_replace( $txt, $rules, $pos + strlen( "User-agent: *\n" ), 0 );
	},
	99999
);
