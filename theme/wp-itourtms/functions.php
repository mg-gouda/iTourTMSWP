<?php
/**
 * WP iTourTMS theme setup.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_ITOURTMS_VERSION', '0.1.0' );

if ( ! isset( $content_width ) ) {
	$content_width = 760; // Matches layout.contentSize in theme.json.
}

/**
 * Theme supports, menus and editor styles.
 */
function wp_itourtms_setup(): void {
	load_theme_textdomain( 'wp-itourtms', get_template_directory() . '/languages' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/main.css' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 48,
			'width'       => 200,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
	add_theme_support( 'custom-background', array( 'default-color' => 'ffffff' ) );
	add_theme_support( 'html5', array( 'comment-form', 'comment-list', 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	register_nav_menus(
		array(
			'primary' => __( 'Main menu', 'wp-itourtms' ),
			'footer'  => __( 'Footer menu', 'wp-itourtms' ),
		)
	);
}
add_action( 'after_setup_theme', 'wp_itourtms_setup' );

/**
 * Block styles (patterns are registered automatically from /patterns).
 */
function wp_itourtms_block_styles(): void {
	register_block_pattern_category( 'wp-itourtms', array( 'label' => __( 'WP iTourTMS', 'wp-itourtms' ) ) );
	register_block_style(
		'core/image',
		array(
			'name'         => 'wp-itourtms-rounded',
			'label'        => __( 'Rounded', 'wp-itourtms' ),
			'inline_style' => '.wp-block-image.is-style-wp-itourtms-rounded img{border-radius:14px}',
		)
	);
}
add_action( 'init', 'wp_itourtms_block_styles' );

/**
 * Front-end styles and scripts.
 */
function wp_itourtms_assets(): void {
	$uri = get_template_directory_uri() . '/assets';
	// While developing the theme, bust the browser cache on every change.
	$ver = 'theme' === wp_get_development_mode() ? (string) filemtime( get_template_directory() . '/assets/css/main.css' ) : WP_ITOURTMS_VERSION;
	wp_enqueue_style( 'wp-itourtms', $uri . '/css/main.css', array(), $ver );
	wp_enqueue_script( 'wp-itourtms', $uri . '/js/main.js', array(), $ver, array( 'strategy' => 'defer' ) );
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'wp_itourtms_assets' );

if ( is_admin() ) {
	require get_template_directory() . '/inc/manual.php';
	require get_template_directory() . '/inc/admin-page.php';
}
