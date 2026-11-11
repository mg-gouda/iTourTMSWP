<?php
/**
 * Plugin Name:       iTour AI Translations
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Native language versions of the site (/de/, /fr/, /ar/ ...). English stays the master; titles, content, excerpts, trip details (included, FAQ, itinerary, add-ons), iTour SEO texts, menus and interface texts are stored per language and machine-translated with Google Gemini. Prices, availability, bookings and images are shared, never duplicated. Ported from Rehlla Languages.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-languages
 *
 * @package ITour\Languages
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_LANG_VERSION', '0.1.0' );
define( 'ITOUR_LANG_DIR', __DIR__ );

/** All supported languages: code => [native name, WP locale, hreflang]. English is the source language. */
function itour_lang_all() {
	return array(
		'en' => array( 'English', 'en_US', 'en' ),
		'de' => array( 'Deutsch', 'de_DE', 'de' ),
		'fr' => array( 'Français', 'fr_FR', 'fr' ),
		'it' => array( 'Italiano', 'it_IT', 'it' ),
		'es' => array( 'Español', 'es_ES', 'es' ),
		'pl' => array( 'Polski', 'pl_PL', 'pl' ),
		'ru' => array( 'Русский', 'ru_RU', 'ru' ),
		'ar' => array( 'العربية', 'ar', 'ar' ),
		'nl' => array( 'Nederlands', 'nl_NL', 'nl' ),
		'pt' => array( 'Português', 'pt_PT', 'pt' ),
		'tr' => array( 'Türkçe', 'tr_TR', 'tr' ),
		'zh' => array( '中文', 'zh_CN', 'zh' ),
		'ja' => array( '日本語', 'ja', 'ja' ),
	);
}

function itour_lang_settings() {
	$s = get_option( 'itour_lang_settings', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), array(
		'enabled' => array( 'de', 'fr', 'it', 'es', 'pl', 'ru' ),
		'api_key' => '', // Empty: the iTour AI Mode key is used.
		'model'   => 'gemini-flash-lite-latest',
		'live'    => 0, // 0 = translated pages hidden from visitors (only admins can preview them)
	) );
}

/** Languages visitors can use (English first). */
function itour_lang_enabled() {
	$all = itour_lang_all();
	$out = array( 'en' => $all['en'] );
	foreach ( itour_lang_settings()['enabled'] as $c ) {
		if ( isset( $all[ $c ] ) && 'en' !== $c ) {
			$out[ $c ] = $all[ $c ];
		}
	}
	return $out;
}

/** Path of the site home, e.g. "/" or "/staging/" (from the raw option, never filtered). */
function itour_lang_home_path() {
	static $p = null;
	if ( null === $p ) {
		$p = trailingslashit( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ) );
		$p = '/' . ltrim( $p, '/' );
	}
	return $p;
}

/* -------------------------------------------------------------------------
 * 1) Request language: strip /xx/ from the URL so WordPress routes normally.
 * ---------------------------------------------------------------------- */
$GLOBALS['itour_lang']          = 'en';
$GLOBALS['itour_lang_orig_uri'] = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';

( function () {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}
	$codes = implode( '|', array_keys( array_diff_key( itour_lang_enabled(), array( 'en' => 1 ) ) ) );
	if ( ! $codes ) {
		return;
	}
	$uri  = $GLOBALS['itour_lang_orig_uri'];
	$home = itour_lang_home_path();
	if ( preg_match( '#^' . preg_quote( $home, '#' ) . '(' . $codes . ')(?=/|\?|$)#', $uri, $m ) ) {
		$rest = substr( $uri, strlen( $home ) + strlen( $m[1] ) );
		$rest = ( '' !== $rest && '/' === $rest[0] ) ? substr( $rest, 1 ) : $rest;
		$GLOBALS['itour_lang']    = $m[1];
		$_SERVER['REQUEST_URI'] = $home . $rest;
		return;
	}
	// Background requests (admin-ajax, wc-ajax from a translated page) follow the page they came from.
	$is_ajax = ( false !== strpos( $uri, 'admin-ajax.php' ) ) || isset( $_GET['wc-ajax'] );
	if ( $is_ajax ) {
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_PATH ) : '';
		if ( $ref && preg_match( '#^' . preg_quote( $home, '#' ) . '(' . $codes . ')(?=/|$)#', $ref, $m ) ) {
			$GLOBALS['itour_lang'] = $m[1];
		} elseif ( isset( $_COOKIE['itour_lang'] ) && preg_match( '#^(' . $codes . ')$#', $_COOKIE['itour_lang'] ) ) {
			$GLOBALS['itour_lang'] = $_COOKIE['itour_lang'];
		}
	}
} )();

