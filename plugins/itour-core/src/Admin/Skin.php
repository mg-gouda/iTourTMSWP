<?php
/**
 * iTourTMS look for the whole of wp-admin and the login page (on by default; iTour → Company to switch off).
 * Lives in the plugin, not the theme: restyling wp-admin is plugin territory.
 *
 * @package ITour\Core
 */

namespace ITour\Core\Admin;

use ITour\Core\Company;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the admin and login skins.
 */
final class Skin {

	/**
	 * Hook everything.
	 */
	public function hooks(): void {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'admin' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'admin_bar' ) );
		add_filter( 'admin_body_class', static fn( string $c ): string => $c . ' itour-skin' );
		add_action( 'login_enqueue_scripts', array( $this, 'login' ) );
		add_filter( 'login_headerurl', static fn(): string => home_url( '/' ) );
		add_filter( 'login_headertext', static fn(): string => get_bloginfo( 'name' ) );
		add_filter( 'login_body_class', static fn( array $c ): array => array_merge( $c, array( 'itour-login' ) ) );
	}

	/**
	 * Whether the skin is switched on.
	 */
	public static function enabled(): bool {
		return ! empty( Company::get()['admin_skin'] );
	}

	/**
	 * Stylesheet URL with a cache-busting version.
	 *
	 * @param string $file File in assets/.
	 * @return array{0:string,1:string}
	 */
	private function asset( string $file ): array {
		$path = dirname( ITOUR_CORE_FILE ) . '/assets/' . $file;
		return array( plugins_url( 'assets/' . $file, ITOUR_CORE_FILE ), (string) ( file_exists( $path ) ? filemtime( $path ) : ITOUR_CORE_VERSION ) );
	}

	/**
	 * All admin screens.
	 */
	public function admin(): void {
		list( $url, $ver ) = $this->asset( 'admin-skin.css' );
		wp_enqueue_style( 'itour-admin-skin', $url, array(), $ver );
		list( $url, $ver ) = $this->asset( 'admin-bar.css' );
		wp_enqueue_style( 'itour-admin-bar', $url, array( 'admin-bar' ), $ver );
	}

	/**
	 * The admin bar on the front end.
	 */
	public function admin_bar(): void {
		if ( is_admin_bar_showing() ) {
			list( $url, $ver ) = $this->asset( 'admin-bar.css' );
			wp_enqueue_style( 'itour-admin-bar', $url, array( 'admin-bar' ), $ver );
		}
	}

	/**
	 * The login, lost-password and register screens.
	 */
	public function login(): void {
		list( $url, $ver ) = $this->asset( 'login.css' );
		wp_enqueue_style( 'itour-login', $url, array( 'login' ), $ver );
		$logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' ) : '';
		if ( $logo ) {
			wp_add_inline_style( 'itour-login', '.itour-login #login h1 a{background-image:url(' . esc_url( $logo ) . ');text-indent:-9999px;width:200px;height:56px;background-size:contain;background-position:center;}' );
		}
	}
}
