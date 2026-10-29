<?php
/**
 * Theme Options: definitions, defaults, reading, and applying them to theme.json and templates.
 * Everything is stored in one option, `wp_itourtms_options` (WordPress.org requirement).
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Option fields grouped by section: section => [ 'title' => …, 'fields' => [ key => field ] ].
 * Field types: color, px, number, checkbox, text.
 *
 * @return array<string,array{title:string,fields:array<string,array<string,mixed>>}>
 */
function wp_itourtms_option_fields(): array {
	return array(
		'colors' => array(
			'title'  => __( 'Colours', 'wp-itourtms' ),
			'fields' => array(
				'color_brand'      => array(
					'label'   => __( 'Brand', 'wp-itourtms' ),
					'type'    => 'color',
					'default' => '#e8851a',
					'help'    => __( 'Buttons, highlights and call-to-action bands.', 'wp-itourtms' ),
				),
				'color_brand_dark' => array(
					'label'   => __( 'Brand (hover)', 'wp-itourtms' ),
					'type'    => 'color',
					'default' => '#9a530a',
					'help'    => __( 'Buttons and links on hover, focus outlines. White text sits on it, so keep it dark.', 'wp-itourtms' ),
				),
				'color_ink'        => array(
					'label'   => __( 'Text', 'wp-itourtms' ),
					'type'    => 'color',
					'default' => '#1a2b48',
					'help'    => __( 'Body text, headings and the footer background.', 'wp-itourtms' ),
				),
				'color_muted'      => array(
					'label'   => __( 'Secondary text', 'wp-itourtms' ),
					'type'    => 'color',
					'default' => '#4f5d6b',
				),
				'color_surface'    => array(
					'label'   => __( 'Soft background', 'wp-itourtms' ),
					'type'    => 'color',
					'default' => '#f4f6f9',
				),
				'color_base'       => array(
					'label'   => __( 'Page background', 'wp-itourtms' ),
					'type'    => 'color',
					'default' => '#ffffff',
				),
			),
		),
		'layout' => array(
			'title'  => __( 'Layout', 'wp-itourtms' ),
			'fields' => array(
				'content_width' => array(
					'label'   => __( 'Text column width', 'wp-itourtms' ),
					'type'    => 'px',
					'default' => 760,
					'min'     => 560,
					'max'     => 960,
					'help'    => __( 'Width of paragraphs and normal blocks. 680–800 px reads best.', 'wp-itourtms' ),
				),
				'wide_width'    => array(
					'label'   => __( 'Wide width', 'wp-itourtms' ),
					'type'    => 'px',
					'default' => 1200,
					'min'     => 960,
					'max'     => 1600,
					'help'    => __( 'Width of the header, footer and “Wide width” blocks.', 'wp-itourtms' ),
				),
				'radius'        => array(
					'label'   => __( 'Corner rounding', 'wp-itourtms' ),
					'type'    => 'px',
					'default' => 10,
					'min'     => 0,
					'max'     => 32,
					'help'    => __( 'Buttons, images in lists and menus. 0 gives square corners.', 'wp-itourtms' ),
				),
			),
		),
		'header' => array(
			'title'  => __( 'Header', 'wp-itourtms' ),
			'fields' => array(
				'header_sticky'     => array(
					'label'   => __( 'Sticky header', 'wp-itourtms' ),
					'type'    => 'checkbox',
					'default' => 0,
					'text'    => __( 'Keep the header at the top of the screen while scrolling', 'wp-itourtms' ),
				),
				'header_show_title' => array(
					'label'   => __( 'Site title with logo', 'wp-itourtms' ),
					'type'    => 'checkbox',
					'default' => 0,
					'text'    => __( 'Show the site title next to the logo', 'wp-itourtms' ),
				),
			),
		),
		'footer' => array(
			'title'  => __( 'Footer', 'wp-itourtms' ),
			'fields' => array(
				'footer_text' => array(
					'label'   => __( 'Footer text', 'wp-itourtms' ),
					'type'    => 'text',
					'default' => '© {year} {site}',
					'help'    => __( '{year} becomes the current year and {site} the site title. Basic links and bold are allowed.', 'wp-itourtms' ),
				),
			),
		),
		'blog'   => array(
			'title'  => __( 'Blog', 'wp-itourtms' ),
			'fields' => array(
				'blog_show_date'     => array(
					'label'   => __( 'Post dates', 'wp-itourtms' ),
					'type'    => 'checkbox',
					'default' => 1,
					'text'    => __( 'Show the date on posts and in lists', 'wp-itourtms' ),
				),
				'blog_single_image'  => array(
					'label'   => __( 'Featured image on posts', 'wp-itourtms' ),
					'type'    => 'checkbox',
					'default' => 1,
					'text'    => __( 'Show the featured image at the top of each post', 'wp-itourtms' ),
				),
				'blog_excerpt_words' => array(
					'label'   => __( 'Summary length', 'wp-itourtms' ),
					'type'    => 'number',
					'default' => 30,
					'min'     => 10,
					'max'     => 80,
					'help'    => __( 'Words shown for each post in lists when the post has no manual excerpt.', 'wp-itourtms' ),
				),
			),
		),
	);
}