// Give the original URI back once WordPress has routed the request (caches and canonical checks see the real URL).
add_action( 'parse_request', function () {
	if ( 'en' !== $GLOBALS['itour_lang'] ) {
		$_SERVER['REQUEST_URI'] = $GLOBALS['itour_lang_orig_uri'];
	}
}, 1 );

function itour_lang() {
	return $GLOBALS['itour_lang'];
}

/** True when this request should be shown in a non-English language. */
function itour_lang_active() {
	if ( 'en' === itour_lang() ) {
		return false;
	}
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	return true;
}

/** Until the switch is turned on, translated pages are visible to administrators only. */
add_action( 'template_redirect', function () {
	if ( 'en' !== itour_lang() && ! itour_lang_settings()['live'] && ! current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( itour_lang_url_for( 'en' ), 302 );
		exit;
	}
}, 0 );

/* WordPress, WooCommerce, Yoast… load their own language files for the visitor's language. */
add_filter( 'locale', function ( $locale ) {
	if ( itour_lang_active() ) {
		$all = itour_lang_all();
		return $all[ itour_lang() ][1];
	}
	return $locale;
}, 20 );

/* -------------------------------------------------------------------------
 * 2) Links: every front-end link keeps the current language.
 * ---------------------------------------------------------------------- */
function itour_lang_prefix_url( $url, $lang = null ) {
	$lang = $lang ?: itour_lang();
	$base = untrailingslashit( get_option( 'home' ) );
	$alt  = preg_replace( '#^https?:#', '', $base ); // protocol-relative variants
	foreach ( array( $base, $alt ) as $b ) {
		if ( 0 === strpos( $url, $b ) ) {
			$rest = substr( $url, strlen( $b ) );
			if ( preg_match( '#^/(?:' . implode( '|', array_keys( itour_lang_all() ) ) . ')(?=/|\?|$)#', $rest ) ) {
				return $url; // already prefixed
			}
			return 'en' === $lang ? $url : $b . '/' . $lang . ( '' === $rest ? '/' : $rest );
		}
	}
	return $url;
}

add_filter( 'home_url', function ( $url, $path ) {
	if ( ! itour_lang_active() || ! did_action( 'parse_request' ) ) {
		return $url;
	}
	$p = ltrim( (string) $path, '/' );
	if ( preg_match( '#^(wp-json|wp-admin|wp-content|wp-includes|wp-login|wp-cron|xmlrpc|.*sitemap.*\.xml)#', $p ) || false !== strpos( $url, 'wc-api=' ) ) {
		return $url;
	}
	return itour_lang_prefix_url( $url );
}, 20, 2 );

/** URL of the current page in another language. */
function itour_lang_url_for( $lang ) {
	$uri  = $GLOBALS['itour_lang_orig_uri'];
	$home = itour_lang_home_path();
	$rest = substr( $uri, strlen( $home ) );
	$rest = preg_replace( '#^(?:' . implode( '|', array_keys( itour_lang_all() ) ) . ')(?=/|\?|$)/?#', '', (string) $rest );
	$base = untrailingslashit( get_option( 'home' ) );
	return $base . ( 'en' === $lang ? '' : '/' . $lang ) . '/' . $rest;
}

/* Remember the visitor's language for background (AJAX) requests made from cached pages. */
add_action( 'wp_footer', function () {
	if ( ! itour_lang_visible() ) {
		return;
	}
	echo '<script id="itour-lang-cookie">document.cookie="itour_lang=' . esc_js( itour_lang() ) . ';path=/;max-age=31536000;samesite=lax";</script>';
}, 99 );

/* -------------------------------------------------------------------------
 * 3) Storage: one table, one row per object + language + field.
 * ---------------------------------------------------------------------- */
