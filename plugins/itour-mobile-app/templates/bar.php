<?php
/**
 * Phone tab bar + "More" sheet.
 *
 * @package ITour\MobileApp
 *
 * @var array<string,array{0:string,1:string,2:string}> $tabs   Tabs.
 * @var string                                           $active Active tab key.
 */

defined( 'ABSPATH' ) || exit;

$itour_m_account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : get_edit_profile_url();
$itour_m_contact = function_exists( 'wp_itourtms_contact_items' ) ? wp_itourtms_contact_items() : array();
$itour_m_menu    = array();
$itour_m_locs    = get_nav_menu_locations();
if ( ! empty( $itour_m_locs['primary'] ) ) {
	foreach ( (array) wp_get_nav_menu_items( $itour_m_locs['primary'] ) as $itour_m_item ) {
		if ( is_object( $itour_m_item ) && ! (int) $itour_m_item->menu_item_parent ) {
			$itour_m_menu[] = array( (string) $itour_m_item->url, (string) $itour_m_item->title );
		}
	}
}
$itour_m_chev = itour_mobile_icon( 'chev' );
?>
<nav class="itm-bar" aria-label="<?php esc_attr_e( 'Quick navigation', 'itour-mobile-app' ); ?>">
	<?php foreach ( $tabs as $itour_m_key => $itour_m_tab ) : ?>
		<a class="itm-tab" href="<?php echo esc_url( $itour_m_tab[0] ); ?>"<?php echo $active === $itour_m_key ? ' aria-current="page"' : ''; ?>>
			<?php echo itour_mobile_icon( $itour_m_tab[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span><?php echo esc_html( $itour_m_tab[2] ); ?></span>
		</a>
	<?php endforeach; ?>
	<button type="button" class="itm-tab" data-itm-open aria-haspopup="dialog">
		<?php echo itour_mobile_icon( 'more' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		<span><?php esc_html_e( 'More', 'itour-mobile-app' ); ?></span>
	</button>
</nav>

<dialog class="itm-sheet" aria-labelledby="itm-sheet-title">
	<div class="itm-sheet__panel">
		<div class="itm-sheet__head">
			<h2 id="itm-sheet-title"><?php esc_html_e( 'More', 'itour-mobile-app' ); ?></h2>
			<button type="button" class="itm-sheet__x" data-itm-close aria-label="<?php esc_attr_e( 'Close', 'itour-mobile-app' ); ?>"><?php echo itour_mobile_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
		</div>

		<section class="itm-group">
			<?php if ( is_user_logged_in() ) : ?>
				<p class="itm-group__title"><?php echo esc_html( wp_get_current_user()->display_name ); ?></p>
				<a class="itm-row" href="<?php echo esc_url( $itour_m_account ); ?>"><?php esc_html_e( 'My account & bookings', 'itour-mobile-app' ); ?><?php echo $itour_m_chev; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
				<a class="itm-row" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out', 'itour-mobile-app' ); ?><?php echo $itour_m_chev; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
			<?php else : ?>
				<a class="itm-signin wp-element-button" href="<?php echo esc_url( wp_login_url() ); ?>" data-itour-auth data-itm-close><?php esc_html_e( 'Sign in or create an account', 'itour-mobile-app' ); ?></a>
			<?php endif; ?>
		</section>

		<?php if ( $itour_m_contact ) : ?>
			<section class="itm-contact" aria-label="<?php esc_attr_e( 'Contact us', 'itour-mobile-app' ); ?>">
				<?php
				$itour_m_names = array(
					'phone'    => __( 'Call', 'itour-mobile-app' ),
					'mail'     => __( 'Email', 'itour-mobile-app' ),
					'whatsapp' => 'WhatsApp',
				);
				foreach ( $itour_m_contact as $itour_m_c ) :
					?>
					<a href="<?php echo esc_url( $itour_m_c[0] ); ?>"<?php echo 'whatsapp' === $itour_m_c[1] ? ' target="_blank" rel="noopener"' : ''; ?>>
						<?php echo itour_mobile_icon( $itour_m_c[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<span><?php echo esc_html( $itour_m_names[ $itour_m_c[1] ] ?? $itour_m_c[2] ); ?></span>
					</a>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php if ( $itour_m_menu ) : ?>
			<nav class="itm-group" aria-label="<?php esc_attr_e( 'Main menu', 'itour-mobile-app' ); ?>">
				<?php foreach ( $itour_m_menu as $itour_m_link ) : ?>
					<a class="itm-row" href="<?php echo esc_url( $itour_m_link[0] ); ?>"><?php echo esc_html( $itour_m_link[1] ); ?><?php echo $itour_m_chev; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>
</dialog>
