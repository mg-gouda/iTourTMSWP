<?php
/**
 * Page not found.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="container">
	<h1 class="page-title"><?php esc_html_e( 'Page not found', 'wp-itourtms' ); ?></h1>
	<p><?php esc_html_e( 'The page you were looking for has moved or no longer exists.', 'wp-itourtms' ); ?></p>
	<?php get_search_form(); ?>
	<p><a class="wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'wp-itourtms' ); ?></a></p>
</div>
<?php
get_footer();