function itour_lang_table() {
	global $wpdb;
	return $wpdb->prefix . 'itour_translations';
}

function itour_lang_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( 'CREATE TABLE ' . itour_lang_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		object_type varchar(20) NOT NULL,
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		lang varchar(8) NOT NULL,
		field varchar(191) NOT NULL,
		value longtext NOT NULL,
		source_hash char(32) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'machine',
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY obj (object_type,object_id,lang,field),
		KEY lang_status (lang,status)
	) {$wpdb->get_charset_collate()};" );
	update_option( 'itour_lang_db', ITOUR_LANG_VERSION );
}
add_action( 'plugins_loaded', function () {
	if ( get_option( 'itour_lang_db' ) !== ITOUR_LANG_VERSION ) {
		itour_lang_install();
	}
} );

/** All translated fields of one object in one language, cached per request. */
function itour_lang_get( $type, $id, $lang ) {
	static $cache = array();
	$k = "$type:$id:$lang";
	if ( ! isset( $cache[ $k ] ) ) {
		global $wpdb;
		$cache[ $k ] = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT field, value, source_hash, status FROM ' . itour_lang_table() . ' WHERE object_type=%s AND object_id=%d AND lang=%s', $type, $id, $lang ) ) as $r ) {
			$cache[ $k ][ $r->field ] = $r;
		}
	}
	return $cache[ $k ];
}

function itour_lang_save( $type, $id, $lang, $field, $value, $hash, $status = 'machine' ) {
	global $wpdb;
	$wpdb->replace( itour_lang_table(), array(
		'object_type' => $type, 'object_id' => $id, 'lang' => $lang, 'field' => $field,
		'value' => $value, 'source_hash' => $hash, 'status' => $status, 'updated_at' => current_time( 'mysql' ),
	) );
}

/* -------------------------------------------------------------------------
 * 4) What gets translated for each post, and how it is shown.
 * ---------------------------------------------------------------------- */
function itour_lang_post_types() {
	return array( 'itour_tour', 'itour_excursion', 'page', 'post' );
}

/** Traveler fields stored as post meta: plain text ones and list ones (only these sub-keys are text). */
function itour_lang_meta_fields() {
	// Other plugins add their own text fields (e.g. Rehlla Booking Policies: rlbp_policy_text).
	return apply_filters( 'itour_lang_meta_fields', itour_lang_core_meta_fields() );
}

function itour_lang_core_meta_fields() {
	return array(
		'text'  => array(),
		'list'  => array(),
		// iTour: meta key => text paths inside its array ("*" = every row).
		'paths' => array(
			'_itour_info'      => array( 'includes', 'excludes', 'faq.*.q', 'faq.*.a' ),
			'_itour_tour'      => array( 'itinerary.*.title', 'itinerary.*.place', 'itinerary.*.meals', 'itinerary.*.overnight', 'itinerary.*.text', 'extras.*.name', 'supplements.*.name' ),
			'_itour_excursion' => array( 'duration', 'meeting_point', 'addons.*.name' ),
			'_itour_seo'       => array( 'title', 'desc', 'og_title', 'og_desc' ),
		),
	);
}

/** Text values of an array at the given paths: "faq.0.q" => text. */
function itour_lang_paths_get( $arr, array $paths ) {
	$out  = array();
	$walk = function ( $node, array $parts, $key ) use ( &$walk, &$out ) {
		if ( ! $parts ) {
			if ( is_string( $node ) && '' !== trim( $node ) ) {
				$out[ $key ] = $node;
			}
			return;
		}
		$part = array_shift( $parts );
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( '*' === $part ? array_keys( $node ) : array( $part ) as $k ) {
			if ( array_key_exists( $k, $node ) ) {
				$walk( $node[ $k ], $parts, '' === $key ? (string) $k : $key . '.' . $k );
			}
		}
	};
	foreach ( $paths as $p ) {
		$walk( $arr, explode( '.', $p ), '' );
	}
	return $out;
}

