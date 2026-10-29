<?php
/**
 * Appearance → WP iTourTMS: getting started, user manual and documentation (core admin UI only).
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the page under Appearance.
 */
function wp_itourtms_admin_menu(): void {
	$hook = add_theme_page(
		__( 'WP iTourTMS', 'wp-itourtms' ),
		__( 'WP iTourTMS', 'wp-itourtms' ),
		'edit_theme_options',
		'wp-itourtms',
		'wp_itourtms_admin_render'
	);
	if ( $hook ) {
		add_action(
			'admin_enqueue_scripts',
			static function ( string $hook_suffix ) use ( $hook ): void {
				if ( $hook_suffix === $hook ) {
					wp_enqueue_style( 'wp-itourtms-admin', get_template_directory_uri() . '/assets/css/admin.css', array(), WP_ITOURTMS_VERSION );
				}
			}
		);
	}
}
add_action( 'admin_menu', 'wp_itourtms_admin_menu' );

/**
 * Render the page.
 */
function wp_itourtms_admin_render(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$manual = wp_itourtms_manual();
	$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'start'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
	if ( ! isset( $manual[ $tab ] ) ) {
		$tab = (string) array_key_first( $manual );
	}
	$theme = wp_get_theme();
	echo '<div class="wrap wp-itourtms-admin">';
	echo '<h1>' . esc_html__( 'WP iTourTMS', 'wp-itourtms' ) . ' <span class="wp-itourtms-version">' . esc_html( $theme->get( 'Version' ) ) . '</span></h1>';
	echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Help sections', 'wp-itourtms' ) . '">';
	foreach ( $manual as $key => $part ) {
		$url = add_query_arg(
			array(
				'page' => 'wp-itourtms',
				'tab'  => $key,
			),
			admin_url( 'themes.php' )
		);
		echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $key === $tab ? ' nav-tab-active" aria-current="page' : '' ) . '">' . esc_html( $part['title'] ) . '</a>';
	}
	echo '</nav><div class="wp-itourtms-layout"><nav class="wp-itourtms-toc" aria-label="' . esc_attr__( 'On this page', 'wp-itourtms' ) . '"><ul>';
	foreach ( $manual[ $tab ]['sections'] as $s ) {
		echo '<li><a href="#' . esc_attr( $s['id'] ) . '">' . esc_html( $s['title'] ) . '</a></li>';
	}
	echo '</ul></nav><div class="wp-itourtms-body">';
	echo wp_itourtms_manual_sections_html( $manual[ $tab ]['sections'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
	echo '</div></div></div>';
}
