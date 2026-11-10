<?php
/**
 * SEO tabs on every public content type: SEO (keyphrase, title, description, Google preview, checklist),
 * Social (sharing title, description, image) and Advanced (noindex, nofollow, canonical). Added to the
 * product panels (tours, excursions); posts and pages get their own "SEO" panel. Saves as you go.
 *
 * @package ITour\Seo
 */

namespace ITour\Seo;

use ITour\Core\Admin\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Panel sections.
 */
final class Editor {

	/**
	 * Hook after the product panels are registered.
	 */
	public function hooks(): void {
		add_action(
			'init',
			static function (): void {
				foreach ( array_keys( Seo::types() ) as $type ) {
					Panel::extend( $type, __( 'SEO', 'itour-seo' ), self::sections() );
				}
			},
			30
		);
	}

	/**
	 * The three tabs.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function sections(): array {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Panel helpers escape.
		return array(
			'seo'          => array(
				'label'  => __( 'SEO', 'itour-seo' ),
				'badge'  => static fn( int $post ): string => Seo::item( $post )['score'],
				'render' => static function ( \WP_Post $post ): void {
					$v = Seo::item( $post->ID );
					wp_enqueue_script( 'itour-seo-editor', plugins_url( 'assets/seo-editor.js', ITOUR_SEO_FILE ), array(), '0.1.0', true );
					wp_enqueue_style( 'itour-seo-editor', plugins_url( 'assets/seo-editor.css', ITOUR_SEO_FILE ), array( 'itour-panel' ), '0.1.0' );
					wp_localize_script(
						'itour-seo-editor',
						'itourSeo',
						array(
							'checks' => array(
								'keyphrase' => __( 'A focus keyphrase is set', 'itour-seo' ),
								'in_title'  => __( 'The keyphrase is in the SEO title', 'itour-seo' ),
								'in_desc'   => __( 'The keyphrase is in the meta description', 'itour-seo' ),
								'in_slug'   => __( 'The keyphrase is in the page address', 'itour-seo' ),
								'in_intro'  => __( 'The keyphrase is in the first paragraph', 'itour-seo' ),
								'title_len' => __( 'SEO title is 30–60 characters', 'itour-seo' ),
								'desc_len'  => __( 'Meta description is 120–156 characters', 'itour-seo' ),
								'words'     => __( 'The text has at least 250 words', 'itour-seo' ),
								'alt'       => __( 'Every image in the text has alt text', 'itour-seo' ),
								'link'      => __( 'The text links to another page of this site', 'itour-seo' ),
							),
							/* translators: %d: characters. */
							'chars'  => __( '%d characters', 'itour-seo' ),
						)
					);
					$default_title = Seo::fill( Seo::title_template( $post->post_type ), $post->ID );
					$default_desc  = Seo::fill( Seo::desc_template( $post->post_type ), $post->ID );
					echo Panel::row( __( 'Focus keyphrase', 'itour-seo' ), Panel::input( 'seo', 'keyphrase', $v['keyphrase'], 'text', array( 'placeholder' => __( 'e.g. Luxor day trip from Hurghada', 'itour-seo' ) ) ), __( 'The search phrase this page should be found for. The checklist below uses it.', 'itour-seo' ), 'itp-seo-keyphrase' );
					/* translators: {title}, {sep}, {site}, {tagline}, {excerpt} and {price} are template variables: keep them as they are. */
					echo Panel::row( __( 'SEO title', 'itour-seo' ), Panel::input( 'seo', 'title', $v['title'], 'text', array( 'placeholder' => $default_title ) ) . '<span class="itp-unit" data-seo-count="title"></span>', __( 'Shown as the blue link in Google. Empty = the default for this content type (SEO → Settings). Variables: {title} {sep} {site} {price}.', 'itour-seo' ), 'itp-seo-title' );
					echo Panel::row( __( 'Meta description', 'itour-seo' ), '<textarea class="itp-input" id="itp-seo-desc" rows="3" name="' . esc_attr( Panel::name( 'seo', 'desc' ) ) . '" placeholder="' . esc_attr( $default_desc ) . '">' . esc_textarea( $v['desc'] ) . '</textarea><span class="itp-unit" data-seo-count="desc"></span>', __( 'The grey text under the link. Write it like a short advert: what the visitor gets and why book with you.', 'itour-seo' ), 'itp-seo-desc' );
					$url = (string) get_permalink( $post );
					echo '<div class="itp-row itp-row--wide"><div class="itp-row-text"><p class="itp-label">' . esc_html__( 'Google preview', 'itour-seo' ) . '</p></div><div class="itseo-preview" data-seo-preview data-default-title="' . esc_attr( $default_title ) . '" data-default-desc="' . esc_attr( $default_desc ) . '" data-slug="' . esc_attr( $post->post_name ) . '">'
						. '<p class="itseo-preview__site"><span class="itseo-preview__name">' . esc_html( get_bloginfo( 'name' ) ) . '</span><span class="itseo-preview__url">' . esc_html( preg_replace( '#^https?://#', '', $url ) ) . '</span></p>'
						. '<p class="itseo-preview__title" data-seo-preview-title></p><p class="itseo-preview__desc" data-seo-preview-desc></p></div></div>';
					echo '<div class="itp-row itp-row--wide"><div class="itp-row-text"><p class="itp-label">' . esc_html__( 'Checklist', 'itour-seo' ) . '</p><p class="itp-help">' . esc_html__( 'Updates as you type. Green items help this page rank; none of them is required.', 'itour-seo' ) . '</p></div><ul class="itseo-checks" data-seo-checks></ul></div>';
					echo '<input type="hidden" name="' . esc_attr( Panel::name( 'seo', 'score' ) ) . '" value="' . esc_attr( $v['score'] ) . '" data-seo-score>';
				},
				'save'   => static function ( int $post, array $in ): void {
					Seo::put(
						$post,
						array(
							'keyphrase' => sanitize_text_field( (string) ( $in['keyphrase'] ?? '' ) ),
							'title'     => sanitize_text_field( (string) ( $in['title'] ?? '' ) ),
							'desc'      => sanitize_textarea_field( (string) ( $in['desc'] ?? '' ) ),
							'score'     => preg_match( '#^\d{1,2}/\d{1,2}$#', (string) ( $in['score'] ?? '' ) ) ? (string) $in['score'] : '',
						)
					);
				},
			),
			'seo_social'   => array(
				'label'  => __( 'Social', 'itour-seo' ),
				'render' => static function ( \WP_Post $post ): void {
					$v = Seo::item( $post->ID );
					wp_enqueue_media();
					wp_enqueue_script( 'itour-gallery', plugins_url( 'assets/gallery.js', ITOUR_CORE_FILE ), array( 'jquery' ), ITOUR_CORE_VERSION, true );
					echo '<div class="itp-row itp-row--wide"><div class="itp-row-text"><p class="itp-help">' . esc_html__( 'How the page looks when shared on Facebook, WhatsApp, LinkedIn and X. Empty fields use the SEO title, the meta description and the featured image.', 'itour-seo' ) . '</p></div></div>';
					echo Panel::row( __( 'Share title', 'itour-seo' ), Panel::input( 'seo_social', 'og_title', $v['og_title'], 'text', array( 'placeholder' => Seo::title( $post->ID ) ) ), '', 'itp-seo_social-og_title' );
					echo Panel::row( __( 'Share description', 'itour-seo' ), '<textarea class="itp-input" id="itp-seo_social-og_desc" rows="3" name="' . esc_attr( Panel::name( 'seo_social', 'og_desc' ) ) . '" placeholder="' . esc_attr( Seo::desc( $post->ID ) ) . '">' . esc_textarea( $v['og_desc'] ) . '</textarea>', '', 'itp-seo_social-og_desc' );
					$id  = (int) $v['og_image'];
					$img = $id ? '<li>' . wp_get_attachment_image( $id, 'thumbnail' ) . '</li>' : '';
					echo Panel::row(
						__( 'Share image', 'itour-seo' ),
						'<div data-itour-gallery><input type="hidden" name="' . esc_attr( Panel::name( 'seo_social', 'og_image' ) ) . '" value="' . ( $id ? (int) $id : '' ) . '"><ul class="itp-gallery-list" data-itour-gallery-list>' . $img . '</ul><button type="button" class="itp-btn" data-itour-gallery-pick data-title="' . esc_attr__( 'Share image', 'itour-seo' ) . '" data-button="' . esc_attr__( 'Use this image', 'itour-seo' ) . '">' . esc_html__( 'Choose image', 'itour-seo' ) . '</button> <button type="button" class="itp-btn itp-btn--quiet" data-itour-gallery-clear>' . esc_html__( 'Remove', 'itour-seo' ) . '</button></div>',
						__( 'Best at 1200 × 630 pixels. Empty = the featured image.', 'itour-seo' )
					);
				},
				'save'   => static function ( int $post, array $in ): void {
					$ids = array_filter( array_map( 'absint', explode( ',', (string) ( $in['og_image'] ?? '' ) ) ) );
					$img = $ids ? (int) reset( $ids ) : 0;
					Seo::put(
						$post,
						array(
							'og_title' => sanitize_text_field( (string) ( $in['og_title'] ?? '' ) ),
							'og_desc'  => sanitize_textarea_field( (string) ( $in['og_desc'] ?? '' ) ),
							'og_image' => $img && wp_attachment_is_image( $img ) ? (string) $img : '',
						)
					);
				},
			),
			'seo_advanced' => array(
				'label'  => __( 'Advanced', 'itour-seo' ),
				'badge'  => static fn( int $post ): string => '1' === Seo::item( $post )['noindex'] ? __( 'hidden', 'itour-seo' ) : '',
				'render' => static function ( \WP_Post $post ): void {
					$v  = Seo::item( $post->ID );
					$on = array_keys( array_filter( array( 'noindex' => '1' === $v['noindex'], 'nofollow' => '1' === $v['nofollow'] ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
					echo Panel::row(
						__( 'Search engines', 'itour-seo' ),
						Panel::choices(
							'seo_advanced',
							'robots',
							array(
								'noindex'  => __( 'Hide this page from search results', 'itour-seo' ),
								'nofollow' => __( 'Don’t follow its links', 'itour-seo' ),
							),
							$on
						),
						__( 'Hidden pages also leave the sitemap. Use it for thank-you pages, drafts made public, duplicates.', 'itour-seo' )
					);
					echo Panel::row( __( 'Canonical address', 'itour-seo' ), Panel::input( 'seo_advanced', 'canonical', $v['canonical'], 'url', array( 'placeholder' => (string) get_permalink( $post ) ) ), __( 'Only when this page copies another one: the address of the original. Empty = this page’s own address.', 'itour-seo' ), 'itp-seo_advanced-canonical' );
				},
				'save'   => static function ( int $post, array $in ): void {
					$robots = (array) ( $in['robots'] ?? array() );
					Seo::put(
						$post,
						array(
							'noindex'   => in_array( 'noindex', $robots, true ) ? '1' : '',
							'nofollow'  => in_array( 'nofollow', $robots, true ) ? '1' : '',
							'canonical' => esc_url_raw( trim( (string) ( $in['canonical'] ?? '' ) ), array( 'http', 'https' ) ),
						)
					);
				},
			),
		);
		// phpcs:enable
	}
}
