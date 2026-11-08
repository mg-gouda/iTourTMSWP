<?php
/**
 * Front-end trims: deferred scripts, no emoji or embed scripts, slower Heartbeat,
 * preconnect to Google Fonts, and a database clean-up.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Speed tweaks.
 */
final class Speed {

	/**
	 * Hooks.
	 */
	public static function hooks(): void {
		$s = Settings::all();
		if ( $s['no_emoji'] ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'emoji_svg_url', '__return_false' );
		}
		if ( $s['no_embeds'] ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
			add_action( 'wp_footer', static fn() => wp_dequeue_script( 'wp-embed' ), 1 );
		}
		if ( $s['defer_js'] ) {
			add_action( 'wp_enqueue_scripts', array( self::class, 'defer' ), PHP_INT_MAX );
		}
		if ( (int) $s['heartbeat'] > 0 ) {
			add_filter(
				'heartbeat_settings',
				static function ( $h ) use ( $s ) {
					$h['interval'] = min( 120, max( 15, (int) $s['heartbeat'] ) );
					return $h;
				}
			);
		}
		if ( $s['preconnect_fonts'] ) {
			add_filter( 'wp_resource_hints', array( self::class, 'preconnect' ), 10, 2 );
		}
	}

	/**
	 * Ask WordPress to defer every front-end script. WordPress itself keeps a script blocking
	 * when something depends on it running early (inline code after it, a blocking dependent).
	 */
	public static function defer(): void {
		$scripts = wp_scripts();
		foreach ( $scripts->registered as $handle => $dep ) {
			if ( $dep->src && ! in_array( $handle, array( 'jquery', 'jquery-core', 'jquery-migrate' ), true ) && ! $scripts->get_data( $handle, 'strategy' ) ) {
				$scripts->add_data( $handle, 'strategy', 'defer' );
			}
		}
	}

	/**
	 * Preconnect to Google Fonts when a queued stylesheet uses it.
	 *
	 * @param array<int,mixed> $urls URLs.
	 * @param string           $type Hint type.
	 * @return array<int,mixed>
	 */
	public static function preconnect( $urls, $type ): array {
		if ( 'preconnect' !== $type ) {
			return (array) $urls;
		}
		foreach ( wp_styles()->queue as $h ) {
			$src = (string) ( wp_styles()->registered[ $h ]->src ?? '' );
			if ( false !== strpos( $src, 'fonts.googleapis.com' ) ) {
				$urls[] = 'https://fonts.googleapis.com';
				$urls[] = array(
					'href'        => 'https://fonts.gstatic.com',
					'crossorigin' => 'anonymous',
				);
				break;
			}
		}
		return (array) $urls;
	}

	/**
	 * What the clean-up would remove.
	 *
	 * @return array<string,int>
	 */
	public static function db_counts(): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return array(
			'revisions'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ),
			'autodrafts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ),
			'trash'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ),
			'spam'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" ),
			'transients' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) ),
		);
		// phpcs:enable
	}

	/**
	 * Remove revisions, auto-drafts, trashed posts, spam and trashed comments, expired transients.
	 * Uses WordPress's own delete functions so related data (meta, terms) goes too.
	 *
	 * @return int Items removed.
	 */
	public static function db_clean(): int {
		global $wpdb;
		$n = 0;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' OR post_status IN ('auto-draft','trash') LIMIT 2000" );
		foreach ( $ids as $id ) {
			$n += wp_delete_post( (int) $id, true ) ? 1 : 0;
		}
		$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash') LIMIT 5000" );
		// phpcs:enable
		foreach ( $ids as $id ) {
			$n += wp_delete_comment( (int) $id, true ) ? 1 : 0;
		}
		delete_expired_transients( true );
		Log::add( 'settings', sprintf( 'Database clean-up removed %d items', $n ), wp_get_current_user()->user_login );
		return $n;
	}
}
