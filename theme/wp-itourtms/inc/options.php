<?php
/**
 * Theme Options: definitions, defaults, reading, and applying them to theme.json and templates.
 * The theme's own settings live in one option, `wp_itourtms_options` (WordPress.org requirement).
 *
 * Plugins (the iTour modules) add their own vertical tabs with the `wp_itourtms_option_sections`
 * filter. Each such section names its own `store` option, so its settings stay with the plugin:
 *
 *   add_filter( 'wp_itourtms_option_sections', function ( $sections ) {
 *       $sections['crm'] = array(
 *           'title'       => 'Tours & Excursions',
 *           'description' => 'Pages the module uses.',
 *           'store'       => 'itour_crm_pages',
 *           'fields'      => array(
 *               'listing_page' => array( 'label' => 'Tours listing page', 'type' => 'page', 'default' => 0 ),
 *           ),
 *       );
 *       return $sections;
 *   } );
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

const WP_ITOURTMS_STORE = 'wp_itourtms_options';

/**
 * All sections shown in the Options tab: the theme's plus any added by plugins.
 * Field types: color, px, number, checkbox, text, select, font, page.
 *
 * @return array<string,array{title:string,description?:string,store:string,fields:array<string,array<string,mixed>>}>
 */
function wp_itourtms_option_fields(): array {
	$sections = (array) apply_filters( 'wp_itourtms_option_sections', wp_itourtms_theme_option_fields() );
	foreach ( $sections as $id => $s ) {
		$sections[ $id ]['store'] = sanitize_key( $s['store'] ?? WP_ITOURTMS_STORE );
	}
	return $sections;
}

/**
 * The theme's own option sections (stored in wp_itourtms_options).
 *
 * @return array<string,array{title:string,description?:string,fields:array<string,array<string,mixed>>}>
 */
