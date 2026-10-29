<?php
/**
 * A single post or page.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
	<?php if ( ! is_front_page() ) : ?>
		<header class="entry__header has-global-padding is-layout-constrained">
			<?php the_title( '<h1 class="entry__title">', '</h1>' ); ?>
			<?php if ( 'post' === get_post_type() && wp_itourtms_option( 'blog_show_date' ) ) : ?>
				<p class="entry__meta"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></p>
			<?php endif; ?>
			<?php if ( 'post' === get_post_type() && has_post_thumbnail() && wp_itourtms_option( 'blog_single_image' ) ) : ?>
				<figure class="entry__image alignwide"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>
		</header>
	<?php endif; ?>
	<div class="entry__content has-global-padding is-layout-constrained">
		<?php
		the_content();
		wp_link_pages(
			array(
				'before' => '<nav class="page-links" aria-label="' . esc_attr__( 'Page', 'wp-itourtms' ) . '">',
				'after'  => '</nav>',
			)
		);
		?>
	</div>
	<?php if ( has_tag() ) : ?>
		<footer class="entry__footer has-global-padding is-layout-constrained"><?php the_tags( '<p class="entry__tags">', ' ', '</p>' ); ?></footer>
	<?php endif; ?>
</article>
<?php
if ( comments_open() || get_comments_number() ) {
	comments_template();
}
