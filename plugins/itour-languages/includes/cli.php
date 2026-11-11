<?php
/** WP-CLI: wp itour-lang translate|status */

defined( 'ABSPATH' ) || exit;

class ITour_Lang_CLI {
	/**
	 * Translate published content (only missing or outdated fields).
	 *
	 * ## OPTIONS
	 * [--lang=<codes>]  : Comma-separated languages (default: all enabled).
	 * [--type=<types>]  : Comma-separated post types (default: all translatable).
	 * [--ids=<ids>]     : Comma-separated post IDs.
	 * [--delay=<sec>]   : Pause between Gemini requests (default 7 — free tier friendly).
	 * [--force]         : Re-translate even if up to date.
	 */
	public function translate( $args, $a ) {
		$langs = ! empty( $a['lang'] ) ? array_map( 'trim', explode( ',', $a['lang'] ) ) : array_keys( array_diff_key( itour_lang_enabled(), array( 'en' => 1 ) ) );
		$q     = array( 'post_type' => ! empty( $a['type'] ) ? array_map( 'trim', explode( ',', $a['type'] ) ) : itour_lang_post_types(), 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' );
		if ( ! empty( $a['ids'] ) ) {
			$q['post__in'] = array_map( 'absint', explode( ',', $a['ids'] ) );
		}
		$ids   = get_posts( $q );
		$delay = isset( $a['delay'] ) ? max( 0, (int) $a['delay'] ) : 7;
		$force = isset( $a['force'] );
		$n     = 0;
		$total = count( $ids ) * count( $langs );
		WP_CLI::log( count( $ids ) . ' item(s) × ' . count( $langs ) . ' language(s)' );
		foreach ( $langs as $l ) {
			foreach ( $ids as $id ) {
				$n++;
				$r = itour_lang_translate_post( $id, $l, $force );
				if ( is_wp_error( $r ) ) {
					WP_CLI::warning( "[$n/$total] $l #$id: " . $r->get_error_message() );
					if ( 'rl_no_key' === $r->get_error_code() ) {
						return;
					}
					if ( 'rl_quota' === $r->get_error_code() || false !== strpos( $r->get_error_message(), 'RESOURCE_EXHAUSTED' ) ) {
						WP_CLI::warning( 'Daily free-tier quota reached — run the command again tomorrow; finished items are kept.' );
						return;
					}
				} else {
					WP_CLI::log( "[$n/$total] $l #$id " . get_the_title( $id ) . ': ' . ( $r ? "$r field(s)" : 'up to date' ) );
				}
				if ( $r && $delay ) {
					sleep( $delay );
				}
			}
		}
		WP_CLI::success( 'Finished.' );
	}

	/**
	 * Translate the interface-string catalogue (buttons, labels, menus, categories).
	 *
	 * ## OPTIONS
	 * [--lang=<codes>] : Comma-separated languages (default: all enabled).
	 */
	public function strings( $args, $a ) {
		global $wpdb;
		$langs = ! empty( $a['lang'] ) ? array_map( 'trim', explode( ',', $a['lang'] ) ) : array_keys( array_diff_key( itour_lang_enabled(), array( 'en' => 1 ) ) );
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . itour_lang_table() . " WHERE object_type='string' AND lang='en'" );
		WP_CLI::log( "$total English strings in the catalogue" );
		foreach ( $langs as $l ) {
			do {
				$r = itour_lang_translate_strings( $l, 600 );
				if ( is_wp_error( $r ) ) {
					WP_CLI::warning( "$l: " . $r->get_error_message() );
					break;
				}
				WP_CLI::log( "$l: $r string(s) translated" );
			} while ( $r > 0 );
		}
		WP_CLI::success( 'Strings finished.' );
	}

	/**
	 * Find content written in another language and translate it into English (the original stays as its language version).
	 *
	 * ## OPTIONS
	 * [--ids=<ids>] : Comma-separated post IDs.
	 * [--dry-run]   : Only list the items that are not in English.
	 */
	public function english( $args, $a ) {
		$q = array( 'post_type' => itour_lang_post_types(), 'post_status' => array( 'publish', 'pending', 'future' ), 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' );
		if ( ! empty( $a['ids'] ) ) {
			$q['post__in'] = array_map( 'absint', explode( ',', $a['ids'] ) );
		}
		$found = 0;
		foreach ( get_posts( $q ) as $id ) {
			$r = itour_lang_to_english( $id, isset( $a['dry-run'] ) );
			if ( is_wp_error( $r ) ) {
				WP_CLI::warning( "#$id " . get_the_title( $id ) . ': ' . $r->get_error_message() );
				if ( in_array( $r->get_error_code(), array( 'rl_quota', 'rl_no_key' ), true ) ) {
					return;
				}
			} elseif ( 'en' !== $r ) {
				$found++;
				WP_CLI::log( "#$id " . get_the_title( $id ) . ( '?' === $r ? ': not English' : ": translated from $r" ) );
			}
		}
		WP_CLI::success( "$found item(s) not in English." );
	}

	/** Progress per language. */
	public function status() {
		$ids = get_posts( array( 'post_type' => itour_lang_post_types(), 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$c   = array();
		foreach ( $ids as $id ) {
			foreach ( itour_lang_post_status( $id ) as $l => $s ) {
				$c[ $l ][ $s ] = ( $c[ $l ][ $s ] ?? 0 ) + 1;
			}
		}
		global $wpdb;
		$str = $wpdb->get_results( 'SELECT lang, COUNT(*) n FROM ' . itour_lang_table() . " WHERE object_type='string' GROUP BY lang", OBJECT_K );
		foreach ( $c as $l => $s ) {
			WP_CLI::log( sprintf( '%s: %d done, %d outdated, %d missing (of %d) | strings %d/%d', strtoupper( $l ), $s['done'] ?? 0, $s['outdated'] ?? 0, $s['missing'] ?? 0, count( $ids ), $str[ $l ]->n ?? 0, $str['en']->n ?? 0 ) );
		}
	}
}

if ( ! function_exists( 'itour_lang_post_status' ) ) {
	require_once __DIR__ . '/admin.php';
}
WP_CLI::add_command( 'itour-lang', 'ITour_Lang_CLI' );