function wp_itourtms_theme_option_fields(): array {
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
		'fonts'  => array(
			'title'       => __( 'Fonts', 'wp-itourtms' ),
			'description' => __( 'Choose from every Google Font, or keep the fast system font. Google Fonts load from Google’s servers.', 'wp-itourtms' ),
			'fields'      => array(
				'font_body'      => array(
					'label'   => __( 'Body font', 'wp-itourtms' ),
					'type'    => 'font',
					'default' => '',
					'help'    => __( 'Paragraphs, menus, buttons and forms.', 'wp-itourtms' ),
				),
				'font_heading'   => array(
					'label'   => __( 'Heading font', 'wp-itourtms' ),
					'type'    => 'font',
					'default' => '',
					'help'    => __( 'Titles and headings. Leave on “Same as body” for one family.', 'wp-itourtms' ),
				),
				'font_size'      => array(
					'label'   => __( 'Base text size', 'wp-itourtms' ),
					'type'    => 'px',
					'default' => 16,
					'min'     => 14,
					'max'     => 20,
				),
				'heading_weight' => array(
					'label'   => __( 'Heading weight', 'wp-itourtms' ),
					'type'    => 'select',
					'default' => '700',
					'options' => array(
						'400' => __( 'Regular (400)', 'wp-itourtms' ),
						'500' => __( 'Medium (500)', 'wp-itourtms' ),
						'600' => __( 'Semibold (600)', 'wp-itourtms' ),
						'700' => __( 'Bold (700)', 'wp-itourtms' ),
						'800' => __( 'Extra bold (800)', 'wp-itourtms' ),
						'900' => __( 'Black (900)', 'wp-itourtms' ),
					),
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
 * Fields kept in one storage option.
 *
 * @param string $store Option name.
 * @return array<string,array<string,mixed>>
 */
function wp_itourtms_store_fields( string $store = WP_ITOURTMS_STORE ): array {
	$fields = array();
	foreach ( WP_ITOURTMS_STORE === $store ? wp_itourtms_theme_option_fields() : wp_itourtms_option_fields() as $section ) {
		if ( ( $section['store'] ?? WP_ITOURTMS_STORE ) === $store ) {
			$fields += $section['fields'];
		}
	}
	return $fields;
}

/**
 * Default values for one storage option.
 *
 * @param string $store Option name.
 * @return array<string,mixed>
 */
function wp_itourtms_option_defaults( string $store = WP_ITOURTMS_STORE ): array {
	return array_map( static fn( $f ) => $f['default'], wp_itourtms_store_fields( $store ) );
}

/**
 * Saved values with defaults filled in (the theme's options unless another store is named).
 *
 * @param string $store Option name.
 * @return array<string,mixed>
 */
function wp_itourtms_options( string $store = WP_ITOURTMS_STORE ): array {
	return array_merge( wp_itourtms_option_defaults( $store ), (array) get_option( $store, array() ) );
}

/**
 * Sanitize one value for its field type.
 *
 * @param array<string,mixed> $f Field.
 * @param mixed               $v Submitted value.
 * @return mixed
 */
function wp_itourtms_sanitize_value( array $f, $v ) {
	switch ( $f['type'] ) {
		case 'color':
			$hex = (string) sanitize_hex_color( (string) $v );
			return '' !== $hex ? $hex : $f['default'];
		case 'checkbox':
			return empty( $v ) ? 0 : 1;
		case 'px':
		case 'number':
			return max( (int) $f['min'], min( (int) $f['max'], null === $v ? (int) $f['default'] : (int) $v ) );
		case 'select':
			return array_key_exists( (string) $v, (array) $f['options'] ) ? (string) $v : $f['default'];
		case 'page':
			$id = absint( $v );
			return $id && 'page' === get_post_type( $id ) ? $id : 0;
		case 'font':
			$v = sanitize_text_field( (string) $v );
			return '' === $v || isset( wp_itourtms_google_fonts()[ $v ] ) ? $v : $f['default'];
		default:
			return wp_kses(
				(string) $v,
				array(
					'a'      => array( 'href' => array() ),
					'strong' => array(),
					'em'     => array(),
				)
			);
	}
}

/**
 * Sanitize submitted values for one storage option.
 *
 * @param mixed  $input Submitted values.
 * @param string $store Option name.
 * @return array<string,mixed>
 */
function wp_itourtms_sanitize_options( $input, string $store = WP_ITOURTMS_STORE ): array {
	$input = is_array( $input ) ? $input : array();
	$out   = array();
	foreach ( wp_itourtms_store_fields( $store ) as $key => $f ) {
		$out[ $key ] = wp_itourtms_sanitize_value( $f, $input[ $key ] ?? null );
	}
	return $out;
}
add_action(
	'init',
	static function (): void {
		register_setting(
			'wp_itourtms',
			WP_ITOURTMS_STORE,
			array(
				'type'              => 'array',
				'sanitize_callback' => static fn( $v ) => wp_itourtms_sanitize_options( $v ),
				'default'           => array(),
			)
		);
	}
);

/**
 * Bundled Google Fonts list: family => [ category, weights ] (built by tools/build-google-fonts.py).
 *
 * @return array<string,array{0:string,1:int[]}>
 */
function wp_itourtms_google_fonts(): array {
	static $fonts = null;
	if ( null === $fonts ) {
		$fonts = array();
		$json  = (string) file_get_contents( get_template_directory() . '/assets/data/google-fonts.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled file.
		foreach ( (array) json_decode( $json, true ) as $f ) {
			$fonts[ (string) $f[0] ] = array( (string) $f[1], array_map( 'intval', (array) $f[2] ) );
		}
	}
	return $fonts;
}

/**
 * CSS font stack for a chosen family ('' = system font).
 *
 * @param string $family Google font family or ''.
 */
function wp_itourtms_font_stack( string $family ): string {
	$system = 'system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif';
	if ( '' === $family ) {
		return $system;
	}
	$cat      = wp_itourtms_google_fonts()[ $family ][0] ?? 'sans-serif';
	$fallback = array(
		'serif'       => 'Georgia, "Times New Roman", serif',
		'monospace'   => 'ui-monospace, Menlo, Consolas, monospace',
		'handwriting' => 'cursive',
	)[ $cat ] ?? $system;
	return '"' . str_replace( '"', '', $family ) . '", ' . $fallback;
}

/**
 * Google Fonts stylesheet URL for the chosen families ('' when only the system font is used).
 */
function wp_itourtms_google_fonts_url(): string {
	$o        = wp_itourtms_options();
	$families = array();
	foreach ( array_unique( array_filter( array( (string) $o['font_body'], (string) $o['font_heading'] ) ) ) as $family ) {
		$available = wp_itourtms_google_fonts()[ $family ][1] ?? array( 400 );
		$wanted    = array_values( array_intersect( $available, array( 400, 500, 600, 700, (int) $o['heading_weight'] ) ) );
		$wanted    = $wanted ? $wanted : array( $available[0] );
		sort( $wanted );
		$families[] = 'family=' . str_replace( '%20', '+', rawurlencode( $family ) ) . ( count( $available ) > 1 ? ':wght@' . implode( ';', array_unique( $wanted ) ) : '' );
	}
	return $families ? 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap' : '';
}

/**
 * Load the chosen Google Fonts on the site and in the block editor.
 */
function wp_itourtms_enqueue_fonts(): void {
	$url = wp_itourtms_google_fonts_url();
	if ( '' !== $url ) {
		wp_enqueue_style( 'wp-itourtms-fonts', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL is versioned by its query.
	}
}
add_action( 'wp_enqueue_scripts', 'wp_itourtms_enqueue_fonts' );
add_action( 'enqueue_block_editor_assets', 'wp_itourtms_enqueue_fonts' );
add_action(
	'after_setup_theme',
	static function (): void {
		$url = wp_itourtms_google_fonts_url();
		if ( '' !== $url ) {
			add_editor_style( $url );
		}
	},
	20
);

/**
 * WCAG contrast ratio of two hex colours.
 *
 * @param string $a Hex colour.
 * @param string $b Hex colour.
 */
function wp_itourtms_contrast( string $a, string $b ): float {
	$lum = static function ( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$c = array_map(
			static function ( string $h ): float {
				$v = hexdec( $h ) / 255;
				return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
			},
			str_split( $hex, 2 )
		);
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	};
	$l   = array( $lum( $a ), $lum( $b ) );
	return ( max( $l ) + 0.05 ) / ( min( $l ) + 0.05 );
}

/**
 * Readability warnings for colour pairs below WCAG AA (4.5:1).
 *
 * @param array<string,mixed> $o Options.
 * @return string[]
 */
function wp_itourtms_contrast_warnings( array $o ): array {
	$pairs = array(
		array( 'color_ink', 'color_base', __( 'Text on the page background', 'wp-itourtms' ) ),
		array( 'color_ink', 'color_brand', __( 'Text on brand buttons', 'wp-itourtms' ) ),
		array( 'color_base', 'color_brand_dark', __( 'White text on the hover colour', 'wp-itourtms' ) ),
	);
	$out   = array();
	foreach ( $pairs as $p ) {
		$ratio = wp_itourtms_contrast( (string) $o[ $p[0] ], (string) $o[ $p[1] ] );
		if ( $ratio < 4.5 ) {
			/* translators: 1: colour pair, 2: contrast ratio. */
			$out[] = sprintf( __( '%1$s is hard to read (%2$s:1). Aim for at least 4.5:1.', 'wp-itourtms' ), $p[2], number_format_i18n( $ratio, 1 ) );
		}
	}
	return $out;
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
				'color'      => array( 'palette' => $palette ),
				'layout'     => array(
					'contentSize' => (int) $o['content_width'] . 'px',
					'wideSize'    => (int) $o['wide_width'] . 'px',
				),
				'typography' => array(
					'fontFamilies' => array(
						array(
							'slug'       => 'system',
							'name'       => '' !== $o['font_body'] ? (string) $o['font_body'] : __( 'System', 'wp-itourtms' ),
							'fontFamily' => wp_itourtms_font_stack( (string) $o['font_body'] ),
						),
						array(
							'slug'       => 'heading',
							'name'       => __( 'Headings', 'wp-itourtms' ),
							'fontFamily' => wp_itourtms_font_stack( '' !== $o['font_heading'] ? (string) $o['font_heading'] : (string) $o['font_body'] ),
						),
					),
				),
			),
			'styles'   => array(
				'typography' => array( 'fontSize' => (int) $o['font_size'] . 'px' ),
				'elements'   => array(
					'button'  => array( 'border' => array( 'radius' => (int) $o['radius'] . 'px' ) ),
					'heading' => array(
						'typography' => array(
							'fontFamily' => 'var(--wp--preset--font-family--heading)',
							'fontWeight' => (string) $o['heading_weight'],
						),
					),
				),
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
