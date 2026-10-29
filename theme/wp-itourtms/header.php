<?php
/**
 * Site header.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'wp-itourtms' ); ?></a>
<?php
if ( wp_itourtms_option( 'topbar_enabled' ) ) {
	get_template_part( 'template-parts/topbar' );
}
?>
<header class="site-header">
	<div class="container site-header__inner">
		<div class="site-branding">
			<?php the_custom_logo(); ?>
			<?php if ( ! has_custom_logo() || wp_itourtms_option( 'header_show_title' ) ) : ?>
				<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<nav class="site-nav" aria-label="<?php esc_attr_e( 'Main menu', 'wp-itourtms' ); ?>">
				<button class="site-nav__toggle" type="button" aria-expanded="false" aria-controls="site-nav-menu" hidden>
					<span class="site-nav__bars" aria-hidden="true"></span><?php esc_html_e( 'Menu', 'wp-itourtms' ); ?>
				</button>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => '',
						'menu_id'        => 'site-nav-menu',
						'depth'          => 2,
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</div>
</header>
<main id="main" class="site-main" tabindex="-1">
