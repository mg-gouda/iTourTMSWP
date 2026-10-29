<?php
/**
 * Fallback template: posts, pages and archives.
 *
 * @package ITour\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="container">
	<?php if ( have_posts() ) : ?>
		<?php if ( ! is_singular() ) : ?>
			<h1 class="page-title"><?php echo esc_html( is_home() ? single_post_title( '', false ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		<?php endif; ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( is_singular() ? 'entry' : 'entry entry--summary' ); ?>>
				<?php if ( is_singular() ) : ?>
					<h1 class="entry__title"><?php the_title(); ?></h1>
					<div class="entry__content"><?php the_content(); ?></div>
				<?php else : ?>
					<h2 class="entry__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="entry__excerpt"><?php the_excerpt(); ?></div>
				<?php endif; ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<h1 class="page-title"><?php esc_html_e( 'Nothing found', 'itour' ); ?></h1>
		<p><?php esc_html_e( 'Try a search, or head back to the home page.', 'itour' ); ?></p>
		<?php get_search_form(); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
