<?php
/**
 * Site header. Design, colours and button come from Theme Options → Header.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

$wp_itourtms_o      = wp_itourtms_options();
$wp_itourtms_layout = (string) $wp_itourtms_o['header_layout'];
$wp_itourtms_logo   = wp_itourtms_logo();
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
if ( $wp_itourtms_o['topbar_enabled'] ) {
	get_template_part( 'template-parts/topbar' );
}
?>
<header class="site-header site-header--<?php echo esc_attr( $wp_itourtms_layout ); ?>" style="<?php echo esc_attr( '--wp-itourtms-header-bg:' . $wp_itourtms_o['header_bg'] . ';--wp-itourtms-header-fg:' . $wp_itourtms_o['header_fg'] . ';--wp-itourtms-logo-h:' . (int) $wp_itourtms_o['logo_height'] . 'px;--wp-itourtms-logo-h-m:' . (int) $wp_itourtms_o['logo_mobile_height'] . 'px' ); ?>">
	<div class="container site-header__inner">
		<div class="site-branding">
			<?php echo $wp_itourtms_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* in wp_itourtms_logo() / core get_custom_logo(). ?>
			<?php if ( '' === $wp_itourtms_logo || $wp_itourtms_o['header_show_title'] ) : ?>
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
		<?php if ( '' !== $wp_itourtms_o['header_cta_text'] && '' !== $wp_itourtms_o['header_cta_url'] ) : ?>
			<a class="site-header__cta wp-element-button" href="<?php echo esc_url( $wp_itourtms_o['header_cta_url'] ); ?>"><?php echo esc_html( $wp_itourtms_o['header_cta_text'] ); ?></a>
		<?php endif; ?>
	</div>
</header>
<main id="main" class="site-main" tabindex="-1">
