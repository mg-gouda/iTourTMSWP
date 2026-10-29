<?php
/**
 * iTour theme setup.
 *
 * @package ITour\Theme
 */

defined( 'ABSPATH' ) || exit;

define( 'ITOUR_THEME_VERSION', '0.1.0' );

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'itour', get_template_directory() . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support(
			'custom-logo',
			array(
				'height'     => 48,
				'flex-width' => true,
			)
		);
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'responsive-embeds' );
		register_nav_menus(
			array(
				'primary' => __( 'Main menu', 'itour' ),
				'footer'  => __( 'Footer menu', 'itour' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$uri = get_template_directory_uri() . '/assets';
		wp_enqueue_style( 'itour', $uri . '/css/main.css', array(), ITOUR_THEME_VERSION );
		wp_enqueue_script( 'itour', $uri . '/js/main.js', array(), ITOUR_THEME_VERSION, array( 'strategy' => 'defer' ) );
	}
);

// Front-end clutter WordPress adds by default and a travel site doesn't need.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
