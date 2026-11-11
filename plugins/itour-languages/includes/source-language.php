<?php
/**
 * Content written in another language. English stays the master: when a post's text is not English, Gemini detects
 * its language and translates it into English, the English replaces the post's fields, and the author's own text is
 * kept as that language's version (and as a backup in post meta _itour_lang_original). The other languages then follow.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quick local check (no Gemini call): true = English, false = another language, null = too little text to tell.
 * Counts common function words; text mostly in a non-Latin script is never English.
 */
function itour_lang_looks_english( $text ) {
	preg_match_all( '/\b(?:title|subtitle|heading|description|text|label)="([^"]*)"/', $text, $attr );
	$plain = wp_strip_all_tags( preg_replace( '/\[[^\]]*\]/', ' ', $text ) ) . ' ' . implode( ' ', $attr[1] );
	$plain = mb_strtolower( html_entity_decode( $plain, ENT_QUOTES, 'UTF-8' ) );
	$other = preg_match_all( '/[\p{Arabic}\p{Cyrillic}\p{Greek}\p{Hebrew}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Thai}]/u', $plain );
	if ( $other > 30 && $other > preg_match_all( '/[a-z]/', $plain ) ) {
		return false;
	}
	preg_match_all( '/\p{L}+/u', $plain, $m );
	if ( count( $m[0] ) < 12 ) {
		return null;
	}
	static $en = null, $xx = null;
	if ( null === $en ) {
		$en = array_flip( explode( ' ', 'the and of to with for is are you your our on at from this will by an we be it or as that all' ) );
		$xx = array_flip( explode( ' ', 'der die das und ist mit für den dem von zu ein eine nicht auch sie wir le les et des du une est pour avec dans sur nous vous il di che per con una del della gli sono el los las y que por para es w z na się nie jest oraz dla' ) );
	}
	$e = 0;
	$x = 0;
	foreach ( $m[0] as $w ) {
		$e += isset( $en[ $w ] ) ? 1 : 0;
		$x += isset( $xx[ $w ] ) ? 1 : 0;
	}
	return ! ( $x >= 3 && $x > $e );
}

function itour_lang_src_hash( $post_id ) {
	return md5( wp_json_encode( itour_lang_post_fields( $post_id ) ) );
}

/** Remember that this exact text is English, so it is not checked again. */
function itour_lang_src_done( $post_id ) {
	clean_post_cache( $post_id );
	update_post_meta( $post_id, '_itour_lang_src_hash', itour_lang_src_hash( $post_id ) );
	delete_post_meta( $post_id, '_itour_lang_src_error' );
}

/** Is this post waiting to be converted to English (or being converted right now)? */
function itour_lang_src_queued( $post_id ) {
	return ! empty( $GLOBALS['itour_lang_converting'] ) || isset( get_option( 'itour_lang_src_queue', array() )[ $post_id ] );
}

/**
 * Turn a post written in another language into English. Returns 'en' when it already is English, the detected
 * language code after converting, '?' for a dry run that found non-English text, or WP_Error.
 */