/** Put translated texts back into the array ("faq.0.q" => text). */
function itour_lang_paths_set( $arr, array $map ) {
	foreach ( $map as $path => $text ) {
		$ref = &$arr;
		$ok  = true;
		foreach ( explode( '.', (string) $path ) as $k ) {
			if ( ! is_array( $ref ) || ! array_key_exists( $k, $ref ) ) {
				$ok = false;
				break;
			}
			$ref = &$ref[ $k ];
		}
		if ( $ok ) {
			$ref = $text;
		}
		unset( $ref );
	}
	return $arr;
}

/** Raw text fields of a post as field => string (lists flattened as meta:key:i:sub). */
function itour_lang_post_fields( $post_id ) {
	$p   = get_post( $post_id );
	$src = array();
	if ( ! $p ) {
		return $src;
	}
	$src['title']   = $p->post_title;
	$src['content'] = $p->post_content;
	$src['excerpt'] = $p->post_excerpt;
	$mf             = itour_lang_meta_fields();
	foreach ( $mf['text'] as $k ) {
		$v = get_post_meta( $post_id, $k, true );
		if ( is_string( $v ) && '' !== trim( $v ) ) {
			$src[ 'meta:' . $k ] = $v;
		}
	}
	foreach ( (array) ( $mf['paths'] ?? array() ) as $k => $paths ) {
		foreach ( itour_lang_paths_get( get_post_meta( $post_id, $k, true ), $paths ) as $path => $text ) {
			$src[ "meta:$k:$path" ] = $text;
		}
	}
	foreach ( $mf['list'] as $k => $subs ) {
		$v = get_post_meta( $post_id, $k, true );
		if ( is_array( $v ) ) {
			foreach ( $v as $i => $row ) {
				foreach ( $subs as $s ) {
					if ( isset( $row[ $s ] ) && is_string( $row[ $s ] ) && '' !== trim( $row[ $s ] ) ) {
						$src[ "meta:$k:$i:$s" ] = $row[ $s ];
					}
				}
			}
		}
	}
	return $src;
}

/** English source fields of a post as field => string, including the SEO title and description. */
function itour_lang_post_source( $post_id ) {
	$p   = get_post( $post_id );
	$src = itour_lang_post_fields( $post_id );
	if ( ! $p ) {
		return $src;
	}
	// Keep fields with visible text, including page-builder pages whose text lives only in shortcode attributes.
	return array_filter( $src, function ( $v ) {
		return '' !== trim( wp_strip_all_tags( preg_replace( '/\[[^\]]*\]/', '', (string) $v ) ) )
			|| preg_match( '/\b(?:title|subtitle|heading|description|more_text|button_text|text|label|placeholder)="[^"]*\p{L}{2}/u', (string) $v );
	} );
}

/** A translated field for the current request's post, or null. */
function itour_lang_t( $post_id, $field ) {
	if ( ! itour_lang_active() || ! $post_id ) {
		return null;
	}
	$t = itour_lang_get( 'post', (int) $post_id, itour_lang() );
	return isset( $t[ $field ] ) && '' !== $t[ $field ]->value ? $t[ $field ]->value : null;
}

add_filter( 'the_title', function ( $title, $id = 0 ) {
	$t = itour_lang_t( $id, 'title' );
	return null !== $t ? $t : $title;
}, 5, 2 );

add_filter( 'single_post_title', function ( $title, $post ) {
	$t = itour_lang_t( $post->ID ?? 0, 'title' );
	return null !== $t ? $t : $title;
}, 5, 2 );

add_filter( 'the_content', function ( $content ) {
	$t = itour_lang_t( get_the_ID(), 'content' );
	if ( null !== $t ) {
		$p = get_post( get_the_ID() );
		if ( $p && $p->post_content === $content ) {
			return $t;
		}
	}
	return itour_lang_content_by_source( $content );
}, 0 );

/** Translation of content from another post rendered inside this page (footer template, forms), found by its English text. */
function itour_lang_content_by_source( $content ) {
	if ( ! itour_lang_active() || '' === trim( (string) $content ) ) {
		return $content;
	}
	global $wpdb;
	$v = $wpdb->get_var( $wpdb->prepare( 'SELECT value FROM ' . itour_lang_table() . " WHERE object_type='post' AND field='content' AND lang=%s AND source_hash=%s LIMIT 1", itour_lang(), md5( $content ) ) );
	return null !== $v && '' !== $v ? $v : $content;
}

