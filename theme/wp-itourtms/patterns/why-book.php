<?php
/**
 * Title: Why book with us
 * Slug: wp-itourtms/why-book
 * Categories: wp-itourtms, text, about
 * Keywords: benefits, reasons, trust
 * Description: A heading beside three short reasons to book, as a plain list.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Why travellers book with us', 'wp-itourtms' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%"><!-- wp:list {"className":"is-style-default","fontSize":"large"} -->
<ul class="wp-block-list is-style-default has-large-font-size"><!-- wp:list-item -->
<li><strong><?php esc_html_e( 'Local team.', 'wp-itourtms' ); ?></strong> <?php esc_html_e( 'Offices in the cities you visit, reachable on WhatsApp day and night.', 'wp-itourtms' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong><?php esc_html_e( 'Clear prices.', 'wp-itourtms' ); ?></strong> <?php esc_html_e( 'The price you see is the price you pay, with taxes included.', 'wp-itourtms' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong><?php esc_html_e( 'Flexible plans.', 'wp-itourtms' ); ?></strong> <?php esc_html_e( 'Free cancellation up to 24 hours before most trips.', 'wp-itourtms' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
