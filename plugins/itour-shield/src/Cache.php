<?php
/**
 * Page cache management: the advanced-cache.php drop-in, WP_CACHE in wp-config.php,
 * clearing on content changes, refilling the cache, and the admin-bar button.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Cache files and switches.
 */
final class Cache {

	private const SIGNATURE = 'iTour Shield page cache';
	private const CONFIG    = "define( 'WP_CACHE', true ); // Added by iTour Shield";

	/**
	 * Hooks.
	 */
	public static function hooks(): void {
		add_action( 'itour_shield_daily', array( self::class, 'gc' ) );
		add_action( 'itour_shield_preload', array( self::class, 'preload' ) );
		if ( ! Settings::get( 'cache' ) ) {
			return;
		}
		add_action( 'transition_post_status', array( self::class, 'post_status' ), 10, 2 );
		foreach ( array( 'deleted_post', 'comment_post', 'edit_comment', 'wp_set_comment_status', 'switch_theme', 'customize_save_after', 'wp_update_nav_menu', 'activated_plugin', 'deactivated_plugin', 'upgrader_process_complete', 'edited_term', 'delete_term' ) as $hook ) {
			add_action( $hook, array( self::class, 'purge' ) );
		}
		add_action( 'updated_option', array( self::class, 'option_changed' ) );
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar' ), 90 );
	}

	/**
	 * Clear when a post goes live or stops being live.
	 *
	 * @param string $to   New status.
	 * @param string $from Old status.
	 */
	public static function post_status( $to, $from ): void {
		if ( 'publish' === $to || 'publish' === $from ) {
			self::purge();
		}
	}

	/**
	 * Folder for cache files.
	 */
	public static function dir(): string {
		return WP_CONTENT_DIR . '/cache/itour-shield';
	}

	/**
	 * A setting changed by someone in the admin (not transients, cron or our own counters): clear.
	 *
	 * @param string $name Option name.
	 */
	public static function option_changed( $name ): void {
		if ( current_user_can( 'manage_options' ) && ! preg_match( '/^(_site)?_transient|^cron$|^itour_shield|^rewrite_rules$|^active_plugins$|_lock$|^recently_/', (string) $name ) ) {
			self::purge();
		}
	}