/**
 * All options with defaults filled in.
 *
 * @return array<string,mixed>
 */
function wp_itourtms_options(): array {
	static $cache = null;
	if ( null === $cache ) {
		$defaults = array();
		foreach ( wp_itourtms_option_fields() as $section ) {
			foreach ( $section['fields'] as $key => $f ) {
				$defaults[ $key ] = $f['default'];
			}
		}
		$cache = array_merge( $defaults, (array) get_option( 'wp_itourtms_options', array() ) );
	}
	return $cache;
}

/**
 * One option value.
 *
 * @param string $key Option key.
 * @return mixed
 */
function wp_itourtms_option( string $key ) {
	return wp_itourtms_options()[ $key ] ?? null;
}

/**
 * Feed the options into theme.json so the editor and the site use the same values.
 * Global Styles (Appearance → Design → Styles) still override these.
 *
 * @param WP_Theme_JSON_Data $data Theme layer.
 * @return WP_Theme_JSON_Data
 */
function wp_itourtms_theme_json( $data ) {
	$o       = wp_itourtms_options();
	$palette = array();
	$names   = array(
		'brand'      => __( 'Brand', 'wp-itourtms' ),
		'brand-dark' => __( 'Brand dark', 'wp-itourtms' ),
		'ink'        => __( 'Ink', 'wp-itourtms' ),
		'muted'      => __( 'Muted', 'wp-itourtms' ),
		'line'       => __( 'Line', 'wp-itourtms' ),
		'surface'    => __( 'Surface', 'wp-itourtms' ),
		'base'       => __( 'Base', 'wp-itourtms' ),
	);
	foreach ( $names as $slug => $name ) {
		$palette[] = array(
			'slug'  => $slug,
			'name'  => $name,
			'color' => 'line' === $slug ? '#e3e7ed' : $o[ 'color_' . str_replace( '-', '_', $slug ) ],
		);
	}
	return $data->update_with(
		array(
			'version'  => 3,
			'settings' => array(
				'color'  => array( 'palette' => $palette ),
				'layout' => array(
					'contentSize' => (int) $o['content_width'] . 'px',
					'wideSize'    => (int) $o['wide_width'] . 'px',
				),
			),
			'styles'   => array(
				'elements' => array( 'button' => array( 'border' => array( 'radius' => (int) $o['radius'] . 'px' ) ) ),
			),
		)
	);
}
add_filter( 'wp_theme_json_data_theme', 'wp_itourtms_theme_json' );

// Saved options change theme.json data, so drop WordPress's cached copy.
add_action( 'update_option_wp_itourtms_options', 'wp_clean_theme_json_cache' );
add_action( 'add_option_wp_itourtms_options', 'wp_clean_theme_json_cache' );

/**
 * CSS variables and body classes the templates use.
 */
function wp_itourtms_option_styles(): void {
	wp_add_inline_style( 'wp-itourtms', ':root{--wp-itourtms-radius:' . (int) wp_itourtms_option( 'radius' ) . 'px}' );
}
add_action( 'wp_enqueue_scripts', 'wp_itourtms_option_styles', 20 );

/**
 * Body classes for header options.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function wp_itourtms_body_classes( array $classes ): array {
	if ( wp_itourtms_option( 'header_sticky' ) ) {
		$classes[] = 'wp-itourtms-sticky-header';
	}
	return $classes;
}
add_filter( 'body_class', 'wp_itourtms_body_classes' );

/**
 * Excerpt length from the Blog options.
 */
function wp_itourtms_excerpt_length(): int {
	return (int) wp_itourtms_option( 'blog_excerpt_words' );
}
add_filter( 'excerpt_length', 'wp_itourtms_excerpt_length' );

/**
 * The footer text with {year} and {site} filled in (limited HTML).
 */
function wp_itourtms_footer_text(): string {
	$text = strtr(
		(string) wp_itourtms_option( 'footer_text' ),
		array(
			'{year}' => wp_date( 'Y' ),
			'{site}' => get_bloginfo( 'name' ),
		)
	);
	return wp_kses(
		$text,
		array(
			'a'      => array( 'href' => array() ),
			'strong' => array(),
			'em'     => array(),
		)
	);
}
