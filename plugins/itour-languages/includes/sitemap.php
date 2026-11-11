<?php
/**
 * One XML sitemap per language (/itour-sitemap-de.xml …) listing only translated pages, each with hreflang
 * alternates. Listed in robots.txt once the languages are visible to visitors.
 */

defined( 'ABSPATH' ) || exit;

/** Published, indexable posts that have a translated title in $lang: id => last translation update. */
function itour_lang_sitemap_items( $lang ) {
	global $wpdb;
	$types = array_filter( itour_lang_post_types(), 'is_post_type_viewable' );
	$rows  = $wpdb->get_results( $wpdb->prepare(
		'SELECT t.object_id id, MAX(t.updated_at) mod_at FROM ' . itour_lang_table() . " t JOIN {$wpdb->posts} p ON p.ID = t.object_id
		 WHERE t.object_type = 'post' AND t.lang = %s AND p.post_status = 'publish' AND p.post_password = ''
		 GROUP BY t.object_id HAVING SUM(t.field = 'title') > 0 ORDER BY t.object_id",
		$lang
	) );
	$out = array();
	foreach ( $rows as $r ) {
		$pt = get_post_type( $r->id );
		if ( ! in_array( $pt, $types, true ) || get_post_meta( $r->id, '_itour_noindex', true ) ) {
			continue;
		}
		$out[ (int) $r->id ] = $r->mod_at;
	}
	return $out;
}

add_action( 'init', function () {
	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( ! $path || ! preg_match( '#/itour-sitemap-([a-z]{2})\.xml$#', $path, $m ) || 'en' === $m[1] || ! isset( itour_lang_enabled()[ $m[1] ] ) || ! itour_lang_visible() ) {
		return;
	}
	$lang  = $m[1];
	$langs = array_keys( itour_lang_enabled() );
	$all   = itour_lang_all();
	$have  = array();
	foreach ( $langs as $l ) {
		$have[ $l ] = 'en' === $l ? null : itour_lang_sitemap_items( $l );
	}
	status_header( 200 );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex, follow', true );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
	foreach ( $have[ $lang ] as $id => $mod ) {
		$en = get_permalink( $id );
		echo "\t<url>\n\t\t<loc>" . esc_url( itour_lang_prefix_url( $en, $lang ) ) . "</loc>\n\t\t<lastmod>" . esc_html( mysql2date( 'c', $mod ) ) . "</lastmod>\n";
		foreach ( $langs as $l ) {
			if ( 'en' === $l || isset( $have[ $l ][ $id ] ) ) {
				echo "\t\t" . '<xhtml:link rel="alternate" hreflang="' . esc_attr( $all[ $l ][2] ) . '" href="' . esc_url( 'en' === $l ? $en : itour_lang_prefix_url( $en, $l ) ) . '"/>' . "\n";
			}
		}
		echo "\t</url>\n";
	}
	echo '</urlset>';
	exit;
}, 99 );

/* List the language sitemaps in robots.txt (only when visitors can see the languages). */
add_filter( 'robots_txt', function ( $txt ) {
	if ( ! itour_lang_settings()['live'] ) {
		return $txt;
	}
	foreach ( array_keys( itour_lang_enabled() ) as $l ) {
		if ( 'en' !== $l && itour_lang_sitemap_items( $l ) ) {
			$txt .= "\nSitemap: " . home_url( '/itour-sitemap-' . $l . '.xml' );
		}
	}
	return $txt;
}, 100 );
