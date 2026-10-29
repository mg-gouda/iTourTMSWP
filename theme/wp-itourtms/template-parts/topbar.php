<?php
/**
 * Top bar above the header (Theme Options → Options → Header → Top bar).
 * Social and WhatsApp/mail icons come from WordPress core's Social Icons block.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

$wp_itourtms_o    = wp_itourtms_options();
$wp_itourtms_icon = static function ( string $service ): string {
	if ( 'phone' === $service ) {
		return '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z"/></svg>';
	}
	return function_exists( 'block_core_social_link_get_icon' ) ? (string) block_core_social_link_get_icon( $service ) : '';
};
$wp_itourtms_svg  = array(
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
);

$wp_itourtms_contacts = array();
if ( '' !== $wp_itourtms_o['topbar_phone'] ) {
	$wp_itourtms_contacts[] = array( 'tel:' . preg_replace( '/[^0-9+]/', '', $wp_itourtms_o['topbar_phone'] ), 'phone', $wp_itourtms_o['topbar_phone'] );
}
if ( '' !== $wp_itourtms_o['topbar_email'] ) {
	$wp_itourtms_contacts[] = array( 'mailto:' . antispambot( $wp_itourtms_o['topbar_email'] ), 'mail', antispambot( $wp_itourtms_o['topbar_email'] ) );
}
if ( '' !== $wp_itourtms_o['topbar_whatsapp'] ) {
	$wp_itourtms_contacts[] = array( 'https://wa.me/' . preg_replace( '/\D/', '', $wp_itourtms_o['topbar_whatsapp'] ), 'whatsapp', __( 'WhatsApp', 'wp-itourtms' ) );
}
$wp_itourtms_social = array();
foreach ( array( 'facebook', 'instagram', 'x', 'tiktok', 'youtube', 'linkedin' ) as $wp_itourtms_service ) {
	if ( '' !== $wp_itourtms_o[ 'social_' . $wp_itourtms_service ] ) {
		$wp_itourtms_social[ $wp_itourtms_service ] = $wp_itourtms_o[ 'social_' . $wp_itourtms_service ];
	}
}
$wp_itourtms_names = array(
	'facebook'  => 'Facebook',
	'instagram' => 'Instagram',
	'x'         => 'X',
	'tiktok'    => 'TikTok',
	'youtube'   => 'YouTube',
	'linkedin'  => 'LinkedIn',
);
?>
<div class="site-topbar<?php echo $wp_itourtms_o['topbar_mobile'] ? '' : ' is-desktop-only'; ?>" style="<?php echo esc_attr( '--wp-itourtms-topbar-bg:' . $wp_itourtms_o['topbar_bg'] . ';--wp-itourtms-topbar-fg:' . $wp_itourtms_o['topbar_fg'] ); ?>">
	<div class="container site-topbar__inner">
		<?php if ( $wp_itourtms_contacts ) : ?>
			<ul class="site-topbar__contacts">
				<?php foreach ( $wp_itourtms_contacts as $wp_itourtms_c ) : ?>
					<li><a href="<?php echo esc_url( $wp_itourtms_c[0] ); ?>"<?php echo 'whatsapp' === $wp_itourtms_c[1] ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo wp_kses( $wp_itourtms_icon( $wp_itourtms_c[1] ), $wp_itourtms_svg ); ?><span class="site-topbar__label"><?php echo esc_html( $wp_itourtms_c[2] ); ?></span></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
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
		<?php if ( $wp_itourtms_social ) : ?>
			<ul class="site-topbar__social">
				<?php foreach ( $wp_itourtms_social as $wp_itourtms_service => $wp_itourtms_url ) : ?>
					<li><a href="<?php echo esc_url( $wp_itourtms_url ); ?>" target="_blank" rel="noopener">
						<?php echo wp_kses( $wp_itourtms_icon( $wp_itourtms_service ), $wp_itourtms_svg ); ?>
						<span class="screen-reader-text"><?php echo esc_html( $wp_itourtms_names[ $wp_itourtms_service ] ); ?></span>
					</a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>
