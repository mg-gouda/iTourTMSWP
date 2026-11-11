<?php
/**
 * Title: Reassurance strip
 * Slug: wp-itourtms/trust-strip
 * Categories: wp-itourtms, text
 * Keywords: trust, benefits, reasons, strip
 * Description: Three short reasons to book in one row with icons, under the home page search.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"wide","className":"wp-itourtms-trust","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
<div class="wp-block-group alignwide wp-itourtms-trust"><!-- wp:paragraph {"className":"wp-itourtms-trust__item is-icon-map"} -->
<p class="wp-itourtms-trust__item is-icon-map"><?php esc_html_e( 'Tours and day trips all over the country', 'wp-itourtms' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"wp-itourtms-trust__item is-icon-lock"} -->
<p class="wp-itourtms-trust__item is-icon-lock"><?php esc_html_e( 'Easy and secure online booking', 'wp-itourtms' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"wp-itourtms-trust__item is-icon-chat"} -->
<p class="wp-itourtms-trust__item is-icon-chat"><?php esc_html_e( 'Local travel experts to help you', 'wp-itourtms' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
