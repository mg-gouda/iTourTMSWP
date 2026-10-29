<?php
/**
 * Site footer.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;
?>
</main>
<footer class="site-footer">
	<div class="container">
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
</footer>
<?php wp_footer(); ?>
</body>
</html>
