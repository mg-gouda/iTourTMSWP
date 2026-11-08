<?php
/**
 * Hardening: security headers, file editor off, XML-RPC off, no username discovery,
 * WordPress version hidden, application passwords off. Server rules live in Htaccess.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Small switches.
 */
final class Hardening {

	/**
	 * Hooks.
	 */
	public static function hooks(): void {
		$s = Settings::all();
		if ( $s['headers'] ) {
			add_action( 'send_headers', array( self::class, 'headers' ) );
			add_action( 'admin_init', array( self::class, 'headers' ) );
			add_action( 'login_init', array( self::class, 'headers' ) );
		}
		if ( $s['no_file_edit'] && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core constant.
		}
		if ( $s['no_xmlrpc'] ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array' );
			add_filter( 'wp_headers', static fn( $h ) => array_diff_key( (array) $h, array( 'X-Pingback' => 1 ) ) );
			remove_action( 'wp_head', 'rsd_link' );
		}
		if ( $s['no_user_enum'] ) {
			add_action( 'parse_request', array( self::class, 'author_query' ) );
			add_filter( 'rest_request_before_callbacks', array( self::class, 'rest_users' ), 10, 3 );
			add_filter( 'wp_sitemaps_add_provider', static fn( $p, $name ) => 'users' === $name ? false : $p, 10, 2 );
			add_filter( 'oembed_response_data', static fn( $d ) => array_diff_key( (array) $d, array( 'author_url' => 1 ) ) );
		}
		if ( $s['hide_version'] ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
			add_filter( 'style_loader_src', array( self::class, 'strip_version' ) );
			add_filter( 'script_loader_src', array( self::class, 'strip_version' ) );
		}
		if ( $s['no_app_passwords'] ) {
			add_filter( 'wp_is_application_passwords_available', '__return_false' );
		}
	}

