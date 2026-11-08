<?php
/**
 * Top bar above the header (Theme Options → Top bar; details from Contact & social).
 * Designs: split, centered, minimal, announce.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

$wp_itourtms_o     = wp_itourtms_options();
$wp_itourtms_style = (string) $wp_itourtms_o['topbar_style'];
$wp_itourtms_rich  = array(
	'a'      => array( 'href' => array() ),
	'strong' => array(),
	'em'     => array(),
);
$wp_itourtms_vars  = 'announce' === $wp_itourtms_style
	? '--wp-itourtms-topbar-bg:var(--wp--preset--color--brand);--wp-itourtms-topbar-fg:var(--wp--preset--color--ink)'
	: '--wp-itourtms-topbar-bg:' . $wp_itourtms_o['topbar_bg'] . ';--wp-itourtms-topbar-fg:' . $wp_itourtms_o['topbar_fg'];
?>
<div class="site-topbar site-topbar--<?php echo esc_attr( $wp_itourtms_style ); ?><?php echo $wp_itourtms_o['topbar_mobile'] ? '' : ' is-desktop-only'; ?>" style="<?php echo esc_attr( $wp_itourtms_vars ); ?>">
	<div class="container site-topbar__inner">
		<?php
		if ( in_array( $wp_itourtms_style, array( 'split', 'minimal' ), true ) && $wp_itourtms_o['topbar_show_contacts'] ) {
			wp_itourtms_contact_list( 'site-topbar__contacts' );
		}
		?>
		<?php if ( 'minimal' !== $wp_itourtms_style && '' !== $wp_itourtms_o['topbar_text'] ) : ?>
			<p class="site-topbar__text">
				<?php echo wp_kses( $wp_itourtms_o['topbar_text'], $wp_itourtms_rich ); ?>
				<?php if ( 'announce' === $wp_itourtms_style && '' !== $wp_itourtms_o['topbar_link_text'] && '' !== $wp_itourtms_o['topbar_link_url'] ) : ?>
					<a class="site-topbar__link" href="<?php echo esc_url( $wp_itourtms_o['topbar_link_url'] ); ?>"><?php echo esc_html( $wp_itourtms_o['topbar_link_text'] ); ?> <span aria-hidden="true">→</span></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<?php
		if ( 'announce' !== $wp_itourtms_style && $wp_itourtms_o['topbar_show_social'] ) {
			wp_itourtms_social_list( 'site-topbar__social' );
		}
		?>
	</div>
</div>
