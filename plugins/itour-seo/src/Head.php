<?php
/**
 * Page head output: document title, meta description, robots, canonical, Open Graph / X cards and the
 * structured data graph. Stays quiet while Yoast SEO is active, so nothing is printed twice.
 *
 * @package ITour\Seo
 */

namespace ITour\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Head tags.
 */
final class Head {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		if ( Seo::yoast_active() ) {
			return;
		}
		add_filter( 'pre_get_document_title', array( $this, 'title' ), 20 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'get_canonical_url', array( $this, 'canonical' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'tags' ), 1 );
		add_action( 'wp_head', array( Schema::class, 'print' ), 2 );
	}

	/**
	 * The current page's search title and description: [ title, description ].
	 *
	 * @return array{0:string,1:string}
	 */
	public static function current(): array {
		$s = Seo::settings();
		if ( is_front_page() && ! is_singular() ) { // Latest posts on the front page.
			return array( Seo::fill( (string) $s['home_title'] ), Seo::fill( (string) $s['home_desc'] ) );
		}
		if ( is_singular() ) {
			$id = (int) get_queried_object_id();
			if ( is_front_page() ) {
				$own = Seo::item( $id );
				return array( '' !== $own['title'] ? Seo::fill( $own['title'], $id ) : Seo::fill( (string) $s['home_title'] ), '' !== $own['desc'] ? Seo::fill( $own['desc'], $id ) : Seo::fill( (string) $s['home_desc'] ) );
			}
			return array( Seo::title( $id ), Seo::desc( $id ) );
		}
		if ( is_search() ) {
			/* translators: %s: search terms. */
			return array( Seo::fill( '{title} {sep} {site}', 0, sprintf( __( 'Search results for “%s”', 'itour-seo' ), get_search_query() ) ), '' );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			return array( Seo::fill( '{title} {sep} {site}', 0, (string) single_term_title( '', false ) ), $term instanceof \WP_Term ? wp_strip_all_tags( term_description( $term->term_id ) ) : '' );
		}
		if ( is_post_type_archive() || is_author() || is_date() ) {
			return array( Seo::fill( '{title} {sep} {site}', 0, wp_strip_all_tags( get_the_archive_title() ) ), '' );
		}
		if ( is_404() ) {
			return array( Seo::fill( '{title} {sep} {site}', 0, __( 'Page not found', 'itour-seo' ) ), '' );
		}
		return array( '', '' );
	}

	/**
	 * Document title.
	 *
	 * @param string $title Title so far.
	 */
	public function title( $title ): string {
		$t = self::current()[0];
		return '' !== $t ? $t : (string) $title;
	}

	/**
	 * Robots directives: per item, per content type, and the indexing rules.
	 *
	 * @param array<string,bool|string> $robots Directives.
	 * @return array<string,bool|string>
	 */
	public function robots( $robots ): array {
		$s       = Seo::settings();
		$noindex = is_search();
		if ( is_singular() ) {
			$id      = (int) get_queried_object_id();
			$v       = Seo::item( $id );
			$noindex = '1' === $v['noindex'] || in_array( get_post_type( $id ), (array) $s['hidden'], true );
			if ( '1' === $v['nofollow'] ) {
				$robots['nofollow'] = true;
			}
		} elseif ( ( is_tag() && $s['noindex_tags'] ) || ( is_category() && $s['noindex_cats'] ) || ( is_author() && $s['noindex_authors'] ) || ( is_date() && $s['noindex_dates'] ) ) {
			$noindex = true;
		}
		if ( $noindex ) {
			$robots['noindex'] = true;
			$robots['follow']  = ! isset( $robots['nofollow'] );
			unset( $robots['max-image-preview'] );
		} elseif ( ! isset( $robots['noindex'] ) ) {
			$robots['max-image-preview'] = 'large'; // Large photos in Google results and Discover.
		}
		return $robots;
	}

	/**
	 * Own canonical address when set.
	 *
	 * @param string   $url  Canonical so far.
	 * @param \WP_Post $post Post.
	 */
	public function canonical( $url, $post ): string {
		$own = Seo::item( (int) $post->ID )['canonical'];
		return '' !== $own ? $own : (string) $url;
	}

	/**
	 * Description, Open Graph and X cards.
	 */
	public function tags(): void {
		list( $title, $desc ) = self::current();
		$s                    = Seo::settings();
		$tags                 = array();
		$image                = (int) $s['share_image'];
		$type                 = 'website';
		$url                  = is_singular() ? (string) wp_get_canonical_url() : ( is_front_page() ? home_url( '/' ) : '' );
		$og_title             = $title;
		$og_desc              = $desc;
		if ( is_singular() ) {
			$id       = (int) get_queried_object_id();
			$v        = Seo::item( $id );
			$og_title = '' !== $v['og_title'] ? $v['og_title'] : $title;
			$og_desc  = '' !== $v['og_desc'] ? $v['og_desc'] : $desc;
			$image    = (int) ( $v['og_image'] ? $v['og_image'] : ( get_post_thumbnail_id( $id ) ? get_post_thumbnail_id( $id ) : $image ) );
			$type     = 'post' === get_post_type( $id ) ? 'article' : 'website';
		}
		if ( '' !== $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		$tags['og:locale']      = get_locale();
		$tags['og:type']        = $type;
		$tags['og:site_name']   = Seo::fill( '{site}' );
		$tags['og:title']       = '' !== $og_title ? $og_title : wp_get_document_title();
		$tags['og:url']         = $url;
		$tags['og:description'] = $og_desc;
		if ( $image ) {
			$src = wp_get_attachment_image_src( $image, 'large' );
			if ( $src ) {
				$tags['og:image']        = $src[0];
				$tags['og:image:width']  = (string) $src[1];
				$tags['og:image:height'] = (string) $src[2];
				$tags['og:image:alt']    = (string) get_post_meta( $image, '_wp_attachment_image_alt', true );
			}
		}
		$tags['twitter:card'] = isset( $tags['og:image'] ) ? 'summary_large_image' : 'summary';
		foreach ( $tags as $k => $v ) {
			if ( '' !== (string) $v ) {
				printf( '<meta %1$s="%2$s" content="%3$s">' . "\n", 0 === strpos( $k, 'twitter:' ) ? 'name' : 'property', esc_attr( $k ), esc_attr( (string) $v ) );
			}
		}
	}
}
