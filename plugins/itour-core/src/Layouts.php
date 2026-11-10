<?php
/**
 * Product page layouts (owner request 10 Nov 2026): every tour and excursion picks one of three on its
 * "Layout" tab. A module hands over the page as parts; the layout decides the arrangement:
 *
 * - classic:   photo grid, title + facts, sticky section links, content beside a sticky booking card.
 * - immersive: full-width photo with the title on it, story flow, big timeline, floating "Book".
 * - compact:   bento tiles of the key facts, booking straight away, details in expandable sections.
 *
 * Parts: title, summary, images (attachment ids, first = main), facts (label => value), price
 * (['amount' => '$45', 'note' => 'per adult'] or null), content (HTML), sections (id => [ title, html ]),
 * book (['title' => …, 'html' => form or call to action]), notice (short line, e.g. cancellation summary).
 * Everything uses the theme's tokens (colours, heading font, radius), so all design presets apply.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

use ITour\Core\Admin\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Layout choice, template and rendering.
 */
final class Layouts {

	/**
	 * Meta key for the chosen layout.
	 */
	public const META = '_itour_layout';

	/**
	 * Post type => parts callback.
	 *
	 * @var array<string,callable>
	 */
	private static array $types = array();

	/**
	 * The layouts: key => [ name, description ].
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function options(): array {
		return array(
			'classic'   => array( __( 'Classic', 'itour-core' ), __( 'Photo grid, then the details beside a booking card that stays in view. The most familiar booking page; best for longer tours.', 'itour-core' ) ),
			'immersive' => array( __( 'Immersive', 'itour-core' ), __( 'A full-width photo with the title on it, then the story day by day. Best when you have strong photos.', 'itour-core' ) ),
			'compact'   => array( __( 'Compact', 'itour-core' ), __( 'Key facts as tiles, the booking form straight away, details folded below. Best for short day trips and phones.', 'itour-core' ) ),
		);
	}

	/**
	 * A product page type and how to build its parts.
	 *
	 * @param string   $type  Post type.
	 * @param callable $parts fn( int $post ): array.
	 */
	public static function register( string $type, callable $parts ): void {
		if ( ! self::$types ) {
			add_filter( 'template_include', array( self::class, 'template' ), 99 );
		}
		self::$types[ $type ] = $parts;
	}

	/**
	 * The chosen layout of a product.
	 *
	 * @param int $post Post id.
	 */
	public static function of( int $post ): string {
		$v = (string) get_post_meta( $post, self::META, true );
		return isset( self::options()[ $v ] ) ? $v : 'classic';
	}

	/**
	 * Our template for product pages.
	 *
	 * @param string $template Theme template.
	 */
	public static function template( $template ): string {
		return is_singular( array_keys( self::$types ) ) ? dirname( ITOUR_CORE_FILE ) . '/templates/product.php' : (string) $template;
	}

	/**
	 * Print the current product (called by templates/product.php inside the theme's header and footer).
	 */
	public static function render_current(): void {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! isset( self::$types[ $post->post_type ] ) ) {
			return;
		}
		$parts  = ( self::$types[ $post->post_type ] )( $post->ID ) + array(
			'title'    => get_the_title( $post ),
			'summary'  => has_excerpt( $post ) ? get_the_excerpt( $post ) : '',
			'images'   => array(),
			'facts'    => array(),
			'price'    => null,
			'content'  => '',
			'sections' => array(),
			'book'     => null,
			'notice'   => '',
		);
		$layout = self::of( $post->ID );
		wp_enqueue_style( 'itour-layouts', plugins_url( 'assets/layouts.css', ITOUR_CORE_FILE ), array( 'itour-products' ), ITOUR_CORE_VERSION );
		wp_enqueue_script( 'itour-layouts', plugins_url( 'assets/layouts.js', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION, true );
		Products::enqueue_style();
		echo '<article class="itl itl--' . esc_attr( $layout ) . '" id="post-' . (int) $post->ID . '">';
		$html = match ( $layout ) {
			'immersive' => self::immersive( $parts ),
			'compact'   => self::compact( $parts ),
			default     => self::classic( $parts ),
		};
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts are escaped by their makers, layout markup in the methods.
		echo '</article>';
	}

