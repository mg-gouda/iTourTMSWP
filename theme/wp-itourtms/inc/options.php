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
 * Field types: color, px, number, checkbox, text, textarea, email, url, tel, select, font, page, menu,
 * heading (a sub-title, no value).
 * 'show_if' shows a field only when a condition holds: '<checkbox key>' (switch on), '<select key>=a|b'
 * (one of those values), or an array of these (all must hold).
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
		'colors'  => array(
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
		'fonts'   => array(
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
		'layout'  => array(
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
		'header'  => array(
			'title'  => __( 'Header', 'wp-itourtms' ),
			'fields' => array(
				'header_sticky'        => array(
					'label'   => __( 'Sticky header', 'wp-itourtms' ),
					'type'    => 'checkbox',
					'default' => 0,
					'text'    => __( 'Keep the header at the top of the screen while scrolling', 'wp-itourtms' ),
				),
				'header_show_title'    => array(
					'label'   => __( 'Site title with logo', 'wp-itourtms' ),
					'type'    => 'checkbox',
					'default' => 0,
					'text'    => __( 'Show the site title next to the logo', 'wp-itourtms' ),
				),
				'topbar_heading'       => array(
					'label' => __( 'Top bar', 'wp-itourtms' ),
					'type'  => 'heading',
					'help'  => __( 'A thin bar above the header for contact details, a short message and social links.', 'wp-itourtms' ),
				),
				'topbar_enabled'       => array(
					'label'   => __( 'Show top bar', 'wp-itourtms' ),
					'type'    => 'checkbox',
					'default' => 0,
					'text'    => __( 'Display the top bar above the header', 'wp-itourtms' ),
				),
				'topbar_text'          => array(
					'label'   => __( 'Message', 'wp-itourtms' ),
					'type'    => 'text',
					'default' => '',
					'show_if' => 'topbar_enabled',
					'help'    => __( 'Short line such as “Free cancellation up to 24 hours before most trips”. Basic links and bold are allowed.', 'wp-itourtms' ),
				),
				'topbar_show_contacts' => array(
					'label'   => __( 'Contact details', 'wp-itourtms' ),
					'type'    => 'checkbox',
					'default' => 1,
					'show_if' => 'topbar_enabled',
					'text'    => __( 'Show phone, email and WhatsApp from the Contact & social tab', 'wp-itourtms' ),
				),
				'topbar_show_social'   => array(
					'label'   => __( 'Social links', 'wp-itourtms' ),
					'type'    => 'checkbox',
					'default' => 1,
					'show_if' => 'topbar_enabled',
					'text'    => __( 'Show the social icons from the Contact & social tab', 'wp-itourtms' ),
				),
				'topbar_bg'            => array(
					'label'   => __( 'Top bar background', 'wp-itourtms' ),
					'type'    => 'color',
					'default' => '#1a2b48',
					'show_if' => 'topbar_enabled',
				),
				'topbar_fg'            => array(
					'label'   => __( 'Top bar text', 'wp-itourtms' ),
					'type'    => 'color',
					'default' => '#ffffff',
					'show_if' => 'topbar_enabled',
				),
				'topbar_mobile'        => array(
					'label'   => __( 'On phones', 'wp-itourtms' ),
					'type'    => 'checkbox',
					'default' => 0,
					'show_if' => 'topbar_enabled',
					'text'    => __( 'Also show the top bar on small screens', 'wp-itourtms' ),
				),
			),
		),
		'contact' => array(
			'title'       => __( 'Contact & social', 'wp-itourtms' ),
			'description' => __( 'Entered once, used by the top bar and the footer. Leave a field empty to hide it.', 'wp-itourtms' ),
			'fields'      => array(
				'contact_phone'    => array(
					'label'   => __( 'Phone', 'wp-itourtms' ),
					'type'    => 'tel',
					'default' => '',
				),
				'contact_email'    => array(
					'label'   => __( 'Email', 'wp-itourtms' ),
					'type'    => 'email',
					'default' => '',
				),
				'contact_whatsapp' => array(
					'label'   => __( 'WhatsApp', 'wp-itourtms' ),
					'type'    => 'tel',
					'default' => '',
					'help'    => __( 'International format, e.g. +20 100 000 0000. Opens a WhatsApp chat.', 'wp-itourtms' ),
				),
				'contact_address'  => array(
					'label'   => __( 'Address', 'wp-itourtms' ),
					'type'    => 'textarea',
					'default' => '',
				),
				'social_heading'   => array(
					'label' => __( 'Social links', 'wp-itourtms' ),
					'type'  => 'heading',
				),
				'social_facebook'  => array(
					'label'   => 'Facebook',
					'type'    => 'url',
					'default' => '',
				),
				'social_instagram' => array(
					'label'   => 'Instagram',
					'type'    => 'url',
					'default' => '',
				),
				'social_x'         => array(
					'label'   => 'X',
					'type'    => 'url',
					'default' => '',
				),
				'social_tiktok'    => array(
					'label'   => 'TikTok',
					'type'    => 'url',
					'default' => '',
				),
				'social_youtube'   => array(
					'label'   => 'YouTube',
					'type'    => 'url',
					'default' => '',
				),
				'social_linkedin'  => array(
					'label'   => 'LinkedIn',
					'type'    => 'url',
					'default' => '',
				),
			),
		),
		'footer'  => array(
			'title'       => __( 'Footer', 'wp-itourtms' ),
			'description' => __( 'Columns at the top of the footer, its colours and the bottom line.', 'wp-itourtms' ),
			'fields'      => wp_itourtms_footer_fields(),
		),
		'blog'    => array(
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
 * Footer fields: layout, one group per column (what it shows), colours and the bottom line.
 *
 * @return array<string,array<string,mixed>>
 */
function wp_itourtms_footer_fields(): array {
	$types    = array(
		'about'   => __( 'About (logo and text)', 'wp-itourtms' ),
		'menu'    => __( 'Menu', 'wp-itourtms' ),
		'contact' => __( 'Contact details', 'wp-itourtms' ),
		'social'  => __( 'Social links', 'wp-itourtms' ),
		'text'    => __( 'Text', 'wp-itourtms' ),
		'widgets' => __( 'Widgets (Appearance → Widgets)', 'wp-itourtms' ),
		'none'    => __( 'Empty', 'wp-itourtms' ),
	);
	$defaults = array(
		1 => array( 'about', '' ),
		2 => array( 'menu', __( 'Explore', 'wp-itourtms' ) ),
		3 => array( 'contact', __( 'Contact', 'wp-itourtms' ) ),
		4 => array( 'social', __( 'Follow us', 'wp-itourtms' ) ),
	);
	$fields   = array(
		'footer_columns' => array(
			'label'   => __( 'Layout', 'wp-itourtms' ),
			'type'    => 'select',
			'default' => '4',
			'options' => array(
				'0' => __( 'No columns (bottom line only)', 'wp-itourtms' ),
				'3' => __( '3 columns', 'wp-itourtms' ),
				'4' => __( '4 columns', 'wp-itourtms' ),
			),
		),
	);
	for ( $n = 1; $n <= 4; $n++ ) {
		$visible = 4 === $n ? 'footer_columns=4' : 'footer_columns=3|4';
		$type    = "footer_col{$n}_type";

		$fields[ "footer_col{$n}_heading" ] = array(
			/* translators: %d: column number. */
			'label'   => sprintf( __( 'Column %d', 'wp-itourtms' ), $n ),
			'type'    => 'heading',
			'show_if' => $visible,
		);
		$fields[ $type ]                    = array(
			'label'   => __( 'Shows', 'wp-itourtms' ),
			'type'    => 'select',
			'default' => $defaults[ $n ][0],
			'options' => $types,
			'show_if' => $visible,
		);
		$fields[ "footer_col{$n}_title" ]   = array(
			'label'   => __( 'Title', 'wp-itourtms' ),
			'type'    => 'text',
			'default' => $defaults[ $n ][1],
			'show_if' => array( $visible, "{$type}=menu|contact|social|text|about" ),
		);
		$fields[ "footer_col{$n}_menu" ]    = array(
			'label'    => __( 'Menu', 'wp-itourtms' ),
			'type'     => 'menu',
			'default'  => 0,
			'location' => "footer-{$n}", // The choice is stored as this menu location's assignment.
			'show_if'  => array( $visible, "{$type}=menu" ),
			'help'     => __( 'Create menus under Appearance → Menus. This sets the “Footer column” menu location.', 'wp-itourtms' ),
		);
		$fields[ "footer_col{$n}_text" ]    = array(
			'label'   => __( 'Text', 'wp-itourtms' ),
			'type'    => 'textarea',
			'default' => '',
			'show_if' => array( $visible, "{$type}=about|text" ),
			'help'    => __( 'Basic links, bold and line breaks are allowed. For “About”, leave empty to use the site tagline.', 'wp-itourtms' ),
		);
		$fields[ "footer_col{$n}_widgets" ] = array(
			/* translators: %d: column number. */
			'label'   => sprintf( __( 'Add blocks to “Footer column %d” under Appearance → Widgets.', 'wp-itourtms' ), $n ),
			'type'    => 'heading',
			'show_if' => array( $visible, "{$type}=widgets" ),
		);
	}
	return $fields + array(
		'footer_style_heading' => array(
			'label' => __( 'Colours and bottom line', 'wp-itourtms' ),
			'type'  => 'heading',
		),
		'footer_bg'            => array(
			'label'   => __( 'Footer background', 'wp-itourtms' ),
			'type'    => 'color',
			'default' => '#1a2b48',
		),
		'footer_fg'            => array(
			'label'   => __( 'Footer text', 'wp-itourtms' ),
			'type'    => 'color',
			'default' => '#ffffff',
		),
		'footer_text'          => array(
			'label'   => __( 'Bottom line', 'wp-itourtms' ),
			'type'    => 'text',
			'default' => '© {year} {site}',
			'help'    => __( '{year} becomes the current year and {site} the site title. Basic links and bold are allowed. Leave empty to hide it.', 'wp-itourtms' ),
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
			$fields += array_filter( $section['fields'], static fn( $f ) => 'heading' !== $f['type'] ); // Headings hold no value.
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
	$values = array_merge( wp_itourtms_option_defaults( $store ), (array) get_option( $store, array() ) );
	return WP_ITOURTMS_STORE === $store ? wp_itourtms_menu_location_values( $values ) : $values;
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
		case 'textarea':
			return wp_kses(
				(string) $v,
				array(
					'a'      => array( 'href' => array() ),
					'strong' => array(),
					'em'     => array(),
					'br'     => array(),
				)
			);
		case 'menu':
			$id = absint( $v );
			return $id && is_nav_menu( $id ) ? $id : 0;
		case 'email':
			return sanitize_email( (string) $v );
		case 'url':
			return esc_url_raw( trim( (string) $v ), array( 'http', 'https' ) );
		case 'tel':
			return trim( (string) preg_replace( '/[^0-9+()\-\s]/', '', (string) $v ) );
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
		// A field missing from the input (e.g. added in a theme update) gets its default, not "empty".
		$out[ $key ] = wp_itourtms_sanitize_value( $f, array_key_exists( $key, $input ) ? $input[ $key ] : $f['default'] );
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
	if ( ! empty( $o['topbar_enabled'] ) ) {
		$pairs[] = array( 'topbar_fg', 'topbar_bg', __( 'Top bar text on its background', 'wp-itourtms' ) );
	}
	$pairs[] = array( 'footer_fg', 'footer_bg', __( 'Footer text on its background', 'wp-itourtms' ) );
	$out     = array();
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

/**
 * Menu fields with a 'location' are stored as that menu location's assignment (theme_location is
 * required for menus), so Appearance → Menus and Theme Options always agree.
 *
 * @param mixed $old   Previous value (unused).
 * @param mixed $value Saved options.
 */
function wp_itourtms_sync_menu_locations( $old, $value = null ): void {
	$value     = is_array( $value ) ? $value : (array) $old;
	$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
	foreach ( wp_itourtms_store_fields() as $key => $f ) {
		if ( 'menu' === $f['type'] && ! empty( $f['location'] ) && isset( $value[ $key ] ) ) {
			$locations[ $f['location'] ] = (int) $value[ $key ];
		}
	}
	set_theme_mod( 'nav_menu_locations', $locations );
}
add_action( 'update_option_wp_itourtms_options', 'wp_itourtms_sync_menu_locations', 10, 2 );
add_action( 'add_option_wp_itourtms_options', 'wp_itourtms_sync_menu_locations', 10, 2 );

/**
 * Show the current location assignment as the value of location-backed menu fields.
 *
 * @param array<string,mixed> $values Option values.
 * @return array<string,mixed>
 */
function wp_itourtms_menu_location_values( array $values ): array {
	$locations = get_nav_menu_locations();
	foreach ( wp_itourtms_store_fields() as $key => $f ) {
		if ( 'menu' === $f['type'] && ! empty( $f['location'] ) ) {
			$values[ $key ] = (int) ( $locations[ $f['location'] ] ?? 0 );
		}
	}
	return $values;
}

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
