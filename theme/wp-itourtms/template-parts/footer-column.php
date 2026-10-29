<?php
/**
 * One footer column; what it shows is set in Theme Options → Footer.
 *
 * @package WP_iTourTMS
 *
 * @var array{n:int} $args Column number.
 */

defined( 'ABSPATH' ) || exit;

$wp_itourtms_n     = (int) $args['n'];
$wp_itourtms_o     = wp_itourtms_options();
$wp_itourtms_type  = (string) $wp_itourtms_o[ "footer_col{$wp_itourtms_n}_type" ];
$wp_itourtms_title = (string) $wp_itourtms_o[ "footer_col{$wp_itourtms_n}_title" ];
$wp_itourtms_text  = (string) $wp_itourtms_o[ "footer_col{$wp_itourtms_n}_text" ];
$wp_itourtms_rich  = array(
	'a'      => array( 'href' => array() ),
	'strong' => array(),
	'em'     => array(),
	'br'     => array(),
);

if ( 'none' === $wp_itourtms_type ) {
	echo '<div class="site-footer__col" aria-hidden="true"></div>';
	return;
}
?>
<div class="site-footer__col site-footer__col--<?php echo esc_attr( $wp_itourtms_type ); ?>">
	<?php if ( 'widgets' === $wp_itourtms_type ) : ?>
		<?php dynamic_sidebar( 'footer-' . $wp_itourtms_n ); ?>
	<?php else : ?>
		<?php if ( 'about' === $wp_itourtms_type ) : ?>
			<div class="site-footer__brand">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a class="site-footer__site" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( '' !== $wp_itourtms_title ) : ?>
			<h2 class="site-footer__title"><?php echo esc_html( $wp_itourtms_title ); ?></h2>
		<?php endif; ?>
		<?php
		switch ( $wp_itourtms_type ) {
			case 'about':
			case 'text':
				$wp_itourtms_body = '' !== $wp_itourtms_text ? $wp_itourtms_text : ( 'about' === $wp_itourtms_type ? get_bloginfo( 'description' ) : '' );
				if ( '' !== $wp_itourtms_body ) {
					echo '<p class="site-footer__text">' . wp_kses( nl2br( $wp_itourtms_body ), $wp_itourtms_rich ) . '</p>';
				}
				break;
			case 'menu':
				if ( has_nav_menu( 'footer-' . $wp_itourtms_n ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'footer-' . $wp_itourtms_n,
							'container'      => 'nav',
							'menu_class'     => 'site-footer__menu',
							'depth'          => 1,
						)
					);
				}
				break;
			case 'contact':
				wp_itourtms_contact_list( 'site-footer__contacts', true );
				break;
			case 'social':
				wp_itourtms_social_list( 'site-footer__social' );
				break;
		}
		?>
	<?php endif; ?>
</div>
