<?php
/**
 * Page not found.
 *
 * @package ITour\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="container">
	<h1 class="page-title"><?php esc_html_e( 'Page not found', 'itour' ); ?></h1>
	<p><?php esc_html_e( 'The page you were looking for has moved or no longer exists.', 'itour' ); ?></p>
	<p><a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'itour' ); ?></a></p>
</div>
<?php
get_footer();
