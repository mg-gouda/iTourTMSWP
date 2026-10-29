<?php
/**
 * Site footer: optional columns (Theme Options → Footer), the footer menu and the bottom line.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

$wp_itourtms_cols = (int) wp_itourtms_option( 'footer_columns' );
?>
</main>
<footer class="site-footer" style="<?php echo esc_attr( '--wp-itourtms-footer-bg:' . wp_itourtms_option( 'footer_bg' ) . ';--wp-itourtms-footer-fg:' . wp_itourtms_option( 'footer_fg' ) ); ?>">
	<div class="container">
		<?php if ( $wp_itourtms_cols ) : ?>
			<div class="site-footer__cols site-footer__cols--<?php echo (int) $wp_itourtms_cols; ?>">
				<?php
				for ( $wp_itourtms_n = 1; $wp_itourtms_n <= $wp_itourtms_cols; $wp_itourtms_n++ ) {
					get_template_part( 'template-parts/footer-column', null, array( 'n' => $wp_itourtms_n ) );
				}
				?>
			</div>
		<?php endif; ?>
		<div class="site-footer__bottom">
			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<nav aria-label="<?php esc_attr_e( 'Footer menu', 'wp-itourtms' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'container'      => '',
							'depth'          => 1,
						)
					);
					?>
				</nav>
			<?php endif; ?>
			<?php $wp_itourtms_footer = wp_itourtms_footer_text(); ?>
			<?php if ( '' !== $wp_itourtms_footer ) : ?>
				<p class="site-footer__copy"><?php echo $wp_itourtms_footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses() in wp_itourtms_footer_text(). ?></p>
			<?php endif; ?>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
