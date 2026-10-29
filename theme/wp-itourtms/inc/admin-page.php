<?php
/**
 * Appearance → Theme Options: the theme's settings (Settings API, one option) plus the built-in
 * getting-started guide, user manual and documentation. Core admin UI only.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the page under Appearance.
 */
function wp_itourtms_admin_menu(): void {
	$hook = add_theme_page(
		__( 'WP iTourTMS Theme Options', 'wp-itourtms' ),
		__( 'Theme Options', 'wp-itourtms' ),
		'edit_theme_options',
		'wp-itourtms',
		'wp_itourtms_admin_render'
	);
	if ( $hook ) {
		add_action(
			'admin_enqueue_scripts',
			static function ( string $hook_suffix ) use ( $hook ): void {
				if ( $hook_suffix !== $hook ) {
					return;
				}
				wp_enqueue_style( 'wp-itourtms-admin', get_template_directory_uri() . '/assets/css/admin.css', array( 'wp-color-picker' ), WP_ITOURTMS_VERSION );
				wp_enqueue_script( 'wp-itourtms-admin', get_template_directory_uri() . '/assets/js/admin.js', array( 'wp-color-picker' ), WP_ITOURTMS_VERSION, true );
			}
		);
	}
}
add_action( 'admin_menu', 'wp_itourtms_admin_menu' );

/**
 * Register the option, sections and fields with the Settings API.
 */
function wp_itourtms_register_settings(): void {
	register_setting(
		'wp_itourtms',
		'wp_itourtms_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'wp_itourtms_sanitize_options',
			'default'           => array(),
		)
	);
	foreach ( wp_itourtms_option_fields() as $section_id => $section ) {
		add_settings_section( 'wp_itourtms_' . $section_id, $section['title'], '__return_null', 'wp-itourtms' );
		foreach ( $section['fields'] as $key => $f ) {
			add_settings_field(
				$key,
				$f['label'],
				'wp_itourtms_render_field',
				'wp-itourtms',
				'wp_itourtms_' . $section_id,
				array(
					'key'       => $key,
					'field'     => $f,
					'label_for' => 'checkbox' === $f['type'] ? null : 'wp-itourtms-' . $key,
				)
			);
		}
	}
}
add_action( 'admin_init', 'wp_itourtms_register_settings' );

/**
 * Render one field.
 *
 * @param array{key:string,field:array<string,mixed>} $args Field args.
 */
function wp_itourtms_render_field( array $args ): void {
	$key   = $args['key'];
	$f     = $args['field'];
	$value = wp_itourtms_option( $key );
	$name  = 'wp_itourtms_options[' . $key . ']';
	$id    = 'wp-itourtms-' . $key;
	switch ( $f['type'] ) {
		case 'color':
			printf( '<input type="text" class="wp-itourtms-color" id="%1$s" name="%2$s" value="%3$s" data-default-color="%4$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), esc_attr( $f['default'] ) );
			break;
		case 'checkbox':
			printf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s> %4$s</label>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $value, true, false ), esc_html( $f['text'] ) );
			break;
		case 'px':
		case 'number':
			printf( '<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$d" min="%4$d" max="%5$d" step="1"> %6$s', esc_attr( $id ), esc_attr( $name ), (int) $value, (int) $f['min'], (int) $f['max'], 'px' === $f['type'] ? 'px' : esc_html__( 'words', 'wp-itourtms' ) );
			break;
		default:
			printf( '<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
	}
	if ( ! empty( $f['help'] ) ) {
		echo '<p class="description">' . esc_html( $f['help'] ) . '</p>';
	}
}

/**
 * Sanitize the submitted options; reset to defaults when asked. Warns about low-contrast colours.
 *
 * @param mixed $input Submitted values.
 * @return array<string,mixed>
 */
