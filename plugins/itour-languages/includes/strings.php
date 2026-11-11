<?php
/**
 * Interface strings: theme/plugin texts (gettext), menu labels, category names and dynamic texts from
 * Rehlla blocks. Every string shown on a front-end page is recorded once in English (lang 'en', object_type
 * 'string'); a background job translates the catalogue in batches. Lookups are one query per request.
 */

defined( 'ABSPATH' ) || exit;

/** Text domains whose strings we translate ourselves (WordPress, WooCommerce, Yoast… use official language packs). */
function itour_lang_string_domains() {
	return array( 'itour', 'wp-itourtms', 'wp-itourtms-child', 'itour-core', 'itour-tours', 'itour-excursions', 'itour-transfers', 'itour-forms', 'itour-slider', 'itour-seo', 'itour-crm', 'itour-mobile-app', 'itour-ai-mode', 'itour-documents', 'itour-registration-guard' );
}

function itour_lang_string_key( $text, $domain, $context = '' ) {
	return md5( $domain . "\x04" . $context . "\x04" . $text );
}

/** All strings of one language (key => text), loaded once per request. */
function itour_lang_strings( $lang ) {
	static $cache = array();
	if ( ! isset( $cache[ $lang ] ) ) {
		global $wpdb;
		$cache[ $lang ] = $wpdb->get_results( $wpdb->prepare( 'SELECT field, value FROM ' . itour_lang_table() . " WHERE object_type='string' AND lang=%s", $lang ), OBJECT_K );
		foreach ( $cache[ $lang ] as $k => $r ) {
			$cache[ $lang ][ $k ] = $r->value;
		}
	}
	return $cache[ $lang ];
}

/** Strings seen on this page that are not yet in the English catalogue; saved once at shutdown. */
$GLOBALS['itour_lang_new_strings'] = array();

function itour_lang_string( $text, $domain, $context = '' ) {
	if ( ! is_string( $text ) || '' === trim( $text ) || is_admin() && ! wp_doing_ajax() ) {
		return $text;
	}
	// Only human text: skip pure numbers, symbols, placeholders or very long blobs.
	if ( strlen( $text ) > 2000 || ! preg_match( '/\p{L}{2,}/u', $text ) ) {
		return $text;
	}
	$key = itour_lang_string_key( $text, $domain, $context );
	$en  = itour_lang_strings( 'en' );
	if ( ! isset( $en[ $key ] ) ) {
		if ( 'en' === itour_lang() ) {
			$GLOBALS['itour_lang_new_strings'][ $key ] = $text; // English pages only, so translated text is never taken as a source
		}
		return $text;
	}
	if ( ! itour_lang_active() ) {
		return $text;
	}
	$tr = itour_lang_strings( itour_lang() );
	return isset( $tr[ $key ] ) && '' !== $tr[ $key ] ? $tr[ $key ] : $text;
}

/** Translate a dynamic text (block titles, menu labels…) through the catalogue. */
function itour_lang__( $text ) {
	return itour_lang_string( $text, 'itour' );
}

add_action( 'shutdown', function () {
	$new = $GLOBALS['itour_lang_new_strings'];
	if ( ! $new || count( $new ) > 5000 ) {
		return; // nothing new, or a runaway page: avoid huge writes
	}
	global $wpdb;
	$now = current_time( 'mysql' );
	foreach ( array_chunk( $new, 200, true ) as $chunk ) {
		$rows = array();
		foreach ( $chunk as $k => $t ) {
			$rows[] = $wpdb->prepare( "('string',0,'en',%s,%s,%s,'source',%s)", $k, $t, md5( $t ), $now );
		}
		$wpdb->query( 'INSERT IGNORE INTO ' . itour_lang_table() . ' (object_type,object_id,lang,field,value,source_hash,status,updated_at) VALUES ' . implode( ',', $rows ) ); // phpcs:ignore
	}
} );

/* gettext for our domains, on the front end only. */
add_filter( 'gettext', function ( $translation, $text, $domain ) {
	return in_array( $domain, itour_lang_string_domains(), true ) && $translation === $text ? itour_lang_string( $text, $domain ) : $translation;
}, 20, 3 );
add_filter( 'gettext_with_context', function ( $translation, $text, $context, $domain ) {
	return in_array( $domain, itour_lang_string_domains(), true ) && $translation === $text ? itour_lang_string( $text, $domain, $context ) : $translation;
}, 20, 4 );
add_filter( 'ngettext', function ( $translation, $single, $plural, $number, $domain ) {
	if ( ! in_array( $domain, itour_lang_string_domains(), true ) || ( $translation !== $single && $translation !== $plural ) ) {
		return $translation;
	}
	return itour_lang_string( 1 === (int) $number ? $single : $plural, $domain );
}, 20, 5 );

