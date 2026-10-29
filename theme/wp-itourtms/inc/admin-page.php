<?php
/**
 * Appearance → Theme Options. Tabs: License (commercial edition), Options, and the built-in manual.
 * Styled after the iTourTMS admin (cards, pill tabs, switches); styles are scoped to this screen.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the page under Appearance and its assets.
 */
function wp_itourtms_admin_menu(): void {
	$hook = add_theme_page(
		__( 'WP iTourTMS Theme Options', 'wp-itourtms' ),
		__( 'Theme Options', 'wp-itourtms' ),
		'edit_theme_options',
		'wp-itourtms',
		'wp_itourtms_admin_render'
	);
	if ( ! $hook ) {
		return;
	}
	add_action(
		'admin_enqueue_scripts',
		static function ( string $hook_suffix ) use ( $hook ): void {
			if ( $hook_suffix !== $hook ) {
				return;
			}
			$uri = get_template_directory_uri() . '/assets';
			$dir = get_template_directory() . '/assets';
			$dev = 'theme' === wp_get_development_mode();
			wp_enqueue_style( 'wp-itourtms-admin', $uri . '/css/admin.css', array(), $dev ? (string) filemtime( $dir . '/css/admin.css' ) : WP_ITOURTMS_VERSION );
			wp_enqueue_script( 'wp-itourtms-admin', $uri . '/js/admin.js', array(), $dev ? (string) filemtime( $dir . '/js/admin.js' ) : WP_ITOURTMS_VERSION, true );
			wp_localize_script(
				'wp-itourtms-admin',
				'wpItourtms',
				array(
					'rest'  => esc_url_raw( rest_url( 'wp-itourtms/v1/' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
					'i18n'  => array(
						'saved'         => __( 'Theme options saved.', 'wp-itourtms' ),
						'reset'         => __( 'Theme options were reset to their defaults.', 'wp-itourtms' ),
						'confirmReset'  => __( 'Reset all theme options to their defaults?', 'wp-itourtms' ),
						'error'         => __( 'Something went wrong. Please try again.', 'wp-itourtms' ),
						'activated'     => __( 'License activated.', 'wp-itourtms' ),
						'removed'       => __( 'License removed from this site.', 'wp-itourtms' ),
						'checked'       => __( 'License checked with the server.', 'wp-itourtms' ),
						'confirmRemove' => __( 'Remove the license from this site?', 'wp-itourtms' ),
						'search'        => __( 'Search…', 'wp-itourtms' ),
						'noMatch'       => __( 'No matches', 'wp-itourtms' ),
						'keepTyping'    => __( 'Keep typing to see more', 'wp-itourtms' ),
					),
				)
			);
		}
	);
}
add_action( 'admin_menu', 'wp_itourtms_admin_menu' );

/**
 * Tabs of the page, in order. The license tab comes first in the commercial edition.
 *
 * @return array<string,string> slug => title.
 */
function wp_itourtms_admin_tabs(): array {
	$tabs = array();
	if ( function_exists( 'wp_itourtms_license_check' ) ) {
		$tabs['license'] = __( 'License', 'wp-itourtms' );
	}
	$tabs['options'] = __( 'Options', 'wp-itourtms' );
	foreach ( wp_itourtms_manual() as $key => $part ) {
		$tabs[ $key ] = $part['title'];
	}
	return $tabs;
}

/**
 * Render the page.
 */
function wp_itourtms_admin_render(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$tabs = wp_itourtms_admin_tabs();
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = (string) array_key_first( $tabs );
	}
	echo '<div class="wrap wp-itourtms-admin"><div class="wpit-page">';
	echo '<header class="wpit-head"><h1 class="wpit-title">' . esc_html__( 'Theme Options', 'wp-itourtms' ) . '</h1>';
	echo '<p class="wpit-sub">' . esc_html( sprintf( /* translators: %s: theme version. */ __( 'WP iTourTMS %s · settings, license and help for your theme', 'wp-itourtms' ), wp_get_theme( get_template() )->get( 'Version' ) ) ) . '</p></header>';

	echo '<nav class="wpit-tabs" aria-label="' . esc_attr__( 'Theme Options sections', 'wp-itourtms' ) . '">';
	foreach ( $tabs as $key => $title ) {
		$url = add_query_arg(
			array(
				'page' => 'wp-itourtms',
				'tab'  => $key,
			),
			admin_url( 'themes.php' )
		);
		echo '<a class="wpit-tab' . ( $key === $tab ? ' is-active" aria-current="page' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
	}
	echo '</nav>';

	if ( 'license' === $tab ) {
		wp_itourtms_render_license_tab();
	} elseif ( 'options' === $tab ) {
		wp_itourtms_render_options_tab();
	} else {
		wp_itourtms_render_manual_tab( $tab );
	}
	echo '</div><div class="wpit-toasts" aria-live="polite"></div></div>';
}

/**
 * Options: vertical section tabs on the left, one card per section on the right.
 */
function wp_itourtms_render_options_tab(): void {
	$fields = wp_itourtms_option_fields();
	$first  = (string) array_key_first( $fields );

	echo '<form class="wpit-options" data-wpit-options novalidate><div class="wpit-split">';
	echo '<div class="wpit-vtabs" role="tablist" aria-orientation="vertical" aria-label="' . esc_attr__( 'Option groups', 'wp-itourtms' ) . '">';
	foreach ( $fields as $id => $section ) {
		$on = $id === $first;
		printf(
			'<button type="button" class="wpit-vtab%1$s" role="tab" id="wpit-tab-%2$s" aria-controls="wpit-panel-%2$s" aria-selected="%3$s" tabindex="%4$s">%5$s</button>',
			$on ? ' is-active' : '',
			esc_attr( $id ),
			$on ? 'true' : 'false',
			$on ? '0' : '-1',
			esc_html( $section['title'] )
		);
	}
	echo '</div><div class="wpit-panels">';

	$descriptions = array(
		'colors' => __( 'Your brand colours. They apply to the site and the block editor.', 'wp-itourtms' ),
		'layout' => __( 'How wide content runs and how rounded corners are.', 'wp-itourtms' ),
		'header' => __( 'How the site header behaves.', 'wp-itourtms' ),
		'footer' => __( 'The line at the bottom of every page.', 'wp-itourtms' ),
		'blog'   => __( 'What posts show in lists and on their own page.', 'wp-itourtms' ),
	);
	foreach ( $fields as $id => $section ) {
		$values = wp_itourtms_options( $section['store'] );
		$desc   = $section['description'] ?? ( $descriptions[ $id ] ?? '' );
		printf( '<section class="wpit-card" role="tabpanel" id="wpit-panel-%1$s" aria-labelledby="wpit-tab-%1$s" data-store="%2$s"%3$s>', esc_attr( $id ), esc_attr( $section['store'] ), $id === $first ? '' : ' hidden' );
		echo '<div class="wpit-card-head"><h2 class="wpit-card-title">' . esc_html( $section['title'] ) . '</h2>';
		if ( '' !== $desc ) {
			echo '<p class="wpit-card-desc">' . esc_html( $desc ) . '</p>';
		}
		echo '</div><div class="wpit-card-body' . ( 'colors' === $id ? ' wpit-grid' : '' ) . '">';
		foreach ( $section['fields'] as $key => $f ) {
			wp_itourtms_render_option_field( $key, $f, $values[ $key ] ?? ( $f['default'] ?? null ) );
		}
		if ( 'fonts' === $id ) {
			echo '<div class="wpit-font-preview" data-wpit-font-preview><p class="wpit-label">' . esc_html__( 'Preview', 'wp-itourtms' ) . '</p>';
			echo '<p class="wpit-preview-heading">' . esc_html__( 'Nile cruise from Luxor to Aswan', 'wp-itourtms' ) . '</p>';
			echo '<p class="wpit-preview-body">' . esc_html__( 'Four nights on the river with guided visits to Karnak, Edfu and Kom Ombo. Breakfast, lunch and dinner included.', 'wp-itourtms' ) . '</p></div>';
		}
		echo '</div></section>';
	}
	echo '<div class="wpit-actions"><button type="submit" class="wpit-btn wpit-btn-primary">' . esc_html__( 'Save options', 'wp-itourtms' ) . '</button>';
	echo '<button type="button" class="wpit-btn wpit-btn-outline" data-wpit-reset>' . esc_html__( 'Reset to defaults', 'wp-itourtms' ) . '</button>';
	echo '<p class="wpit-note">' . esc_html__( 'Changes under Appearance → Design → Styles take priority over these colours and widths.', 'wp-itourtms' ) . '</p></div>';
	echo '</div></div></form>';
}

/**
 * One option control.
 *
 * @param string              $key   Option key.
 * @param array<string,mixed> $f     Field definition.
 * @param mixed               $value Current value.
 */
function wp_itourtms_render_option_field( string $key, array $f, $value ): void {
	$id   = 'wpit-' . $key;
	$help = empty( $f['help'] ) ? '' : '<p class="wpit-help" id="' . esc_attr( $id ) . '-help">' . esc_html( $f['help'] ) . '</p>';
	$desc = empty( $f['help'] ) ? '' : ' aria-describedby="' . esc_attr( $id ) . '-help"';
	// Conditions become "wpit-<key>" or "wpit-<key>=a|b", joined by ";" (all must hold); admin.js evaluates them.
	$cond = empty( $f['show_if'] ) ? '' : ' data-show-if="' . esc_attr( implode( ';', array_map( static fn( $c ) => 'wpit-' . $c, (array) $f['show_if'] ) ) ) . '"';

	if ( 'heading' === $f['type'] ) {
		echo '<div class="wpit-subhead"' . $cond . '><h3>' . esc_html( $f['label'] ) . '</h3>' . $help . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above.
		return;
	}
	if ( 'checkbox' === $f['type'] ) {
		echo '<div class="wpit-field wpit-field-row"' . $cond . '><div><label class="wpit-label" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $cond built with esc_attr.
		echo '<p class="wpit-help">' . esc_html( $f['text'] ) . '</p></div>';
		printf( '<button type="button" role="switch" class="wpit-switch" id="%1$s" data-key="%2$s" aria-checked="%3$s"><span class="wpit-switch-thumb"></span></button></div>', esc_attr( $id ), esc_attr( $key ), $value ? 'true' : 'false' );
		return;
	}
	echo '<div class="wpit-field"' . $cond . '><label class="wpit-label" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $cond built with esc_attr.
	switch ( $f['type'] ) {
		case 'textarea':
			echo '<textarea class="wpit-input" id="' . esc_attr( $id ) . '" data-key="' . esc_attr( $key ) . '" rows="3"' . $desc . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $desc built with esc_attr.
			break;
		case 'menu':
			echo '<select class="wpit-input" id="' . esc_attr( $id ) . '" data-key="' . esc_attr( $key ) . '"' . $desc . '><option value="0">' . esc_html__( '— Choose a menu —', 'wp-itourtms' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $desc built with esc_attr.
			foreach ( wp_get_nav_menus() as $menu ) {
				echo '<option value="' . (int) $menu->term_id . '"' . selected( (int) $value, (int) $menu->term_id, false ) . '>' . esc_html( $menu->name ) . '</option>';
			}
			echo '</select>';
			break;
		case 'email':
		case 'url':
		case 'tel':
			printf(
				'<input type="%1$s" class="wpit-input" id="%2$s" data-key="%3$s" value="%4$s" placeholder="%5$s"%6$s>',
				esc_attr( $f['type'] ),
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( (string) $value ),
				esc_attr(
					array(
						'email' => 'info@example.com',
						'url'   => 'https://',
						'tel'   => '+20 100 000 0000',
					)[ $f['type'] ]
				),
				$desc // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr above.
			);
			break;
		case 'color':
			printf(
				'<div class="wpit-color"><input type="color" class="wpit-swatch" value="%1$s" aria-label="%2$s" data-sync="%3$s"><input type="text" class="wpit-input wpit-mono" id="%3$s" data-key="%4$s" value="%1$s" maxlength="7" pattern="#[0-9a-fA-F]{6}"%5$s></div>',
				esc_attr( (string) $value ),
				/* translators: %s: colour name. */
				esc_attr( sprintf( __( 'Pick %s colour', 'wp-itourtms' ), $f['label'] ) ),
				esc_attr( $id ),
				esc_attr( $key ),
				$desc // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr above.
			);
			break;
		case 'font':
			// A plain <select>; admin.js turns every .wpit-input select into a searchable combobox.
			echo '<select class="wpit-input" id="' . esc_attr( $id ) . '" data-key="' . esc_attr( $key ) . '"' . $desc . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $desc built with esc_attr.
			echo '<option value="">' . esc_html( 'font_heading' === $key ? __( 'Same as body', 'wp-itourtms' ) : __( 'System font (fastest)', 'wp-itourtms' ) ) . '</option>';
			foreach ( wp_itourtms_google_fonts() as $family => $info ) {
				echo '<option value="' . esc_attr( $family ) . '" data-hint="' . esc_attr( $info[0] ) . '"' . selected( (string) $value, $family, false ) . '>' . esc_html( $family ) . '</option>';
			}
			echo '</select>';
			break;
		case 'select':
			echo '<select class="wpit-input" id="' . esc_attr( $id ) . '" data-key="' . esc_attr( $key ) . '"' . $desc . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $desc built with esc_attr.
			foreach ( (array) $f['options'] as $opt => $text ) {
				echo '<option value="' . esc_attr( (string) $opt ) . '"' . selected( (string) $value, (string) $opt, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select>';
			break;
		case 'page':
			echo '<select class="wpit-input" id="' . esc_attr( $id ) . '" data-key="' . esc_attr( $key ) . '"' . $desc . '><option value="0">' . esc_html__( '— Choose a page —', 'wp-itourtms' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $desc built with esc_attr.
			foreach ( get_pages( array( 'post_status' => 'publish,private,draft' ) ) as $page ) {
				echo '<option value="' . (int) $page->ID . '"' . selected( (int) $value, (int) $page->ID, false ) . '>' . esc_html( $page->post_title ? $page->post_title : '#' . $page->ID ) . '</option>';
			}
			echo '</select>';
			break;
		case 'px':
		case 'number':
			printf(
				'<div class="wpit-range"><input type="range" min="%1$d" max="%2$d" value="%3$d" aria-hidden="true" tabindex="-1" data-sync="%4$s"><div class="wpit-suffix"><input type="number" class="wpit-input" id="%4$s" data-key="%5$s" min="%1$d" max="%2$d" value="%3$d"%6$s><span>%7$s</span></div></div>',
				(int) $f['min'],
				(int) $f['max'],
				(int) $value,
				esc_attr( $id ),
				esc_attr( $key ),
				$desc, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr above.
				'px' === $f['type'] ? 'px' : esc_html__( 'words', 'wp-itourtms' )
			);
			break;
		default:
			printf( '<input type="text" class="wpit-input" id="%1$s" data-key="%2$s" value="%3$s"%4$s>', esc_attr( $id ), esc_attr( $key ), esc_attr( (string) $value ), $desc ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $desc built with esc_attr.
	}
	echo $help . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_html above.
}

/**
 * License: status card and activation form (commercial edition only).
 */
function wp_itourtms_render_license_tab(): void {
	echo '<section class="wpit-card" data-wpit-license><div class="wpit-card-head"><h2 class="wpit-card-title">' . esc_html__( 'License Information', 'wp-itourtms' ) . '</h2>';
	echo '<p class="wpit-card-desc">' . esc_html__( 'Activate your license to receive theme updates and support. Your site keeps working either way.', 'wp-itourtms' ) . '</p></div>';
	if ( ! wp_itourtms_license_configured() ) {
		echo '<div class="wpit-card-body"><p class="wpit-alert">' . esc_html__( 'The license server is not set up on this site yet. Add WP_ITOURTMS_LICENSE_SERVER and WP_ITOURTMS_LICENSE_PUBKEY to wp-config.php (see Documentation → License).', 'wp-itourtms' ) . '</p></div></section>';
		return;
	}
	$l = wp_itourtms_license_check();
	echo '<div class="wpit-card-body">';
	wp_itourtms_render_license_details( $l );
	echo '<form class="wpit-license-form" data-wpit-license-form><label class="wpit-label" for="wpit-license-key">' . esc_html( $l['ok'] ? __( 'Replace license key', 'wp-itourtms' ) : __( 'License key', 'wp-itourtms' ) ) . '</label>';
	echo '<textarea class="wpit-input wpit-mono" id="wpit-license-key" rows="3" spellcheck="false" autocomplete="off" placeholder="' . esc_attr__( 'Paste the license key from your purchase email', 'wp-itourtms' ) . '"></textarea>';
	echo '<div class="wpit-actions"><button type="submit" class="wpit-btn wpit-btn-primary">' . esc_html__( 'Activate', 'wp-itourtms' ) . '</button>';
	if ( 'none' !== $l['status'] ) {
		echo '<button type="button" class="wpit-btn wpit-btn-outline" data-wpit-license-check>' . esc_html__( 'Check now', 'wp-itourtms' ) . '</button>';
		echo '<button type="button" class="wpit-btn wpit-btn-ghost-danger" data-wpit-license-remove>' . esc_html__( 'Remove license', 'wp-itourtms' ) . '</button>';
	}
	echo '</div></form></div></section>';
}

/**
 * License details grid.
 *
 * @param array<string,mixed> $l Result of wp_itourtms_license_check().
 */
function wp_itourtms_render_license_details( array $l ): void {
	$labels               = array(
		'none'            => array( __( 'Not activated', 'wp-itourtms' ), 'muted' ),
		'active'          => array( __( 'Active', 'wp-itourtms' ), 'ok' ),
		'grace'           => array( __( 'Active (needs attention)', 'wp-itourtms' ), 'warn' ),
		'expired'         => array( __( 'Expired', 'wp-itourtms' ), 'bad' ),
		'revoked'         => array( __( 'Revoked', 'wp-itourtms' ), 'bad' ),
		'invalid'         => array( __( 'Invalid key', 'wp-itourtms' ), 'bad' ),
		'domain_mismatch' => array( __( 'Not licensed for this domain', 'wp-itourtms' ), 'bad' ),
		'install_blocked' => array( __( 'Install limit reached', 'wp-itourtms' ), 'bad' ),
		'ip_mismatch'     => array( __( 'Server IP not allowed', 'wp-itourtms' ), 'bad' ),
		'grace_expired'   => array( __( 'Could not reach the license server', 'wp-itourtms' ), 'bad' ),
	);
	list( $label, $tone ) = $labels[ $l['status'] ] ?? $labels['invalid'];
	$or                   = static fn( string $k ): string => empty( $l[ $k ] ) ? '—' : (string) $l[ $k ];
	$left                 = isset( $l['daysLeft'] ) ? (int) $l['daysLeft'] : null;
	$days                 = null === $left ? '—' : sprintf( /* translators: %d: days. */ _n( '%d day', '%d days', $left, 'wp-itourtms' ), $left );
	$items                = array(
		__( 'License Key', 'wp-itourtms' )    => empty( $l['keyEnd'] ) ? '—' : '••••••••' . $l['keyEnd'],
		__( 'Status', 'wp-itourtms' )         => '',
		__( 'Licensed to', 'wp-itourtms' )    => $or( 'tenant' ),
		__( 'Plan', 'wp-itourtms' )           => $or( 'plan' ),
		__( 'Expires On', 'wp-itourtms' )     => $or( 'expiresAt' ),
		__( 'Days Remaining', 'wp-itourtms' ) => $days,
		__( 'Domains', 'wp-itourtms' )        => empty( $l['domains'] ) ? '—' : implode( ', ', (array) $l['domains'] ),
		__( 'Last checked', 'wp-itourtms' )   => $or( 'checkedAt' ),
	);
	echo '<dl class="wpit-details" data-wpit-license-details>';
	foreach ( $items as $k => $v ) {
		echo '<div><dt>' . esc_html( $k ) . '</dt><dd>';
		if ( __( 'Status', 'wp-itourtms' ) === $k ) {
			echo '<span class="wpit-badge is-' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
		} else {
			echo '<span' . ( __( 'License Key', 'wp-itourtms' ) === $k ? ' class="wpit-mono"' : '' ) . '>' . esc_html( (string) $v ) . '</span>';
		}
		echo '</dd></div>';
	}
	echo '</dl>';
}

/**
 * A manual tab: section index on the left, sections as cards.
 *
 * @param string $tab Manual tab key.
 */
function wp_itourtms_render_manual_tab( string $tab ): void {
	$part = wp_itourtms_manual()[ $tab ];
	echo '<div class="wpit-split"><nav class="wpit-vtabs is-links" aria-label="' . esc_attr__( 'On this page', 'wp-itourtms' ) . '">';
	foreach ( $part['sections'] as $s ) {
		echo '<a class="wpit-vtab" href="#' . esc_attr( $s['id'] ) . '">' . esc_html( $s['title'] ) . '</a>';
	}
	echo '</nav><div class="wpit-panels wpit-doc">';
	echo wp_itourtms_manual_sections_html( $part['sections'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
	echo '</div></div>';
}