// Newsletter form (Mailchimp for WP): labels, placeholder and button.
add_filter( 'mc4wp_form_content', 'itour_lang_content_by_source', 0 );

add_filter( 'get_the_excerpt', function ( $excerpt, $post = null ) {
	$t = itour_lang_t( $post ? $post->ID : get_the_ID(), 'excerpt' );
	return null !== $t ? $t : $excerpt;
}, 5, 2 );

add_filter( 'get_post_metadata', function ( $value, $object_id, $meta_key, $single ) {
	static $busy = false;
	if ( $busy || ! itour_lang_active() || ! $meta_key ) {
		return $value;
	}
	// WordPress takes element [0] of what this filter returns when $single is true, so always wrap.
	$mf = itour_lang_meta_fields();
	if ( in_array( $meta_key, $mf['text'], true ) ) {
		$t = itour_lang_t( $object_id, 'meta:' . $meta_key );
		return null !== $t ? array( $t ) : $value;
	}
	if ( isset( $mf['paths'][ $meta_key ] ) ) {
		$tr = itour_lang_get( 'post', (int) $object_id, itour_lang() );
		if ( ! $tr ) {
			return $value;
		}
		$busy = true;
		$orig = get_post_meta( $object_id, $meta_key, true );
		$busy = false;
		if ( ! is_array( $orig ) ) {
			return $value;
		}
		$map = array();
		foreach ( array_keys( itour_lang_paths_get( $orig, $mf['paths'][ $meta_key ] ) ) as $path ) {
			$f = "meta:$meta_key:$path";
			if ( isset( $tr[ $f ] ) && '' !== $tr[ $f ]->value ) {
				$map[ $path ] = $tr[ $f ]->value;
			}
		}
		return array( itour_lang_paths_set( $orig, $map ) );
	}
	if ( isset( $mf['list'][ $meta_key ] ) ) {
		$tr = itour_lang_get( 'post', (int) $object_id, itour_lang() );
		if ( ! $tr ) {
			return $value;
		}
		$busy = true;
		$orig = get_post_meta( $object_id, $meta_key, true );
		$busy = false;
		if ( ! is_array( $orig ) ) {
			return $value;
		}
		foreach ( $orig as $i => $row ) {
			foreach ( $mf['list'][ $meta_key ] as $s ) {
				$f = "meta:$meta_key:$i:$s";
				if ( isset( $tr[ $f ] ) && is_array( $row ) ) {
					$orig[ $i ][ $s ] = $tr[ $f ]->value;
				}
			}
		}
		return array( $orig );
	}
	return $value;
}, 5, 4 );


/* -------------------------------------------------------------------------
 * 5) SEO: hreflang alternates, canonical, og:locale; untranslated pages stay out of the index.
 * ---------------------------------------------------------------------- */
/** Languages are shown to this viewer: switched on for visitors, or an administrator previewing. */
function itour_lang_visible() {
	return itour_lang_settings()['live'] || current_user_can( 'manage_options' );
}

add_action( 'wp_head', function () {
	if ( is_404() || is_search() || ! itour_lang_settings()['live'] ) {
		return; // while hidden, search engines must not be told about language pages that redirect
	}
	$id = is_singular() ? get_queried_object_id() : 0;
	foreach ( itour_lang_enabled() as $code => $l ) {
		if ( 'en' !== $code && $id && ! itour_lang_get( 'post', $id, $code ) ) {
			continue; // no translation yet: do not advertise it
		}
		echo '<link rel="alternate" hreflang="' . esc_attr( $l[2] ) . '" href="' . esc_url( itour_lang_url_for( $code ) ) . "\" />\n";
	}
	echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( itour_lang_url_for( 'en' ) ) . "\" />\n";
}, 2 );

// A page without a translation in this language stays out of search results (it shows the English text).
add_filter( 'wp_robots', function ( $r ) {
	if ( itour_lang_active() && is_singular() && ! itour_lang_get( 'post', get_queried_object_id(), itour_lang() ) ) {
		unset( $r['index'], $r['max-image-preview'] );
		$r['noindex'] = true;
		$r['follow']  = true;
	}
	return $r;
}, 30 );

