<?php
/**
 * Indexing rules (from Rehlla's search-index clean-up, as settings): feeds stay readable but out of search
 * results, removed pages answer "410 Gone", attachment pages go to the file. The WordPress sitemap
 * (/wp-sitemap.xml) leaves out hidden pages, hidden content types and noindexed archives, and gets dates.
 *
 * @package ITour\Seo
 */

namespace ITour\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Rules + sitemap filters.
 */
final class Indexing {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'template_redirect', array( $this, 'redirects' ), 1 );
		add_action( 'init', array( $this, 'feeds' ) );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'sitemap_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'sitemap_taxonomies' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'sitemap_users' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'sitemap_hidden' ) );
		add_filter( 'wp_sitemaps_posts_entry', array( $this, 'sitemap_dates' ), 10, 2 );
	}

	/**
	 * Feeds: header noindex, and no per-tag/category feed links in the page head.
	 */
	public function feeds(): void {
		if ( Seo::settings()['feeds_noindex'] ) {
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
	}

	/**
	 * 410 for removed pages, attachment pages to their file, X-Robots-Tag on feeds.
	 */
	public function redirects(): void {
		$s = Seo::settings();
		if ( is_feed() && $s['feeds_noindex'] && ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, follow', true );
			return;
		}
		if ( is_attachment() && $s['attachment_redirect'] ) {
			$file = wp_get_attachment_url( (int) get_queried_object_id() );
			if ( $file ) {
				wp_safe_redirect( $file, 301 );
				exit;
			}
		}
		if ( is_404() && $s['gone_410'] ) {
			$path = trim( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$slug = basename( $path );
			if ( '' !== $slug && get_posts( array( 'name' => $slug . '__trashed', 'post_type' => 'any', 'post_status' => 'trash', 'numberposts' => 1, 'fields' => 'ids' ) ) ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
				status_header( 410 ); // Deleted for good: Google drops it faster than a 404.
			}
		}
	}

	/**
	 * Hidden content types leave the sitemap.
	 *
	 * @param array<string,\WP_Post_Type> $types Types.
	 * @return array<string,\WP_Post_Type>
	 */
	public function sitemap_types( $types ): array {
		return array_diff_key( (array) $types, array_flip( (array) Seo::settings()['hidden'] ) );
	}

	/**
	 * Noindexed tag / category archives leave the sitemap.
	 *
	 * @param array<string,\WP_Taxonomy> $taxes Taxonomies.
	 * @return array<string,\WP_Taxonomy>
	 */
	public function sitemap_taxonomies( $taxes ): array {
		$s = Seo::settings();
		if ( $s['noindex_tags'] ) {
			unset( $taxes['post_tag'] );
		}
		if ( $s['noindex_cats'] ) {
			unset( $taxes['category'] );
		}
		return (array) $taxes;
	}

	/**
	 * Author pages leave the sitemap when noindexed.
	 *
	 * @param \WP_Sitemaps_Provider|null $provider Provider.
	 * @param string                     $name     Provider name.
	 * @return \WP_Sitemaps_Provider|null
	 */
	public function sitemap_users( $provider, $name ) {
		return 'users' === $name && Seo::settings()['noindex_authors'] ? null : $provider;
	}

	/**
	 * Pages marked "hide from search results" leave the sitemap.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<string,mixed>
	 */
	public function sitemap_hidden( $args ): array {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sitemap pages are cached by crawlers.
			array(
				'key'     => '_itour_noindex',
				'compare' => 'NOT EXISTS',
			),
		);
		$booking            = (int) get_option( 'itour_booking_product' ); // The hidden product that carries checkout lines.
		if ( $booking ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), array( $booking ) );
		}
		return (array) $args;
	}

	/**
	 * Last-modified dates in the sitemap.
	 *
	 * @param array<string,string> $entry Entry.
	 * @param \WP_Post             $post  Post.
	 * @return array<string,string>
	 */
	public function sitemap_dates( $entry, $post ): array {
		$entry['lastmod'] = (string) get_post_modified_time( 'c', true, $post );
		return (array) $entry;
	}
}