	/**
	 * Facts as a definition list.
	 *
	 * @param array<string,mixed> $p   Parts.
	 * @param string              $css Class names.
	 */
	private static function facts_html( array $p, string $css = 'itl-facts' ): string {
		if ( ! $p['facts'] ) {
			return '';
		}
		$h = '<dl class="' . esc_attr( $css ) . '">';
		foreach ( $p['facts'] as $k => $v ) {
			$h .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
		}
		return $h . '</dl>';
	}

	/**
	 * "From $45 per adult".
	 *
	 * @param array<string,mixed> $p Parts.
	 */
	private static function price_html( array $p ): string {
		if ( ! $p['price'] ) {
			return '';
		}
		return '<p class="itl-price"><span class="itl-price__from">' . esc_html__( 'From', 'itour-core' ) . '</span> <strong>' . esc_html( $p['price']['amount'] ) . '</strong> <span class="itl-price__note">' . esc_html( $p['price']['note'] ?? '' ) . '</span></p>';
	}

	/**
	 * Image tag for an attachment.
	 *
	 * @param int    $id    Attachment id.
	 * @param string $size  Size.
	 * @param bool   $eager First paint (no lazy loading).
	 */
	private static function img( int $id, string $size, bool $eager = false ): string {
		return wp_get_attachment_image(
			$id,
			$size,
			false,
			$eager ? array(
				'loading'       => 'eager',
				'fetchpriority' => 'high',
			) : array( 'loading' => 'lazy' )
		);
	}

	/**
	 * Sections as headed blocks.
	 *
	 * @param array<string,mixed> $p       Parts.
	 * @param string              $heading Heading tag.
	 */
	private static function sections_html( array $p, string $heading = 'h2' ): string {
		$h = '';
		foreach ( $p['sections'] as $id => list( $title, $html ) ) {
			$h .= '<section class="itl-section itl-section--' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '"><' . $heading . ' class="itl-section__title">' . esc_html( $title ) . '</' . $heading . '>' . $html . '</section>';
		}
		return $h;
	}

	/**
	 * The booking block.
	 *
	 * @param array<string,mixed> $p Parts.
	 */
	private static function book_html( array $p ): string {
		if ( ! $p['book'] ) {
			return '';
		}
		return '<div class="itl-book" id="book"><h2 class="itl-book__title">' . esc_html( $p['book']['title'] ) . '</h2>' . self::price_html( $p ) . ( '' !== $p['notice'] ? '<p class="itl-notice">' . esc_html( $p['notice'] ) . '</p>' : '' ) . $p['book']['html'] . '</div>';
	}

	/**
	 * Phone bar / floating button that jumps to the booking block.
	 *
	 * @param array<string,mixed> $p   Parts.
	 * @param string              $css Extra class.
	 */
	private static function jump( array $p, string $css ): string {
		if ( ! $p['book'] ) {
			return '';
		}
		$price = $p['price'] ? '<span class="itl-jump__price">' . esc_html__( 'From', 'itour-core' ) . ' <strong>' . esc_html( $p['price']['amount'] ) . '</strong></span>' : '';
		return '<div class="itl-jump ' . esc_attr( $css ) . ( 'itl-jump--float' === $css ? ' is-hidden' : '' ) . '" data-itl-jump>' . $price . '<a class="itl-jump__btn wp-element-button" href="#book">' . esc_html( $p['book']['title'] ) . '</a></div>';
	}