	/**
	 * Turn the cache on or off to match the setting.
	 */
	public static function sync(): void {
		if ( ! Settings::get( 'cache' ) ) {
			self::disable();
			return;
		}
		$s = Settings::all();
		wp_mkdir_p( self::dir() . '/pages' );
		$exclude = array_filter( array_map( 'trim', explode( "\n", (string) $s['cache_exclude'] ) ) );
		foreach ( array( $s['login_slug'], $s['rest_prefix'] ) as $p ) {
			if ( '' !== $p ) {
				$exclude[] = '/' . $p;
			}
		}
		self::put(
			self::dir() . '/config.php',
			"<?php\n// iTour Shield cache settings. Rewritten when the settings are saved.\nreturn " . var_export( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				array(
					'ttl'     => max( 1, (int) $s['cache_ttl'] ) * HOUR_IN_SECONDS,
					'exclude' => array_values( $exclude ),
				),
				true
			) . ";\n"
		);
		self::put( self::dir() . '/index.php', "<?php\n// Silence.\n" );
		$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
		if ( ! is_file( $dropin ) || self::ours( $dropin ) ) {
			self::put( $dropin, "<?php\n// " . self::SIGNATURE . ". Added and removed by the iTour Shield plugin.\nif ( is_readable( " . var_export( ITOUR_SHIELD_DIR . '/cache-serve.php', true ) . " ) ) {\n\tinclude " . var_export( ITOUR_SHIELD_DIR . '/cache-serve.php', true ) . ";\n}\n" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
		self::wp_cache( true );
		self::purge();
	}

	/**
	 * Remove the drop-in, the WP_CACHE line and every cached page.
	 */
	public static function disable(): void {
		$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
		if ( is_file( $dropin ) && self::ours( $dropin ) ) {
			wp_delete_file( $dropin );
		}
		self::wp_cache( false );
		self::purge();
		wp_delete_file( self::dir() . '/config.php' );
	}

	/**
	 * Is a drop-in file ours?
	 *
	 * @param string $file File.
	 */
	private static function ours( string $file ): bool {
		return false !== strpos( (string) file_get_contents( $file ), self::SIGNATURE ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Write a file if its content changed.
	 *
	 * @param string $file File.
	 * @param string $code Content.
	 */
	private static function put( string $file, string $code ): void {
		if ( ! is_file( $file ) || file_get_contents( $file ) !== $code ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $file, $code, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * The wp-config.php file.
	 */
	private static function config_file(): string {
		return is_file( ABSPATH . 'wp-config.php' ) ? ABSPATH . 'wp-config.php' : dirname( ABSPATH ) . '/wp-config.php';
	}

	/**
	 * Add or remove our WP_CACHE line. Never touches a WP_CACHE line someone else wrote.
	 *
	 * @param bool $on Add.
	 * @return bool WP_CACHE will be on after this (when $on).
	 */
	private static function wp_cache( bool $on ): bool {
		$file = self::config_file();
		if ( ! is_file( $file ) ) {
			return false;
		}
		$src   = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$clean = (string) preg_replace( '/^' . preg_quote( self::CONFIG, '/' ) . '\R/m', '', $src );
		$new   = $clean;
		if ( $on ) {
			if ( preg_match( '/define\(\s*[\'"]WP_CACHE[\'"]/', $clean ) ) {
				return defined( 'WP_CACHE' ) && WP_CACHE;
			}
			$new = (string) preg_replace( '/^<\?php\R/', "<?php\n" . self::CONFIG . "\n", $clean, 1 );
		}
		if ( $new !== $src ) {
			if ( ! wp_is_writable( $file ) ) {
				return false;
			}
			file_put_contents( $file, $new, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $on && false !== strpos( $new, self::CONFIG );
	}

	/**
	 * Cache state for the admin screen.
	 *
	 * @return array{dropin:string,wp_cache:bool,pages:int,bytes:int}
	 */
	public static function status(): array {
		$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
		$files  = glob( self::dir() . '/pages/*.html' );
		$files  = $files ? $files : array();
		$src    = is_file( self::config_file() ) ? (string) file_get_contents( self::config_file() ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		return array(
			'dropin'   => ! is_file( $dropin ) ? 'none' : ( self::ours( $dropin ) ? 'ours' : 'other' ),
			'wp_cache' => ( defined( 'WP_CACHE' ) && WP_CACHE ) || false !== strpos( $src, self::CONFIG ),
			'pages'    => count( $files ),
			'bytes'    => (int) array_sum( array_map( 'filesize', $files ) ),
		);
	}

	/**
	 * Delete every cached page and queue a refill.
	 */
	public static function purge(): void {
		foreach ( (array) glob( self::dir() . '/pages/*' ) as $f ) {
			wp_delete_file( (string) $f );
		}
		if ( Settings::get( 'cache' ) && Settings::get( 'cache_preload' ) && ! wp_next_scheduled( 'itour_shield_preload' ) ) {
			wp_schedule_single_event( time() + 60, 'itour_shield_preload' );
		}
	}

	/**
	 * Delete expired pages (daily).
	 */
	public static function gc(): void {
		$limit = time() - max( 1, (int) Settings::get( 'cache_ttl' ) ) * HOUR_IN_SECONDS;
		foreach ( (array) glob( self::dir() . '/pages/*' ) as $f ) {
			if ( filemtime( (string) $f ) < $limit ) {
				wp_delete_file( (string) $f );
			}
		}
		foreach ( (array) glob( self::dir() . '/rate/*' ) as $f ) {
			if ( filemtime( (string) $f ) < time() - HOUR_IN_SECONDS ) {
				wp_delete_file( (string) $f );
			}
		}
	}

	/**
	 * Visit the main pages so the first real visitor gets a cached copy.
	 */
	public static function preload(): void {
		$urls = array( home_url( '/' ) );
		foreach ( get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 60,
				'orderby'        => 'modified',
				'has_password'   => false,
				'fields'         => 'ids',
			)
		) as $id ) {
			$urls[] = (string) get_permalink( (int) $id );
		}
		$stop = time() + 50;
		foreach ( array_unique( $urls ) as $url ) {
			if ( time() > $stop ) {
				break;
			}
			wp_remote_get(
				$url,
				array(
					'timeout'    => 10,
					'sslverify'  => false,
					'user-agent' => 'iTour Shield preload',
				)
			);
		}
	}

	/**
	 * "Clear cache" in the admin bar.
	 *
	 * @param \WP_Admin_Bar $bar Bar.
	 */
	public static function admin_bar( $bar ): void {
		if ( current_user_can( 'manage_options' ) ) {
			$bar->add_node(
				array(
					'id'    => 'itour-shield-purge',
					'title' => __( 'Clear cache', 'itour-shield' ),
					'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=itour_shield_purge' ), 'itour_shield_purge' ),
				)
			);
		}
	}
}
