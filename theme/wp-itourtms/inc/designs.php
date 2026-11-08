<?php
/**
 * The six design presets (Theme Options → Design). Picking one fills in colours, fonts, shape,
 * header, top bar, footer and booking-widget model; every value stays editable afterwards.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Presets: slug => [ name, note, values ].
 *
 * @return array<string,array{name:string,note:string,values:array<string,string|int>}>
 */
function wp_itourtms_design_presets(): array {
	return array(
		'nile'     => array(
			'name'   => __( 'Nile Classic', 'wp-itourtms' ),
			'note'   => __( 'Navy and sunset orange, system type, tabbed search. Calm and familiar.', 'wp-itourtms' ),
			'values' => array(
				'color_brand'          => '#e8851a',
				'color_brand_dark'     => '#9a530a',
				'color_ink'            => '#1a2b48',
				'color_muted'          => '#4f5d6b',
				'color_surface'        => '#f4f6f9',
				'color_base'           => '#ffffff',
				'font_body'            => '',
				'font_heading'         => '',
				'heading_weight'       => '700',
				'radius'               => 10,
				'header_layout'        => 'classic',
				'header_bg'            => '#ffffff',
				'header_fg'            => '#1a2b48',
				'topbar_style'         => 'split',
				'topbar_bg'            => '#1a2b48',
				'topbar_fg'            => '#ffffff',
				'footer_bg'            => '#1a2b48',
				'footer_fg'            => '#ffffff',
				'booking_widget_model' => 'tabs',
			),
		),
		'desert'   => array(
			'name'   => __( 'Desert Dusk', 'wp-itourtms' ),
			'note'   => __( 'Terracotta and espresso, Fraunces headings, centred header, search bar. Warm and editorial.', 'wp-itourtms' ),
			'values' => array(
				'color_brand'          => '#e07a5f',
				'color_brand_dark'     => '#9c3d24',
				'color_ink'            => '#2b1d16',
				'color_muted'          => '#5f4d42',
				'color_surface'        => '#f6efe9',
				'color_base'           => '#ffffff',
				'font_body'            => 'DM Sans',
				'font_heading'         => 'Fraunces',
				'heading_weight'       => '600',
				'radius'               => 4,
				'header_layout'        => 'centered',
				'header_bg'            => '#ffffff',
				'header_fg'            => '#2b1d16',
				'topbar_style'         => 'announce',
				'topbar_bg'            => '#2b1d16',
				'topbar_fg'            => '#ffffff',
				'footer_bg'            => '#2b1d16',
				'footer_fg'            => '#f6efe9',
				'booking_widget_model' => 'bar',
			),
		),
		'redsea'   => array(
			'name'   => __( 'Red Sea', 'wp-itourtms' ),
			'note'   => __( 'Lagoon teal on deep water, Manrope, header over the hero, search card. Fresh and open.', 'wp-itourtms' ),
			'values' => array(
				'color_brand'          => '#2ec4b6',
				'color_brand_dark'     => '#0f6b63',
				'color_ink'            => '#0b2b33',
				'color_muted'          => '#47636c',
				'color_surface'        => '#eef6f6',
				'color_base'           => '#ffffff',
				'font_body'            => 'Manrope',
				'font_heading'         => 'Manrope',
				'heading_weight'       => '800',
				'radius'               => 16,
				'header_layout'        => 'transparent',
				'header_bg'            => '#ffffff',
				'header_fg'            => '#0b2b33',
				'topbar_style'         => 'minimal',
				'topbar_bg'            => '#0b2b33',
				'topbar_fg'            => '#ffffff',
				'footer_bg'            => '#0b2b33',
				'footer_fg'            => '#ffffff',
				'booking_widget_model' => 'card',
			),
		),
		'midnight' => array(
			'name'   => __( 'Midnight Gold', 'wp-itourtms' ),
			'note'   => __( 'Near-black and antique gold, Cormorant headings, square corners. Luxury cruises and private tours.', 'wp-itourtms' ),
			'values' => array(
				'color_brand'          => '#d4a73a',
				'color_brand_dark'     => '#7a5a12',
				'color_ink'            => '#14121a',
				'color_muted'          => '#55505e',
				'color_surface'        => '#f4f2ee',
				'color_base'           => '#ffffff',
				'font_body'            => 'Inter',
				'font_heading'         => 'Cormorant Garamond',
				'heading_weight'       => '600',
				'radius'               => 0,
				'header_layout'        => 'classic',
				'header_bg'            => '#14121a',
				'header_fg'            => '#f2ede4',
				'topbar_style'         => 'centered',
				'topbar_bg'            => '#000000',
				'topbar_fg'            => '#e9dcc0',
				'footer_bg'            => '#0f0d13',
				'footer_fg'            => '#f2ede4',
				'booking_widget_model' => 'tabs',
			),
		),
		'oasis'    => array(
			'name'   => __( 'Oasis', 'wp-itourtms' ),
			'note'   => __( 'Palm green, Nunito, soft round shapes, floating header, search bar. Friendly for families.', 'wp-itourtms' ),
			'values' => array(
				'color_brand'          => '#4caf6e',
				'color_brand_dark'     => '#24663b',
				'color_ink'            => '#13261b',
				'color_muted'          => '#4a5f52',
				'color_surface'        => '#eef5f0',
				'color_base'           => '#ffffff',
				'font_body'            => 'Nunito',
				'font_heading'         => 'Nunito',
				'heading_weight'       => '800',
				'radius'               => 24,
				'header_layout'        => 'floating',
				'header_bg'            => '#ffffff',
				'header_fg'            => '#13261b',
				'topbar_style'         => 'centered',
				'topbar_bg'            => '#24663b',
				'topbar_fg'            => '#ffffff',
				'footer_bg'            => '#13261b',
				'footer_fg'            => '#ffffff',
				'booking_widget_model' => 'bar',
			),
		),
		'itourtms' => array(
			'name'   => __( 'iTourTMS', 'wp-itourtms' ),
			'note'   => __( 'Aubergine and orchid, Inter, dark header, search card. Matches the iTourTMS dashboard.', 'wp-itourtms' ),
			'values' => array(
				'color_brand'          => '#b366b3',
				'color_brand_dark'     => '#660066',
				'color_ink'            => '#17141a',
				'color_muted'          => '#5b6275',
				'color_surface'        => '#f4eef4',
				'color_base'           => '#ffffff',
				'font_body'            => 'Inter',
				'font_heading'         => 'Inter',
				'heading_weight'       => '700',
				'radius'               => 10,
				'header_layout'        => 'classic',
				'header_bg'            => '#1a0a1a',
				'header_fg'            => '#f2eef6',
				'topbar_style'         => 'split',
				'topbar_bg'            => '#120712',
				'topbar_fg'            => '#e7dcef',
				'footer_bg'            => '#1a0a1a',
				'footer_fg'            => '#f2eef6',
				'booking_widget_model' => 'card',
			),
		),
	);
}

/**
 * The booking widget's default model follows the chosen design.
 */
add_filter(
	'itour_booking_widget_default_model',
	static fn(): string => (string) wp_itourtms_option( 'booking_widget_model' )
);