/* -------------------------------------------------------------------------
 * 6) Language switcher: [itour_language_switcher]
 * ---------------------------------------------------------------------- */
function itour_lang_flag_url( $code ) {
	$map = array( 'en' => 'gb', 'de' => 'de', 'fr' => 'fr', 'it' => 'it', 'es' => 'es', 'pl' => 'pl', 'ru' => 'ru' );
	return isset( $map[ $code ] ) ? plugins_url( 'flags/' . $map[ $code ] . '.svg', __FILE__ ) : '';
}

/** Flag image, or an empty spacer for languages without a flag file (name only). */
function itour_lang_flag_img( $code ) {
	$url = itour_lang_flag_url( $code );
	return '' !== $url ? '<img src="' . esc_url( $url ) . '" alt="" width="22" height="16">' : '<span style="display:inline-block;width:22px"></span>';
}

function itour_lang_switcher_html() {
	if ( ! itour_lang_visible() ) {
		return '';
	}
	$langs = itour_lang_enabled();
	$cur   = itour_lang();
	$h   = '<div class="itour-langsw"><button type="button" class="itour-langsw-btn" aria-haspopup="true" aria-expanded="false" aria-label="Language: ' . esc_attr( $langs[ $cur ][0] ?? 'English' ) . '">'
		. itour_lang_flag_img( $cur ) . '<span>' . esc_html( strtoupper( $cur ) ) . '</span></button><ul class="itour-langsw-menu" role="menu">';
	foreach ( $langs as $code => $l ) {
		$h .= '<li role="none"><a role="menuitem" hreflang="' . esc_attr( $l[2] ) . '" lang="' . esc_attr( $l[2] ) . '" href="' . esc_url( itour_lang_url_for( $code ) ) . '"' . ( $code === $cur ? ' aria-current="true"' : '' ) . '>' . itour_lang_flag_img( $code ) . '' . esc_html( $l[0] ) . '</a></li>';
	}
	return $h . '</ul></div>';
}
add_shortcode( 'itour_language_switcher', 'itour_lang_switcher_html' );
// WP iTourTMS header: before the account link.
add_action( 'wp_itourtms_header_account', function () {
	echo itour_lang_switcher_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
}, 5 );

add_action( 'wp_head', function () {
	if ( ! itour_lang_visible() ) {
		return;
	}
	?>
<style id="itour-langsw-css">
.itour-langsw{position:relative;display:inline-block}
.itour-langsw-btn{display:flex;align-items:center;gap:6px;background:none;border:0;padding:4px 6px;cursor:pointer;color:inherit;font-weight:600}
.itour-langsw-btn img,.itour-langsw-menu img{border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.08)}
.itour-langsw-menu{display:none;position:absolute;right:0;top:100%;z-index:9999;min-width:170px;margin:6px 0 0;padding:6px;list-style:none;background:#fff;border-radius:10px;box-shadow:0 10px 30px rgba(20,33,61,.18)}
.itour-langsw.open .itour-langsw-menu{display:block}
.itour-langsw-menu a{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:6px;color:#1f2937;text-decoration:none;font-size:14px}
.itour-langsw-menu a:hover,.itour-langsw-menu a[aria-current]{background:color-mix(in srgb,currentColor 8%,transparent)}
</style>
	<?php
} );

add_action( 'wp_footer', function () {
	if ( ! itour_lang_visible() ) {
		return;
	}
	?>
<script id="itour-langsw-js">
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('.itour-langsw-btn');document.querySelectorAll('.itour-langsw').forEach(function(w){var open=b&&w.contains(b)&&!w.classList.contains('open');w.classList.toggle('open',open);w.querySelector('.itour-langsw-btn').setAttribute('aria-expanded',open?'true':'false');});});
</script>
	<?php
} );

require_once ITOUR_LANG_DIR . '/includes/translator.php';
require_once ITOUR_LANG_DIR . '/includes/strings.php';
require_once ITOUR_LANG_DIR . '/includes/sitemap.php';
require_once ITOUR_LANG_DIR . '/includes/source-language.php';
if ( is_admin() ) {
	require_once ITOUR_LANG_DIR . '/includes/admin.php';
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ITOUR_LANG_DIR . '/includes/cli.php';
}
