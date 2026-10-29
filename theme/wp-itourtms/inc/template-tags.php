<?php
/**
 * Template helpers shared by the top bar and the footer: contact details, social links, icons.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * An icon as SVG: a phone glyph, or WordPress core's Social Icons block icon for a service.
 *
 * @param string $service phone|mail|whatsapp|facebook|instagram|x|tiktok|youtube|linkedin.
 */
function wp_itourtms_icon( string $service ): string {
	if ( 'phone' === $service ) {
		$svg = '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z"/></svg>';
	} elseif ( 'address' === $service ) {
		$svg = '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>';
	} else {
		$svg = function_exists( 'block_core_social_link_get_icon' ) ? (string) block_core_social_link_get_icon( $service ) : '';
	}
	return wp_kses(
		$svg,
		array(
			'svg'  => array(
				'width'       => true,
				'height'      => true,
				'viewbox'     => true,
				'version'     => true,
				'xmlns'       => true,
				'aria-hidden' => true,
				'focusable'   => true,
				'role'        => true,
			),
			'path' => array(
				'd'    => true,
				'fill' => true,
			),
		)
	);
}

/**
 * Contact links from Theme Options → Contact & social: [ href, icon, label ] (empty fields skipped).
 *
 * @param bool $with_address Include the address (no link).
 * @return array<int,array{0:string,1:string,2:string}>
 */
function wp_itourtms_contact_items( bool $with_address = false ): array {
	$o     = wp_itourtms_options();
	$items = array();
	if ( '' !== $o['contact_phone'] ) {
		$items[] = array( 'tel:' . preg_replace( '/[^0-9+]/', '', (string) $o['contact_phone'] ), 'phone', (string) $o['contact_phone'] );
	}
	if ( '' !== $o['contact_email'] ) {
		$items[] = array( 'mailto:' . antispambot( (string) $o['contact_email'] ), 'mail', antispambot( (string) $o['contact_email'] ) );
	}
	if ( '' !== $o['contact_whatsapp'] ) {
		$items[] = array( 'https://wa.me/' . preg_replace( '/\D/', '', (string) $o['contact_whatsapp'] ), 'whatsapp', __( 'WhatsApp', 'wp-itourtms' ) );
	}
	if ( $with_address && '' !== $o['contact_address'] ) {
		$items[] = array( '', 'address', (string) $o['contact_address'] );
	}
	return $items;
}

/**
 * Social links that are filled in: service => [ url, name ].
 *
 * @return array<string,array{0:string,1:string}>
 */
function wp_itourtms_social_items(): array {
	$o     = wp_itourtms_options();
	$names = array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'x'         => 'X',
		'tiktok'    => 'TikTok',
		'youtube'   => 'YouTube',
		'linkedin'  => 'LinkedIn',
	);
	$out   = array();
	foreach ( $names as $service => $name ) {
		if ( '' !== $o[ 'social_' . $service ] ) {
			$out[ $service ] = array( (string) $o[ 'social_' . $service ], $name );
		}
	}
	return $out;
}

/**
 * Print contact links as a list.
 *
 * @param string $list_class   List class.
 * @param bool   $with_address Include the address.
 */
function wp_itourtms_contact_list( string $list_class, bool $with_address = false ): void {
	$items = wp_itourtms_contact_items( $with_address );
	if ( ! $items ) {
		return;
	}
	echo '<ul class="' . esc_attr( $list_class ) . '">';
	foreach ( $items as $c ) {
		$label = 'address' === $c[1] ? nl2br( esc_html( $c[2] ) ) : esc_html( $c[2] );
		$inner = wp_itourtms_icon( $c[1] ) . '<span class="site-contact__label">' . $label . '</span>';
		if ( '' === $c[0] ) {
			echo '<li><span class="site-contact__item">' . $inner . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon via wp_kses, label escaped.
		} else {
			echo '<li><a class="site-contact__item" href="' . esc_url( $c[0] ) . '"' . ( 'whatsapp' === $c[1] ? ' target="_blank" rel="noopener"' : '' ) . '>' . $inner . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon via wp_kses, label escaped.
		}
	}
	echo '</ul>';
}

/**
 * Print social icons as a list.
 *
 * @param string $list_class List class.
 */
function wp_itourtms_social_list( string $list_class ): void {
	$items = wp_itourtms_social_items();
	if ( ! $items ) {
		return;
	}
	echo '<ul class="' . esc_attr( $list_class ) . '">';
	foreach ( $items as $service => $s ) {
		echo '<li><a href="' . esc_url( $s[0] ) . '" target="_blank" rel="noopener">' . wp_itourtms_icon( $service ) . '<span class="screen-reader-text">' . esc_html( $s[1] ) . '</span></a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon via wp_kses.
	}
	echo '</ul>';
}