	/**
	 * Classic: gallery grid, head, sticky section links, main + sticky booking card.
	 *
	 * @param array<string,mixed> $p Parts.
	 */
	private static function classic( array $p ): string {
		$h      = '<div class="container">';
		$images = array_slice( $p['images'], 0, 5 );
		if ( $images ) {
			$h .= '<div class="itl-gallery itl-gallery--' . count( $images ) . '">';
			foreach ( $images as $i => $id ) {
				$h .= '<figure class="itl-gallery__item">' . self::img( (int) $id, 0 === $i ? 'large' : 'medium_large', 0 === $i ) . '</figure>';
			}
			if ( count( $p['images'] ) > 5 && isset( $p['sections']['photos'] ) ) {
				/* translators: %d: number of photos. */
				$h .= '<a class="itl-gallery__all" href="#photos">' . esc_html( sprintf( __( 'All %d photos', 'itour-core' ), count( $p['images'] ) ) ) . '</a>';
			}
			$h .= '</div>';
		}
		$h .= '<header class="itl-head"><h1 class="itl-title">' . esc_html( $p['title'] ) . '</h1>' . ( '' !== $p['summary'] ? '<p class="itl-summary">' . esc_html( $p['summary'] ) . '</p>' : '' ) . self::facts_html( $p, 'itl-chips' ) . '</header>';
		if ( count( $p['sections'] ) > 1 ) {
			$h .= '<nav class="itl-tabs" aria-label="' . esc_attr__( 'On this page', 'itour-core' ) . '" data-itl-tabs><ul>';
			if ( '' !== trim( wp_strip_all_tags( $p['content'] ) ) ) {
				$h .= '<li><a href="#overview">' . esc_html__( 'Overview', 'itour-core' ) . '</a></li>';
			}
			foreach ( $p['sections'] as $id => list( $title ) ) {
				$h .= '<li><a href="#' . esc_attr( $id ) . '">' . esc_html( $title ) . '</a></li>';
			}
			$h .= '</ul></nav>';
		}
		$h .= '<div class="itl-grid"><div class="itl-main">';
		if ( '' !== trim( wp_strip_all_tags( $p['content'] ) ) ) {
			$h .= '<section class="itl-section itl-content" id="overview">' . $p['content'] . '</section>';
		}
		$h .= self::sections_html( $p ) . '</div>';
		$h .= '<aside class="itl-aside">' . self::book_html( $p ) . '</aside></div></div>';
		return $h . self::jump( $p, 'itl-jump--bar' );
	}

	/**
	 * Immersive: full-bleed hero, narrow story column, booking at the end, floating button.
	 *
	 * @param array<string,mixed> $p Parts.
	 */
	private static function immersive( array $p ): string {
		$hero = $p['images'] ? self::img( (int) $p['images'][0], 'full', true ) : '';
		$h    = '<header class="itl-hero' . ( '' === $hero ? ' itl-hero--plain' : '' ) . '">' . ( '' !== $hero ? '<div class="itl-hero__media">' . $hero . '</div>' : '' );
		$h   .= '<div class="itl-hero__body container"><h1 class="itl-title">' . esc_html( $p['title'] ) . '</h1>' . ( '' !== $p['summary'] ? '<p class="itl-summary">' . esc_html( $p['summary'] ) . '</p>' : '' );
		$h   .= '<div class="itl-hero__actions">' . self::price_html( $p ) . ( $p['book'] ? '<a class="wp-element-button itl-hero__cta" href="#book">' . esc_html( $p['book']['title'] ) . '</a>' : '' ) . '</div></div></header>';
		$h   .= '<div class="itl-band">' . self::facts_html( $p, 'itl-facts itl-facts--band container' ) . '</div>';
		$h   .= '<div class="container itl-story">';
		if ( '' !== trim( wp_strip_all_tags( $p['content'] ) ) ) {
			$h .= '<section class="itl-section itl-content itl-lead" id="overview">' . $p['content'] . '</section>';
		}
		$h .= self::sections_html( $p ) . self::book_html( $p ) . '</div>';
		return $h . self::jump( $p, 'itl-jump--float' );
	}

