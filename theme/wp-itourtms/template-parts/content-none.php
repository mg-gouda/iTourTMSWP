<?php
/**
 * Nothing found.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="no-results">
	<h1 class="page-title"><?php esc_html_e( 'Nothing found', 'wp-itourtms' ); ?></h1>
	<p><?php esc_html_e( 'Nothing matches yet. Try a different search, or head back to the home page.', 'wp-itourtms' ); ?></p>
	<?php get_search_form(); ?>
</section>
