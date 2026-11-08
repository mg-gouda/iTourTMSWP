<?php
/**
 * Hidden login, REST prefix and masked folder names.
 *
 * - Login: wp-login.php answers at /{slug}/ only; wp-login.php and wp-admin show "not found" to guests.
 * - REST: /wp-json/ becomes /{prefix}/.
 * - Folders: public URLs say /assets/, /lib/, /modules/, /skins/, /media/ instead of wp-content,
 *   wp-includes, plugins, themes and uploads. Files stay where they are: the web server maps the
 *   new names back (rules in .htaccess) and page HTML is rewritten on the way out.
 *   This hides fingerprints from scanners; the firewall is what actually stops attacks.
 *
 * Locked out? Add define( 'ITOUR_SHIELD_DISABLE_PATHS', true ); to wp-config.php.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * URL changes.
 */
final class Paths {

	/**
	 * Hooks.
	 */
	public static function hooks(): void {
		if ( defined( 'ITOUR_SHIELD_DISABLE_PATHS' ) && ITOUR_SHIELD_DISABLE_PATHS ) {
			return;
		}
		$s = Settings::all();
		if ( '' !== $s['rest_prefix'] ) {
			add_filter( 'rest_url_prefix', static fn() => (string) $s['rest_prefix'] );
		}
		if ( '' !== $s['login_slug'] ) {
			self::route( (string) $s['login_slug'] );
			foreach ( array( 'site_url', 'network_site_url', 'wp_redirect', 'login_url', 'logout_url', 'lostpassword_url', 'register_url' ) as $hook ) {
				add_filter( $hook, array( self::class, 'login_link' ), 99 );
			}
		}
		if ( $s['mask_paths'] && self::can_mask() ) {
			add_action( 'template_redirect', array( self::class, 'start_mask' ), -999 );
		}
	}

	/**
	 * Serve the login page at the slug; hide wp-login.php and wp-admin from guests.
	 *
	 * @param string $slug Login slug.
	 */
	private static function route( string $slug ): void {
		$path  = strtolower( untrailingslashit( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/', PHP_URL_PATH ) ) );
		$login = untrailingslashit( (string) wp_parse_url( site_url( $slug ), PHP_URL_PATH ) );
		$core  = untrailingslashit( (string) wp_parse_url( site_url( 'wp-login.php' ), PHP_URL_PATH ) );
		if ( $path === $login ) {
			$GLOBALS['pagenow'] = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			add_action(
				'wp_loaded',
				static function () {
					global $error, $interim_login, $action, $user_login; // wp-login.php expects these in global scope.
					require_once ABSPATH . 'wp-login.php';
					exit;
				}
			);
			return;
		}
		$admin_guest = is_admin() && ! wp_doing_ajax() && ! is_user_logged_in()
			&& 'admin-post.php' !== basename( isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '' );
		if ( $path === $core || $admin_guest ) {
			if ( $path === $core && is_user_logged_in() ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
			add_action(
				'wp_loaded',
				static function () {
					wp_safe_redirect( home_url( '404/' ) ); // Let the theme show its normal "not found" page.
					exit;
				}
			);
		}
	}

	/**
	 * Point links at the login slug.
	 *
	 * @param string $url URL.
	 */
	public static function login_link( $url ): string {
		$url = (string) $url;
		if ( false === strpos( $url, 'wp-login.php' ) ) {
			return $url;
		}
		$slug = (string) Settings::get( 'login_slug' );
		return (string) preg_replace( '#wp-login\.php(?=$|\?|\#)#', $slug . '/', $url );
	}

	/**
	 * Buffer the page so asset links can be rewritten.
	 */
	public static function start_mask(): void {
		ob_start( array( self::class, 'mask_html' ) );
	}

	/**
	 * Folder masks: real URL => public URL, longest first.
	 *
	 * @return array<string,string>
	 */
	public static function masks(): array {
		$s    = Settings::all();
		$home = trailingslashit( home_url() );
		return array(
			content_url( 'plugins/' ) => $home . $s['mask_plugins'] . '/',
			content_url( 'themes/' )  => $home . $s['mask_themes'] . '/',
			content_url( 'uploads/' ) => $home . $s['mask_uploads'] . '/',
			content_url( '/' )        => $home . $s['mask_content'] . '/',
			includes_url()            => $home . $s['mask_includes'] . '/',
		);
	}

	/**
	 * Masking needs Apache rewrites and the standard layout (WordPress in the site root, wp-content inside it).
	 */
	public static function can_mask(): bool {
		global $is_apache;
		return $is_apache && home_url() === site_url() && 0 === strpos( content_url(), home_url() ) && WP_CONTENT_DIR === untrailingslashit( ABSPATH ) . '/wp-content';
	}

	/**
	 * Rewrite asset URLs in page HTML (plain and JSON-escaped).
	 *
	 * @param string $html Page.
	 */
	public static function mask_html( string $html ): string {
		$from = array();
		$to   = array();
		foreach ( self::masks() as $real => $public ) {
			array_push( $from, $real, str_replace( '/', '\/', $real ) );
			array_push( $to, $public, str_replace( '/', '\/', $public ) );
		}
		return str_replace( $from, $to, $html );
	}

	/**
	 * .htaccess lines that map the public names back to the real folders (static files only, never PHP).
	 *
	 * @return array<int,string>
	 */
	public static function rewrite_rules(): array {
		$s = Settings::all();
		if ( ! $s['mask_paths'] || ! self::can_mask() ) {
			return array();
		}
		$file  = '(.+\.(?!php\d?$|phtml$|phar$)[A-Za-z0-9]+)$';
		$lines = array( 'RewriteEngine On', 'RewriteBase ' . trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ) );
		foreach (
			array(
				$s['mask_plugins']  => 'wp-content/plugins/',
				$s['mask_themes']   => 'wp-content/themes/',
				$s['mask_uploads']  => 'wp-content/uploads/',
				$s['mask_content']  => 'wp-content/',
				$s['mask_includes'] => 'wp-includes/',
			) as $public => $real
		) {
			$lines[] = 'RewriteRule ^' . preg_quote( (string) $public, '#' ) . '/' . $file . ' ' . $real . '$1 [L]';
		}
		return $lines;
	}
}
