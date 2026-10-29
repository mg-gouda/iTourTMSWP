<?php
/**
 * A post in a list (blog, archive, search).
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry entry--summary' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="entry__thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'medium_large' ); ?></a>
	<?php endif; ?>
	<div>
		<?php the_title( '<h2 class="entry__title"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' ); ?>
		<?php if ( 'post' === get_post_type() ) : ?>
			<p class="entry__meta"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></p>
		<?php endif; ?>
		<div class="entry__excerpt"><?php the_excerpt(); ?></div>
	</div>
</article>