	/**
	 * Compact: title, bento tiles, booking, folded sections.
	 *
	 * @param array<string,mixed> $p Parts.
	 */
	private static function compact( array $p ): string {
		$h  = '<div class="container">';
		$h .= '<header class="itl-head"><h1 class="itl-title">' . esc_html( $p['title'] ) . '</h1>' . ( '' !== $p['summary'] ? '<p class="itl-summary">' . esc_html( $p['summary'] ) . '</p>' : '' ) . '</header>';
		$h .= '<div class="itl-bento' . ( $p['images'] ? '' : ' itl-bento--nophoto' ) . '">';
		if ( $p['images'] ) {
			$h .= '<figure class="itl-tile itl-tile--photo">' . self::img( (int) $p['images'][0], 'large', true ) . '</figure>';
		}
		$h .= '<div class="itl-bento__side">';
		if ( $p['price'] ) {
			$h .= '<div class="itl-tile itl-tile--price"><div><span class="itl-tile__label">' . esc_html__( 'From', 'itour-core' ) . '</span><strong class="itl-tile__big">' . esc_html( $p['price']['amount'] ) . '</strong><span class="itl-tile__note">' . esc_html( $p['price']['note'] ?? '' ) . '</span></div>' . ( $p['book'] ? '<a class="wp-element-button" href="#book">' . esc_html( $p['book']['title'] ) . '</a>' : '' ) . '</div>';
		}
		$facts = array_slice( $p['facts'], 0, 6, true );
		$n     = 0;
		foreach ( $facts as $k => $v ) {
			++$n;
			$wide = count( $facts ) === $n && 1 === count( $facts ) % 2; // An odd last tile spans the row: no holes.
			$h   .= '<div class="itl-tile' . ( $wide ? ' itl-tile--wide' : '' ) . '"><span class="itl-tile__label">' . esc_html( $k ) . '</span><span class="itl-tile__value">' . esc_html( $v ) . '</span></div>';
		}
		if ( '' !== $p['notice'] ) {
			$h .= '<div class="itl-tile itl-tile--wide itl-tile--notice"><span class="itl-tile__label">' . esc_html__( 'Cancellation', 'itour-core' ) . '</span><span class="itl-tile__value">' . esc_html( $p['notice'] ) . '</span></div>';
		}
		$h .= '</div></div>';
		$h .= '<div class="itl-compact-grid"><div class="itl-main">';
		if ( '' !== trim( wp_strip_all_tags( $p['content'] ) ) ) {
			$h .= '<section class="itl-section itl-content" id="overview">' . $p['content'] . '</section>';
		}
		$h    .= '<div class="itl-folds">';
		$first = true;
		foreach ( $p['sections'] as $id => list( $title, $html ) ) {
			$h    .= '<details class="itl-fold" id="' . esc_attr( $id ) . '"' . ( $first ? ' open' : '' ) . '><summary><span>' . esc_html( $title ) . '</span></summary><div class="itl-fold__body">' . $html . '</div></details>';
			$first = false;
		}
		$h .= '</div></div><aside class="itl-aside">' . self::book_html( $p ) . '</aside></div></div>';
		return $h . self::jump( $p, 'itl-jump--bar' );
	}