/* Menu labels and category / type names. */
add_filter( 'wp_nav_menu_objects', function ( $items ) {
	foreach ( $items as $it ) {
		$it->title = itour_lang__( $it->title );
		if ( ! empty( $it->attr_title ) ) {
			$it->attr_title = itour_lang__( $it->attr_title );
		}
	}
	return $items;
}, 20 );

add_filter( 'get_term', function ( $term ) {
	if ( $term instanceof WP_Term && ! is_admin() && 'nav_menu' !== $term->taxonomy ) {
		$term->name = itour_lang_string( $term->name, 'itour', 'term' );
		if ( '' !== trim( (string) $term->description ) ) {
			$term->description = itour_lang_string( $term->description, 'itour', 'term' );
		}
	}
	return $term;
}, 20 );

/* Section headings that print a taxonomy label (e.g. "Suitability", "Durations" on tour pages). */
add_action( 'wp', function () {
	if ( is_admin() ) {
		return;
	}
	foreach ( get_taxonomies( array( '_builtin' => false ), 'objects' ) as $tax ) {
		$tax->label        = itour_lang_string( $tax->label, 'itour', 'taxonomy' );
		$tax->labels->name = itour_lang_string( $tax->labels->name, 'itour', 'taxonomy' );
	}
} );

/* Search form tab titles are stored URL-encoded in the shortcode, so they go through the catalogue when rendered. */
add_filter( 'do_shortcode_tag', function ( $out, $tag, $attr ) {
	if ( 'st_search_form_new' !== $tag || empty( $attr['service_items'] ) || is_admin() ) {
		return $out;
	}
	foreach ( (array) json_decode( urldecode( $attr['service_items'] ), true ) as $item ) {
		if ( ! empty( $item['tab_title'] ) && is_string( $item['tab_title'] ) ) {
			$t   = esc_html( $item['tab_title'] );
			$out = str_replace( '>' . $t . '<', '>' . esc_html( itour_lang__( $item['tab_title'] ) ) . '<', $out );
		}
	}
	return $out;
}, 10, 3 );

/* Footer copyright and other Theme Options texts shown on the front end (recorded on English pages). */
add_filter( 'option_option_tree', function ( $o ) {
	if ( ! is_array( $o ) || is_admin() ) {
		return $o;
	}
	foreach ( array( 'st_text_copyright' ) as $k ) {
		if ( ! empty( $o[ $k ] ) && is_string( $o[ $k ] ) ) {
			$o[ $k ] = itour_lang_string( $o[ $k ], 'itour', 'option' );
		}
	}
	return $o;
}, 30 );

/* ---------------- Translating the catalogue ---------------- */

/** Translate missing strings for one language, up to $limit strings, in batches of 60. Returns count or WP_Error. */
function itour_lang_translate_strings( $lang, $limit = 600 ) {
	global $wpdb;
	$t    = itour_lang_table();
	$todo = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.field, e.value FROM $t e LEFT JOIN $t x ON x.object_type='string' AND x.lang=%s AND x.field=e.field
		 WHERE e.object_type='string' AND e.lang='en' AND x.id IS NULL LIMIT %d",
		$lang,
		$limit
	), OBJECT_K );
	$done = 0;
	foreach ( array_chunk( $todo, 60, true ) as $batch ) {
		$in  = array();
		foreach ( $batch as $k => $r ) {
			$in[ $k ] = $r->value;
		}
		$out = itour_lang_gemini( $in, $lang );
		if ( is_wp_error( $out ) ) {
			return $done ? $done : $out;
		}
		foreach ( $in as $k => $src ) {
			$v = isset( $out[ $k ] ) && is_string( $out[ $k ] ) ? trim( $out[ $k ] ) : '';
			// printf placeholders (%s, %1$s, %d) must survive or the theme would break.
			preg_match_all( '/%(\d+\$)?[sdf]/', $src, $a );
			preg_match_all( '/%(\d+\$)?[sdf]/', $v, $b );
			if ( '' === $v || count( $a[0] ) !== count( $b[0] ) ) {
				continue;
			}
			itour_lang_save( 'string', 0, $lang, $k, $v, md5( $src ) );
			$done++;
		}
		sleep( 7 );
	}
	return $done;
}

// Keep the catalogue translated: a few batches per cron run, all enabled languages.
add_action( 'itour_lang_cron', function () {
	if ( get_transient( 'itour_lang_str_lock' ) || ! itour_lang_settings()['api_key'] ) {
		return;
	}
	set_transient( 'itour_lang_str_lock', 1, 110 );
	foreach ( array_keys( array_diff_key( itour_lang_enabled(), array( 'en' => 1 ) ) ) as $l ) {
		$r = itour_lang_translate_strings( $l, 60 );
		if ( is_wp_error( $r ) ) {
			break;
		}
	}
	delete_transient( 'itour_lang_str_lock' );
}, 20 );
