<?php
/**
 * SEO data: site settings, per-item values, and the title / description that every output uses.
 *
 * Per item (post meta `_itour_seo`): keyphrase, title, desc, score, og_title, og_desc, og_image, noindex,
 * nofollow, canonical. `_itour_noindex` mirrors the noindex flag so sitemaps can query it.
 * Templates: {title} {sep} {site} {tagline} {excerpt} {price}.
 *
 * @package ITour\Seo
 */

namespace ITour\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, values, templates.
 */
final class Seo {

	/**
	 * Site settings option.
	 */
	public const OPTION = 'itour_seo';

	/**
	 * Per-item meta key.
	 */
	public const META = '_itour_seo';

	/**
	 * Public content types the plugin handles: type => label.
	 *
	 * @return array<string,string>
	 */
	public static function types(): array {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type => $obj ) {
			if ( 'attachment' !== $type ) {
				$out[ $type ] = (string) $obj->labels->name;
			}
		}
		return $out;
	}

	/**
	 * Site settings with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function settings(): array {
		$s = get_option( self::OPTION, array() );
		return array_merge(
			array(
				'sep'                 => '–',
				'home_title'          => '{site} {sep} {tagline}',
				'home_desc'           => '{tagline}',
				'titles'              => array(),
				'descs'               => array(),
				'hidden'              => array(), // Types kept out of search engines.
				'org_type'            => 'TravelAgency',
				'org_name'            => '',
				'org_logo'            => 0,
				'org_phone'           => '',
				'org_email'           => '',
				'org_socials'         => '',
				'share_image'         => 0,
				'noindex_tags'        => 1,
				'noindex_cats'        => 1,
				'noindex_authors'     => 1,
				'noindex_dates'       => 1,
				'feeds_noindex'       => 1,
				'gone_410'            => 1,
				'attachment_redirect' => 1,
			),
			is_array( $s ) ? $s : array()
		);
	}

	/**
	 * One item's values with defaults.
	 *
	 * @param int $post Post id.
	 * @return array<string,string>
	 */
	public static function item( int $post ): array {
		$m = get_post_meta( $post, self::META, true );
		return array_merge(
			array_fill_keys( array( 'keyphrase', 'title', 'desc', 'score', 'og_title', 'og_desc', 'og_image', 'noindex', 'nofollow', 'canonical' ), '' ),
			is_array( $m ) ? array_map( 'strval', $m ) : array()
		);
	}

	/**
	 * Merge values into an item.
	 *
	 * @param int                  $post   Post id.
	 * @param array<string,string> $values Values.
	 */
	public static function put( int $post, array $values ): void {
		$v = array_merge( self::item( $post ), $values );
		update_post_meta( $post, self::META, $v );
		if ( '1' === $v['noindex'] ) {
			update_post_meta( $post, '_itour_noindex', '1' );
		} else {
			delete_post_meta( $post, '_itour_noindex' );
		}
	}

	/**
	 * Default title template for a type.
	 *
	 * @param string $type Post type.
	 */
	public static function title_template( string $type ): string {
		$t = (string) ( self::settings()['titles'][ $type ] ?? '' );
		return '' !== $t ? $t : '{title} {sep} {site}';
	}

	/**
	 * Default description template for a type.
	 *
	 * @param string $type Post type.
	 */
	public static function desc_template( string $type ): string {
		$t = (string) ( self::settings()['descs'][ $type ] ?? '' );
		return '' !== $t ? $t : '{excerpt}';
	}

	/**
	 * Fill a template.
	 *
	 * @param string $template Template.
	 * @param int    $post     Post id (0 = site-level).
	 * @param string $title    Title of a non-post page (archive, search).
	 */
	public static function fill( string $template, int $post = 0, string $title = '' ): string {
		$vars = array(
			'{sep}'     => (string) self::settings()['sep'],
			'{site}'    => (string) get_bloginfo( 'name' ),
			'{tagline}' => (string) get_bloginfo( 'description' ),
			'{title}'   => $post ? (string) get_the_title( $post ) : $title,
			'{excerpt}' => $post ? self::excerpt( $post ) : '',
			'{price}'   => '',
		);
		$from = $post ? (float) get_post_meta( $post, '_itour_price_from', true ) : 0;
		if ( $from && function_exists( 'itour' ) ) {
			$vars['{price}'] = itour()->currencies()->format( $from );
		}
		$out = strtr( $template, $vars );
		$out = (string) preg_replace( '/\s+/u', ' ', $out );
		// WordPress hands titles and the site name over HTML-encoded ("Nile &amp; Sea"); every output escapes again.
		return html_entity_decode( trim( $out, " \t\n\r\0\x0B" . $vars['{sep}'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * A post's summary: its excerpt, else the start of its text (about 155 characters).
	 *
	 * @param int $post Post id.
	 */
	public static function excerpt( int $post ): string {
		$p    = get_post( $post );
		$text = $p ? ( '' !== trim( $p->post_excerpt ) ? $p->post_excerpt : $p->post_content ) : '';
		$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( (string) preg_replace( '/<!--.*?-->/s', '', $text ) ) ) ) );
		if ( mb_strlen( $text ) > 156 ) {
			$text = rtrim( mb_substr( $text, 0, 155 ), ' ,.;:' );
			$text = mb_substr( $text, 0, (int) mb_strrpos( $text, ' ' ) ) . '…';
		}
		return $text;
	}

	/**
	 * The search title of a post.
	 *
	 * @param int $post Post id.
	 */
	public static function title( int $post ): string {
		$own = self::item( $post )['title'];
		return self::fill( '' !== $own ? $own : self::title_template( (string) get_post_type( $post ) ), $post );
	}

	/**
	 * The meta description of a post.
	 *
	 * @param int $post Post id.
	 */
	public static function desc( int $post ): string {
		$own = self::item( $post )['desc'];
		return self::fill( '' !== $own ? $own : self::desc_template( (string) get_post_type( $post ) ), $post );
	}

	/**
	 * Whether Yoast SEO is running (then this plugin stays quiet in the page head).
	 */
	public static function yoast_active(): bool {
		return defined( 'WPSEO_VERSION' );
	}
}
