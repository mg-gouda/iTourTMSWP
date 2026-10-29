<?php
/**
 * Top bar above the header (Theme Options → Header → Top bar; details from Contact & social).
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

$wp_itourtms_o = wp_itourtms_options();
?>
<div class="site-topbar<?php echo $wp_itourtms_o['topbar_mobile'] ? '' : ' is-desktop-only'; ?>" style="<?php echo esc_attr( '--wp-itourtms-topbar-bg:' . $wp_itourtms_o['topbar_bg'] . ';--wp-itourtms-topbar-fg:' . $wp_itourtms_o['topbar_fg'] ); ?>">
	<div class="container site-topbar__inner">
		<?php
		if ( $wp_itourtms_o['topbar_show_contacts'] ) {
			wp_itourtms_contact_list( 'site-topbar__contacts' );
		}
		?>
		<?php if ( '' !== $wp_itourtms_o['topbar_text'] ) : ?>
			<p class="site-topbar__text">
				<?php
				echo wp_kses(
					$wp_itourtms_o['topbar_text'],
					array(
						'a'      => array( 'href' => array() ),
						'strong' => array(),
						'em'     => array(),
					)
				);
				?>
			</p>
		<?php endif; ?>
		<?php
		if ( $wp_itourtms_o['topbar_show_social'] ) {
			wp_itourtms_social_list( 'site-topbar__social' );
		}
		?>
	</div>
</div>