function itour_lang_to_english( $post_id, $dry_run = false ) {
	$p      = get_post( $post_id );
	$fields = array_filter( itour_lang_post_fields( $post_id ), function ( $v ) {
		return '' !== trim( (string) $v );
	} );
	if ( ! $p || ! $fields ) {
		return 'en';
	}
	if ( false !== itour_lang_looks_english( implode( "\n", $fields ) ) ) {
		if ( ! $dry_run ) {
			itour_lang_src_done( $post_id );
		}
		return 'en';
	}
	if ( $dry_run ) {
		return '?';
	}
	// Raw Yoast templates, so %%title%% style variables survive.
	foreach ( array( '_yoast_wpseo_title' => 'yoast:title', '_yoast_wpseo_metadesc' => 'yoast:desc' ) as $k => $f ) {
		$v = (string) get_post_meta( $post_id, $k, true );
		if ( preg_match( '/\p{L}{2}/u', preg_replace( '/%%[a-z_]+%%/', '', $v ) ) ) {
			$fields[ $f ] = $v;
		}
	}
	$en   = array();
	$from = '';
	foreach ( itour_lang_chunks( $fields ) as $n => $chunk ) {
		if ( $n ) {
			sleep( 6 );
		}
		$out = itour_lang_gemini( $chunk, 'en', 4, 120, 'auto' );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$from = $from ? $from : strtolower( substr( (string) ( $out['__source_lang'] ?? '' ), 0, 2 ) );
		$bad  = function ( $f, $v, $out ) {
			$t = isset( $out[ $f ] ) && is_string( $out[ $f ] ) ? trim( $out[ $f ] ) : '';
			return '' === $t || ( 'content' === $f && ! itour_lang_same_markup( $v, $t ) );
		};
		// The model sometimes drops a key or changes layout: ask once more for just those fields.
		$retry = array();
		foreach ( $chunk as $f => $v ) {
			if ( $bad( $f, $v, $out ) ) {
				$retry[ $f ] = $v;
			}
		}
		if ( $retry ) {
			sleep( 6 );
			$again = itour_lang_gemini( $retry, 'en', 3, 120, 'auto' );
			if ( is_wp_error( $again ) ) {
				return $again;
			}
			$out = array_merge( $out, $again );
		}
		foreach ( $chunk as $f => $v ) {
			if ( $bad( $f, $v, $out ) ) {
				return new WP_Error( 'rl_markup', 'Gemini returned no usable English for the ' . $f . ' field.' );
			}
			$en[ $f ] = trim( $out[ $f ] );
		}
	}
	if ( '' === $from || 'en' === $from ) {
		itour_lang_src_done( $post_id );
		return 'en';
	}

	$GLOBALS['itour_lang_converting'] = true;
	$lists                         = array();
	foreach ( $en as $f => $t ) {
		if ( 'yoast:title' === $f || 'yoast:desc' === $f ) {
			update_post_meta( $post_id, 'yoast:title' === $f ? '_yoast_wpseo_title' : '_yoast_wpseo_metadesc', wp_slash( $t ) );
		} elseif ( preg_match( '/^meta:([^:]+)$/', $f, $m ) ) {
			update_post_meta( $post_id, $m[1], wp_slash( $t ) );
		} elseif ( preg_match( '/^meta:([^:]+):([^:]+):([^:]+)$/', $f, $m ) ) {
			$lists[ $m[1] ][ $m[2] ][ $m[3] ] = $t;
		}
	}
	foreach ( $lists as $k => $rows ) {
		$v = get_post_meta( $post_id, $k, true );
		foreach ( $rows as $i => $subs ) {
			foreach ( $subs as $s => $t ) {
				if ( isset( $v[ $i ] ) && is_array( $v[ $i ] ) ) {
					$v[ $i ][ $s ] = $t;
				}
			}
		}
		update_post_meta( $post_id, $k, wp_slash( $v ) );
	}

	// The post itself is saved last, so Yoast and the theme rebuild their copies from the English fields.
	$upd = array( 'ID' => $post_id );
	foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt' ) as $f => $col ) {
		if ( isset( $en[ $f ] ) ) {
			$upd[ $col ] = $en[ $f ];
		}
	}
	// A slug made from the original title follows the English one (WordPress redirects the old address).
	if ( isset( $en['title'] ) && $p->post_name && 0 === strpos( urldecode( $p->post_name ), urldecode( sanitize_title( $p->post_title ) ) ) ) {
		$upd['post_name'] = sanitize_title( $en['title'] );
	}
	// Cron runs without a user, so kses would strip iframes and other markup the author was allowed to use.
	kses_remove_filters();
	wp_update_post( wp_slash( $upd ) );
	kses_init();

	update_post_meta( $post_id, '_itour_lang_original', wp_slash( array( 'lang' => $from, 'date' => current_time( 'mysql' ), 'fields' => $fields ) ) );
	clean_post_cache( $post_id );
	if ( isset( itour_lang_enabled()[ $from ] ) ) {
		// The author's SEO texts too, unless they are built from variables that now hold English text.
		foreach ( array( 'yoast:title' => 'seo_title', 'yoast:desc' => 'seo_desc' ) as $y => $f ) {
			if ( isset( $fields[ $y ] ) && function_exists( 'wpseo_replace_vars' ) && ! preg_match( '/%%(?:title|excerpt|excerpt_only|category|term_title|parent_title)%%/', $fields[ $y ] ) ) {
				$fields[ $f ] = wpseo_replace_vars( $fields[ $y ], get_post( $post_id ) );
			}
		}
		foreach ( itour_lang_post_source( $post_id ) as $f => $v ) {
			if ( isset( $fields[ $f ] ) && $fields[ $f ] !== $v ) {
				itour_lang_save( 'post', $post_id, $from, $f, $fields[ $f ], md5( $v ), 'original' );
			}
		}
	}
	itour_lang_src_done( $post_id );
	$GLOBALS['itour_lang_converting'] = false;
	if ( 'publish' === get_post_status( $post_id ) ) {
		itour_lang_queue_add( $post_id );
	}
	return $from;
}

