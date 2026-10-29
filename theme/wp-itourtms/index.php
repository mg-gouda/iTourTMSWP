<?php
/**
 * Main template: posts, pages, archives and search results.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_singular() ) {
	// Single posts and pages use WordPress's layout classes, so wide and full-width blocks work.
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/content', 'single' );
	}
	get_footer();
	return;
}
?>
<div class="container">
	<?php if ( ( is_home() && ! is_front_page() ) || is_archive() || is_search() ) : ?>
		<header class="page-header">
			<h1 class="page-title">
				<?php
				if ( is_search() ) {
					/* translators: %s: search query. */
					printf( esc_html__( 'Search results for “%s”', 'wp-itourtms' ), esc_html( get_search_query() ) );
				} elseif ( is_home() ) {
					single_post_title();
				} else {
					the_archive_title();
				}
				?>
			</h1>
			<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
		</header>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content', 'summary' );
		endwhile;
		the_posts_pagination();
		?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