function wp_itourtms_sanitize_options( $input ): array {
	$input = is_array( $input ) ? $input : array();
	if ( ! empty( $_POST['wp_itourtms_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php checks the settings nonce.
		add_settings_error( 'wp_itourtms_options', 'reset', __( 'Theme options were reset to their defaults.', 'wp-itourtms' ), 'updated' );
		return array();
	}
	$out = array();
	foreach ( wp_itourtms_option_fields() as $section ) {
		foreach ( $section['fields'] as $key => $f ) {
			$v = $input[ $key ] ?? null;
			switch ( $f['type'] ) {
				case 'color':
					$out[ $key ] = sanitize_hex_color( (string) $v ) ?? $f['default'];
					break;
				case 'checkbox':
					$out[ $key ] = empty( $v ) ? 0 : 1;
					break;
				case 'px':
				case 'number':
					$out[ $key ] = max( (int) $f['min'], min( (int) $f['max'], (int) $v ) );
					break;
				default:
					$out[ $key ] = wp_kses(
						(string) $v,
						array(
							'a'      => array( 'href' => array() ),
							'strong' => array(),
							'em'     => array(),
						)
					);
			}
		}
	}
	$pairs = array(
		array( 'color_ink', 'color_base', __( 'Text on the page background', 'wp-itourtms' ) ),
		array( 'color_ink', 'color_brand', __( 'Text on brand buttons', 'wp-itourtms' ) ),
		array( 'color_base', 'color_brand_dark', __( 'White text on the hover colour', 'wp-itourtms' ) ),
	);
	foreach ( $pairs as $p ) {
		$ratio = wp_itourtms_contrast( $out[ $p[0] ], $out[ $p[1] ] );
		if ( $ratio < 4.5 ) {
			add_settings_error(
				'wp_itourtms_options',
				'contrast-' . $p[0] . $p[1],
				/* translators: 1: colour pair, 2: contrast ratio. */
				sprintf( __( 'Saved, but hard to read: %1$s has a contrast of %2$s:1. Aim for at least 4.5:1.', 'wp-itourtms' ), $p[2], number_format_i18n( $ratio, 1 ) ),
				'warning'
			);
		}
	}
	return $out;
}

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
 * Render the page: Options tab (form) and the manual tabs.
 */
function wp_itourtms_admin_render(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$tabs = array( 'options' => array( 'title' => __( 'Options', 'wp-itourtms' ) ) ) + wp_itourtms_manual();
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'options'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'options';
	}
	echo '<div class="wrap wp-itourtms-admin">';
	echo '<h1>' . esc_html__( 'Theme Options', 'wp-itourtms' ) . ' <span class="wp-itourtms-version">' . esc_html( 'WP iTourTMS ' . wp_get_theme( get_template() )->get( 'Version' ) ) . '</span></h1>';
	echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Theme Options sections', 'wp-itourtms' ) . '">';
	foreach ( $tabs as $key => $part ) {
		$url = add_query_arg(
			array(
				'page' => 'wp-itourtms',
				'tab'  => $key,
			),
			admin_url( 'themes.php' )
		);
		echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $key === $tab ? ' nav-tab-active" aria-current="page' : '' ) . '">' . esc_html( $part['title'] ) . '</a>';
	}
	echo '</nav>';

	if ( 'options' === $tab ) {
		settings_errors(); // "Settings saved." plus our contrast warnings.
		echo '<p class="wp-itourtms-intro">' . esc_html__( 'These settings apply to the whole site and to the block editor. Changes made later under Appearance → Design → Styles take priority over the colours and widths here.', 'wp-itourtms' ) . '</p>';
		echo '<form method="post" action="options.php" class="wp-itourtms-options">';
		settings_fields( 'wp_itourtms' );
		do_settings_sections( 'wp-itourtms' );
		echo '<p class="submit">';
		submit_button( __( 'Save options', 'wp-itourtms' ), 'primary', 'submit', false );
		echo ' ';
		submit_button(
			__( 'Reset to defaults', 'wp-itourtms' ),
			'secondary',
			'wp_itourtms_reset',
			false,
			array(
				'onclick'      => 'return confirm(this.dataset.confirm)',
				'data-confirm' => __( 'Reset all theme options to their defaults?', 'wp-itourtms' ),
			)
		);
		echo '</p></form></div>';
		return;
	}

	echo '<div class="wp-itourtms-layout"><nav class="wp-itourtms-toc" aria-label="' . esc_attr__( 'On this page', 'wp-itourtms' ) . '"><ul>';
	foreach ( $tabs[ $tab ]['sections'] as $s ) {
		echo '<li><a href="#' . esc_attr( $s['id'] ) . '">' . esc_html( $s['title'] ) . '</a></li>';
	}
	echo '</ul></nav><div class="wp-itourtms-body">';
	echo wp_itourtms_manual_sections_html( $tabs[ $tab ]['sections'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
	echo '</div></div></div>';
}