	/**
	 * Security headers on PHP responses.
	 */
	public static function headers(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), usb=()' );
		header( 'Cross-Origin-Opener-Policy: same-origin-allow-popups' );
		if ( Settings::get( 'hsts' ) && is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}
	}

	/**
	 * Guests asking for ?author=N (which reveals the username) go to the home page.
	 *
	 * @param \WP $wp Request.
	 */
	public static function author_query( $wp ): void {
		if ( ! is_admin() && ! is_user_logged_in() && ( isset( $_GET['author'] ) || isset( $wp->query_vars['author'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * The REST users list is for signed-in users only.
	 *
	 * @param mixed            $response Response so far.
	 * @param array<mixed>     $handler  Route handler.
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function rest_users( $response, $handler, $request ) {
		if ( 0 === strpos( $request->get_route(), '/wp/v2/users' ) && ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_user_cannot_view', __( 'Sorry, you are not allowed to list users.', 'itour-shield' ), array( 'status' => 401 ) );
		}
		return $response;
	}

	/**
	 * Replace ?ver=<WordPress version> with a hash, so caching still works but the version is hidden.
	 *
	 * @param string $src URL.
	 */
	public static function strip_version( $src ): string {
		$v = get_bloginfo( 'version' );
		return $src ? str_replace( 'ver=' . $v, 'ver=' . substr( md5( $v . wp_salt( 'nonce' ) ), 0, 8 ), (string) $src ) : '';
	}

	/**
	 * Site checks shown on the overview, with what to do about each.
	 *
	 * @return array<int,array{label:string,ok:bool,level:string,note:string,tab:string}>
	 */
	public static function checks(): array {
		global $wpdb;
		$s       = Settings::all();
		$plugins = get_plugins();
		$active  = (array) get_option( 'active_plugins', array() );
		$updates = get_site_transient( 'update_plugins' );
		$core    = get_site_transient( 'update_core' );
		$need_wp = is_object( $core ) && ! empty( $core->updates ) && 'upgrade' === ( $core->updates[0]->response ?? '' );
		$need_pl = is_object( $updates ) ? count( (array) ( $updates->response ?? array() ) ) : 0;
		$salts   = defined( 'AUTH_KEY' ) && strlen( (string) AUTH_KEY ) >= 32 && false === strpos( (string) AUTH_KEY, 'put your unique phrase' );
		$role    = (string) get_option( 'default_role' );
		$c       = array(
			array( __( 'No user is called “admin”', 'itour-shield' ), ! username_exists( 'admin' ), 'warn', __( '“admin” is the first username attackers try. Create a new administrator and delete this one.', 'itour-shield' ), '' ),
			array( __( 'Secret keys (salts) are set', 'itour-shield' ), $salts, 'bad', __( 'Add unique keys to wp-config.php from api.wordpress.org/secret-key/1.1/salt/.', 'itour-shield' ), '' ),
			array( __( 'Site uses HTTPS', 'itour-shield' ), is_ssl() || 0 === strpos( home_url(), 'https://' ), 'bad', __( 'Install an SSL certificate and change the site address to https://.', 'itour-shield' ), '' ),
			array( __( 'Errors are not shown to visitors', 'itour-shield' ), ! ( WP_DEBUG && WP_DEBUG_DISPLAY ) || 'local' === wp_get_environment_type(), 'bad', __( 'Set WP_DEBUG_DISPLAY to false in wp-config.php on live sites.', 'itour-shield' ), '' ),
			array( __( 'debug.log is not in a public folder', 'itour-shield' ), ! is_file( WP_CONTENT_DIR . '/debug.log' ) || $s['protect_files'], 'warn', __( 'Turn on “Block sensitive files” or move the log outside the web root.', 'itour-shield' ), 'hardening' ),
			array( __( 'WordPress is up to date', 'itour-shield' ), ! $need_wp, 'bad', __( 'Update WordPress from Dashboard → Updates.', 'itour-shield' ), '' ),
			/* translators: %d: plugins with updates. */
			array( __( 'Plugins are up to date', 'itour-shield' ), 0 === $need_pl, 'bad', sprintf( _n( '%d plugin has an update waiting.', '%d plugins have updates waiting.', $need_pl, 'itour-shield' ), $need_pl ), '' ),
			/* translators: %d: inactive plugins. */
			array( __( 'No unused plugins installed', 'itour-shield' ), count( $plugins ) <= count( $active ), 'warn', sprintf( __( '%d inactive plugins. Inactive plugin code can still be attacked: delete what you don’t use.', 'itour-shield' ), max( 0, count( $plugins ) - count( $active ) ) ), '' ),
			array( __( 'New users don’t get extra powers', 'itour-shield' ), ! get_option( 'users_can_register' ) || in_array( $role, array( 'subscriber', 'customer' ), true ), 'bad', __( 'Anyone can register and gets a role above Subscriber. Change it in Settings → General.', 'itour-shield' ), '' ),
			array( __( 'PHP version is supported', 'itour-shield' ), version_compare( PHP_VERSION, '8.2', '>=' ), 'warn', __( 'Ask your host for PHP 8.2 or newer; older versions no longer get security fixes.', 'itour-shield' ), '' ),
			array( __( 'Database prefix is not the default', 'itour-shield' ), 'wp_' !== $wpdb->prefix, 'info', __( 'The default wp_ prefix makes some automated SQL attacks easier. It is safest to change it only on a fresh install.', 'itour-shield' ), '' ),
			array( __( 'wp-config.php is not writable by everyone', 'itour-shield' ), ! is_file( ABSPATH . 'wp-config.php' ) || 0 === ( fileperms( ABSPATH . 'wp-config.php' ) & 0002 ), 'bad', __( 'Set the file permissions of wp-config.php to 640 or 600.', 'itour-shield' ), '' ),
			array( __( 'Firewall loads before other plugins', 'itour-shield' ), ! $s['firewall'] || Firewall::loaded(), 'warn', __( 'The mu-plugins folder is not writable, so the firewall can’t load early.', 'itour-shield' ), 'firewall' ),
			array( __( 'Two-factor login is on for you', 'itour-shield' ), TwoFactor::enabled( get_current_user_id() ), 'warn', __( 'Turn it on in your profile. A stolen password is then not enough.', 'itour-shield' ), 'profile' ),
			array( __( 'Login page has a private address', 'itour-shield' ), '' !== $s['login_slug'], 'info', __( 'Bots hammer wp-login.php all day. Give the login page its own address.', 'itour-shield' ), 'paths' ),
			array( __( 'XML-RPC is off', 'itour-shield' ), (bool) $s['no_xmlrpc'], 'warn', __( 'XML-RPC allows many password guesses per request.', 'itour-shield' ), 'hardening' ),
		);
		return array_map(
			static fn( $r ) => array(
				'label' => $r[0],
				'ok'    => (bool) $r[1],
				'level' => $r[2],
				'note'  => $r[3],
				'tab'   => $r[4],
			),
			$c
		);
	}
}
