<?php
/**
 * Title: Call to action
 * Slug: wp-itourtms/call-to-action
 * Categories: wp-itourtms, call-to-action
 * Keywords: contact, cta, enquiry
 * Description: A brand-coloured band with a short question and a contact button.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"full","backgroundColor":"brand","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-brand-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading {"textColor":"ink"} -->
<h2 class="wp-block-heading has-ink-color has-text-color"><?php esc_html_e( 'Not sure where to start?', 'wp-itourtms' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"ink","textColor":"base"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-base-color has-ink-background-color has-text-color has-background wp-element-button"><?php esc_html_e( 'Plan my trip', 'wp-itourtms' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
