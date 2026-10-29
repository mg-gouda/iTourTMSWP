<?php
/**
 * Title: Hero banner
 * Slug: wp-itourtms/hero
 * Categories: wp-itourtms, banner, featured
 * Keywords: hero, cover, banner, header
 * Description: A full-width banner with a headline, short intro and two buttons. Add a photo with “Replace” on the Cover block.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:cover {"overlayColor":"ink","isUserOverlayColor":true,"minHeight":520,"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var(--wp--style--root--padding-left)","right":"var(--wp--style--root--padding-right)"}}}} -->
<div class="wp-block-cover alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--style--root--padding-right);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--style--root--padding-left);min-height:520px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group"><!-- wp:group {"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1,"textColor":"base"} -->
<h1 class="wp-block-heading has-base-color has-text-color"><?php esc_html_e( 'Egypt, planned by people who live here', 'wp-itourtms' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"large"} -->
<p class="has-base-color has-text-color has-large-font-size"><?php esc_html_e( 'Day trips, Nile cruises and private transfers with local guides, clear prices and free cancellation on most bookings.', 'wp-itourtms' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Find a trip', 'wp-itourtms' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"textColor":"base","className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-base-color has-text-color wp-element-button"><?php esc_html_e( 'Talk to us', 'wp-itourtms' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