// Saved text that is (or may be) in another language goes to the conversion queue instead of straight to translation.
add_action( 'save_post', function ( $post_id, $post ) {
	if ( ! empty( $GLOBALS['itour_lang_converting'] ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id )
		|| ! in_array( $post->post_status, array( 'publish', 'pending', 'future' ), true ) || ! in_array( $post->post_type, itour_lang_post_types(), true )
		|| ! itour_lang_settings()['api_key'] || get_post_meta( $post_id, '_itour_lang_src_hash', true ) === itour_lang_src_hash( $post_id ) ) {
		return;
	}
	if ( true !== itour_lang_looks_english( implode( "\n", itour_lang_post_fields( $post_id ) ) ) ) {
		$q             = get_option( 'itour_lang_src_queue', array() );
		$q[ $post_id ] = time();
		update_option( 'itour_lang_src_queue', $q, false );
	}
}, 15, 2 );

// Runs before the translation queue, a couple of posts per run.
add_action( 'itour_lang_cron', function () {
	$q = get_option( 'itour_lang_src_queue', array() );
	if ( ! $q || get_transient( 'itour_lang_src_lock' ) ) {
		return;
	}
	set_transient( 'itour_lang_src_lock', 1, 110 );
	foreach ( array_slice( array_keys( $q ), 0, 2 ) as $pid ) {
		$r = itour_lang_to_english( $pid );
		if ( is_wp_error( $r ) && in_array( $r->get_error_code(), array( 'rl_quota', 'rl_rate', 'rl_no_key', 'rl_fail' ), true ) ) {
			break; // try again next run
		}
		unset( $q[ $pid ] );
		if ( is_wp_error( $r ) ) {
			update_post_meta( $pid, '_itour_lang_src_error', $r->get_error_message() );
		} elseif ( 'en' === $r && 'publish' === get_post_status( $pid ) ) {
			itour_lang_queue_add( $pid ); // English after all: translate as usual
		}
	}
	update_option( 'itour_lang_src_queue', $q, false );
	delete_transient( 'itour_lang_src_lock' );
}, 5 );

/** Line for the Translations box on the edit screen. */
function itour_lang_src_note( $post ) {
	$all  = itour_lang_all();
	$orig = get_post_meta( $post->ID, '_itour_lang_original', true );
	$err  = get_post_meta( $post->ID, '_itour_lang_src_error', true );
	if ( itour_lang_src_queued( $post->ID ) ) {
		echo '<p style="color:#dba617">⏳ This text is not in English. It will be translated into English within a few minutes; the original stays as its own language version.</p>';
	} elseif ( $err ) {
		echo '<p style="color:#d63638">✖ Could not translate into English: ' . esc_html( $err ) . ' Update the post to try again.</p>';
	} elseif ( is_array( $orig ) && ! empty( $orig['lang'] ) ) {
		$name = isset( $all[ $orig['lang'] ] ) ? $all[ $orig['lang'] ][0] : strtoupper( $orig['lang'] );
		echo '<p class="description">Written in <strong>' . esc_html( $name ) . '</strong>, translated into English on ' . esc_html( mysql2date( get_option( 'date_format' ), $orig['date'] ) ) . '.'
			. ( isset( itour_lang_enabled()[ $orig['lang'] ] ) ? ' The ' . esc_html( $name ) . ' version keeps the author’s own text.' : '' ) . '</p>';
	}
}