	/**
	 * Shared parts every product has: photos, languages / suitability facts, included, FAQ, photos section.
	 *
	 * @param int $post Post id.
	 * @return array{images:int[],facts:array<string,string>,sections:array<string,array{0:string,1:string}>}
	 */
	public static function info_parts( int $post ): array {
		$i      = Products::info( $post );
		$images = array_values( array_unique( array_filter( array_merge( array( (int) get_post_thumbnail_id( $post ) ), array_map( 'intval', $i['gallery'] ) ) ) ) );
		$facts  = array();
		foreach ( array( 'itour_language' => __( 'Languages', 'itour-core' ), 'itour_suitability' => __( 'Suitable for', 'itour-core' ) ) as $tax => $label ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			$names = wp_get_post_terms( $post, $tax, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $names ) && $names ) {
				$facts[ $label ] = implode( ', ', $names );
			}
		}
		$sections = array();
		$cols     = '';
		foreach ( array( 'includes' => __( 'Included', 'itour-core' ), 'excludes' => __( 'Not included', 'itour-core' ) ) as $k => $title ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			$items = array_filter( array_map( 'trim', explode( "\n", $i[ $k ] ) ) );
			if ( $items ) {
				$cols .= '<div class="itour-info__list itour-info__list--' . esc_attr( $k ) . '"><h3>' . esc_html( $title ) . '</h3><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li></ul></div>';
			}
		}
		if ( '' !== $cols ) {
			$sections['included'] = array( __( 'What’s included', 'itour-core' ), '<div class="itour-info__lists">' . $cols . '</div>' );
		}
		if ( $i['faq'] ) {
			$faq = '<div class="itour-info__faq">';
			foreach ( $i['faq'] as $f ) {
				$faq .= '<details><summary>' . esc_html( $f['q'] ) . '</summary><p>' . nl2br( esc_html( $f['a'] ) ) . '</p></details>';
			}
			$sections['faq'] = array( __( 'Questions', 'itour-core' ), $faq . '</div>' );
		}
		if ( count( $images ) > 1 ) {
			$g = '<ul class="itour-info__gallery">';
			foreach ( $images as $id ) {
				$g .= '<li><a href="' . esc_url( (string) wp_get_attachment_image_url( $id, 'full' ) ) . '">' . wp_get_attachment_image( $id, 'medium_large', false, array( 'loading' => 'lazy' ) ) . '</a></li>';
			}
			$sections['photos'] = array( __( 'Photos', 'itour-core' ), $g . '</ul>' );
		}
		return array(
			'images'   => $images,
			'facts'    => $facts,
			'sections' => $sections,
		);
	}

	/**
	 * The post's own text, run through the content filters (blocks, embeds, shortcodes).
	 *
	 * @param int $post Post id.
	 */
	public static function content( int $post ): string {
		return (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $post ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
	}

	/**
	 * The "Layout" tab for product panels: three choices drawn as small page sketches.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function section(): array {
		return array(
			'layout' => array(
				'label'  => __( 'Layout', 'itour-core' ),
				'badge'  => static fn( int $post ): string => self::options()[ self::of( $post ) ][0],
				'render' => static function ( \WP_Post $post ): void {
					echo self::picker( 'layout', 'layout', self::options(), self::of( $post->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				},
				'save'   => static function ( int $post, array $in ): void {
					$v = sanitize_key( (string) ( $in['layout'] ?? '' ) );
					update_post_meta( $post, self::META, isset( self::options()[ $v ] ) ? $v : 'classic' );
				},
			),
		);
	}

	/**
	 * Layout picker: radio cards, each with a sketch of the page.
	 *
	 * @param string                                 $section Panel section.
	 * @param string                                 $field   Field.
	 * @param array<string,array{0:string,1:string}> $options Options.
	 * @param string                                 $value   Current.
	 */
	public static function picker( string $section, string $field, array $options, string $value ): string {
		wp_enqueue_style( 'itour-layouts-admin', plugins_url( 'assets/layouts-admin.css', ITOUR_CORE_FILE ), array( 'itour-panel' ), ITOUR_CORE_VERSION );
		$h = '<div class="itp-row itp-row--wide"><div class="itp-row-text"><p class="itp-help">' . esc_html__( 'How this page looks to visitors. Every layout shows the same information and uses your design (Theme Options); only the arrangement changes.', 'itour-core' ) . '</p></div><div class="itp-layouts" role="radiogroup" aria-label="' . esc_attr__( 'Layout', 'itour-core' ) . '">';
		foreach ( $options as $k => list( $name, $desc ) ) {
			$h .= '<label class="itp-layout"><input type="radio" name="' . esc_attr( Panel::name( $section, $field ) ) . '" value="' . esc_attr( $k ) . '"' . checked( $value, $k, false ) . '><span class="itp-layout__card"><span class="itp-layout__sketch itp-layout__sketch--' . esc_attr( $k ) . '" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span><span class="itp-layout__name">' . esc_html( $name ) . '</span><span class="itp-layout__desc">' . esc_html( $desc ) . '</span></span></label>';
		}
		return $h . '</div></div>';
	}
}
