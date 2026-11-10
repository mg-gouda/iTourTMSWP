<?php
/**
 * SEO → Settings: one tabbed screen (core Panel, saves as you go). Tabs: General, Content types,
 * Organization, Indexing, Import (from Yoast SEO).
 *
 * @package ITour\Seo
 */

namespace ITour\Seo;

use ITour\Core\Admin\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
final class Settings {

	/**
	 * Merge values into the settings.
	 *
	 * @param array<string,mixed> $values Values.
	 */
	private static function put( array $values ): void {
		update_option( Seo::OPTION, array_merge( Seo::settings(), $values ) );
	}

	/**
	 * Image picker (core gallery component, first image used).
	 *
	 * @param string $section Section.
	 * @param string $field   Field.
	 * @param int    $id      Attachment id.
	 */
	private static function image( string $section, string $field, int $id ): string {
		wp_enqueue_media();
		wp_enqueue_script( 'itour-gallery', plugins_url( 'assets/gallery.js', ITOUR_CORE_FILE ), array( 'jquery' ), ITOUR_CORE_VERSION, true );
		return '<div data-itour-gallery><input type="hidden" name="' . esc_attr( Panel::name( $section, $field ) ) . '" value="' . ( $id ? (int) $id : '' ) . '"><ul class="itp-gallery-list" data-itour-gallery-list>' . ( $id ? '<li>' . wp_get_attachment_image( $id, 'thumbnail' ) . '</li>' : '' ) . '</ul><button type="button" class="itp-btn" data-itour-gallery-pick data-title="' . esc_attr__( 'Choose image', 'itour-seo' ) . '" data-button="' . esc_attr__( 'Use this image', 'itour-seo' ) . '">' . esc_html__( 'Choose image', 'itour-seo' ) . '</button> <button type="button" class="itp-btn itp-btn--quiet" data-itour-gallery-clear>' . esc_html__( 'Remove', 'itour-seo' ) . '</button></div>';
	}

	/**
	 * First image id from a picker value.
	 *
	 * @param mixed $v Posted value.
	 */
	private static function image_id( $v ): int {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $v ) ) );
		$id  = $ids ? (int) reset( $ids ) : 0;
		return $id && wp_attachment_is_image( $id ) ? $id : 0;
	}

	/**
	 * Register the screen.
	 *
	 * @param string $cap Capability.
	 */
	public static function register( string $cap ): void {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Panel helpers escape.
		$rules = array(
			'noindex_tags'        => __( 'Hide tag archives', 'itour-seo' ),
			'noindex_cats'        => __( 'Hide blog category archives', 'itour-seo' ),
			'noindex_authors'     => __( 'Hide author archives', 'itour-seo' ),
			'noindex_dates'       => __( 'Hide date archives', 'itour-seo' ),
			'feeds_noindex'       => __( 'Keep RSS feeds out of search results', 'itour-seo' ),
			'gone_410'            => __( 'Deleted pages answer “410 Gone”', 'itour-seo' ),
			'attachment_redirect' => __( 'Attachment pages open the file itself', 'itour-seo' ),
		);
		Panel::register_screen(
			'seo-settings',
			__( 'SEO settings', 'itour-seo' ),
			$cap,
			array(
				'general'      => array(
					'label'  => __( 'General', 'itour-seo' ),
					'render' => static function (): void {
						$s = Seo::settings();
						if ( Seo::yoast_active() ) {
							echo '<div class="itp-row itp-row--wide"><p class="itp-help" style="color:#8a5300;font-weight:500">' . esc_html__( 'Yoast SEO is active, so iTour SEO adds nothing to your pages yet (no double tags). Import Yoast’s data on the Import tab, then deactivate Yoast.', 'itour-seo' ) . '</p></div>';
						}
						echo Panel::row(
							__( 'Title separator', 'itour-seo' ),
							Panel::choices(
								'general',
								'sep',
								array(
									'–' => '–',
									'|' => '|',
									'·' => '·',
									'•' => '•',
									'»' => '»',
								),
								$s['sep'],
								false
							),
							/* translators: {title}, {sep}, {site}, {tagline}, {excerpt} and {price} are template variables: keep them as they are. */
							__( 'Used by {sep} in titles, e.g. “Luxor day trip – Nile & Sea Travel”.', 'itour-seo' )
						);
						/* translators: {title}, {sep}, {site}, {tagline}, {excerpt} and {price} are template variables: keep them as they are. */
						echo Panel::row( __( 'Home page title', 'itour-seo' ), Panel::input( 'general', 'home_title', $s['home_title'] ), __( 'Variables: {site} {sep} {tagline}. A static home page can override it in its own SEO tab.', 'itour-seo' ), 'itp-general-home_title' );
						echo Panel::row( __( 'Home page description', 'itour-seo' ), '<textarea class="itp-input" id="itp-general-home_desc" rows="3" name="' . esc_attr( Panel::name( 'general', 'home_desc' ) ) . '">' . esc_textarea( (string) $s['home_desc'] ) . '</textarea>', __( 'About 120–156 characters: what you offer and where.', 'itour-seo' ), 'itp-general-home_desc' );
					},
					'save'   => static function ( int $unused, array $in ): void {
						self::put(
							array(
								'sep'        => in_array( $in['sep'] ?? '', array( '–', '|', '·', '•', '»' ), true ) ? $in['sep'] : '–',
								'home_title' => sanitize_text_field( (string) ( $in['home_title'] ?? '' ) ),
								'home_desc'  => sanitize_textarea_field( (string) ( $in['home_desc'] ?? '' ) ),
							)
						);
					},
				),
				'types'        => array(
					'label'  => __( 'Content types', 'itour-seo' ),
					'render' => static function (): void {
						$s = Seo::settings();
						/* translators: {title}, {sep}, {site}, {tagline}, {excerpt} and {price} are template variables: keep them as they are. */
						echo '<div class="itp-row itp-row--wide"><p class="itp-help">' . esc_html__( 'Default title and description for each kind of page, used when a page leaves its own SEO fields empty. Variables: {title} {sep} {site} {excerpt} {price} (tours and excursions: the “from” price).', 'itour-seo' ) . '</p></div>';
						foreach ( Seo::types() as $type => $label ) {
							$control  = '<label class="itp-help" for="itp-types-t-' . esc_attr( $type ) . '">' . esc_html__( 'Title', 'itour-seo' ) . '</label>';
							$control .= '<input class="itp-input itp-input--text" id="itp-types-t-' . esc_attr( $type ) . '" name="' . esc_attr( Panel::name( 'types', 'titles' ) ) . '[' . esc_attr( $type ) . ']" value="' . esc_attr( Seo::title_template( $type ) ) . '">';
							$control .= '<label class="itp-help" for="itp-types-d-' . esc_attr( $type ) . '">' . esc_html__( 'Description', 'itour-seo' ) . '</label>';
							$control .= '<input class="itp-input itp-input--text" id="itp-types-d-' . esc_attr( $type ) . '" name="' . esc_attr( Panel::name( 'types', 'descs' ) ) . '[' . esc_attr( $type ) . ']" value="' . esc_attr( Seo::desc_template( $type ) ) . '">';
							$control .= '<label class="itp-chip"><input type="checkbox" name="' . esc_attr( Panel::name( 'types', 'hidden' ) ) . '[]" value="' . esc_attr( $type ) . '"' . checked( in_array( $type, (array) $s['hidden'], true ), true, false ) . '><span>' . esc_html__( 'Hide from search results', 'itour-seo' ) . '</span></label>';
							echo Panel::row( $label, '<div class="itseo-type">' . $control . '</div>' );
						}
						echo '<input type="hidden" name="' . esc_attr( Panel::name( 'types', 'hidden' ) ) . '[]" value="">';
					},
					'save'   => static function ( int $unused, array $in ): void {
						$types = array_keys( Seo::types() );
						$clean = static fn( $vals ) => array_intersect_key( array_map( 'sanitize_text_field', (array) $vals ), array_flip( $types ) );
						self::put(
							array(
								'titles' => $clean( $in['titles'] ?? array() ),
								'descs'  => $clean( $in['descs'] ?? array() ),
								'hidden' => array_values( array_intersect( $types, (array) ( $in['hidden'] ?? array() ) ) ),
							)
						);
					},
				),
				'organization' => array(
					'label'  => __( 'Organization', 'itour-seo' ),
					'render' => static function (): void {
						$s = Seo::settings();
						echo '<div class="itp-row itp-row--wide"><p class="itp-help">' . esc_html__( 'Who runs this site. Google uses it for your knowledge panel and to link your tours to your company.', 'itour-seo' ) . '</p></div>';
						echo Panel::row(
							__( 'Type', 'itour-seo' ),
							Panel::choices(
								'organization',
								'org_type',
								array(
									'TravelAgency'  => __( 'Travel agency / DMC', 'itour-seo' ),
									'LocalBusiness' => __( 'Local business', 'itour-seo' ),
									'Organization'  => __( 'Organization', 'itour-seo' ),
								),
								$s['org_type'],
								false
							)
						);
						echo Panel::row( __( 'Name', 'itour-seo' ), Panel::input( 'organization', 'org_name', $s['org_name'], 'text', array( 'placeholder' => get_bloginfo( 'name' ) ) ), '', 'itp-organization-org_name' );
						echo Panel::row( __( 'Logo', 'itour-seo' ), self::image( 'organization', 'org_logo', (int) $s['org_logo'] ), __( 'Square or wide, at least 112 × 112 pixels.', 'itour-seo' ) );
						echo Panel::row( __( 'Phone', 'itour-seo' ), Panel::input( 'organization', 'org_phone', $s['org_phone'], 'tel' ), __( 'With the country code, e.g. +20 100 000 0000.', 'itour-seo' ), 'itp-organization-org_phone' );
						echo Panel::row( __( 'Email', 'itour-seo' ), Panel::input( 'organization', 'org_email', $s['org_email'], 'email' ), '', 'itp-organization-org_email' );
						echo Panel::row( __( 'Social profiles', 'itour-seo' ), '<textarea class="itp-input" id="itp-organization-org_socials" rows="4" name="' . esc_attr( Panel::name( 'organization', 'org_socials' ) ) . '" placeholder="https://www.facebook.com/…">' . esc_textarea( (string) $s['org_socials'] ) . '</textarea>', __( 'One address per line: Facebook, Instagram, TikTok, YouTube, LinkedIn, TripAdvisor.', 'itour-seo' ), 'itp-organization-org_socials' );
						echo Panel::row( __( 'Default share image', 'itour-seo' ), self::image( 'organization', 'share_image', (int) $s['share_image'] ), __( 'Used when a page has no image of its own. Best at 1200 × 630 pixels.', 'itour-seo' ) );
					},
					'save'   => static function ( int $unused, array $in ): void {
						self::put(
							array(
								'org_type'    => in_array( $in['org_type'] ?? '', array( 'TravelAgency', 'LocalBusiness', 'Organization' ), true ) ? $in['org_type'] : 'TravelAgency',
								'org_name'    => sanitize_text_field( (string) ( $in['org_name'] ?? '' ) ),
								'org_logo'    => self::image_id( $in['org_logo'] ?? '' ),
								'org_phone'   => sanitize_text_field( (string) ( $in['org_phone'] ?? '' ) ),
								'org_email'   => sanitize_email( (string) ( $in['org_email'] ?? '' ) ),
								'org_socials' => implode( "\n", array_filter( array_map( static fn( $u ) => esc_url_raw( trim( $u ), array( 'http', 'https' ) ), preg_split( '/\R/u', (string) ( $in['org_socials'] ?? '' ) ) ) ) ),
								'share_image' => self::image_id( $in['share_image'] ?? '' ),
							)
						);
					},
				),
				'indexing'     => array(
					'label'  => __( 'Indexing', 'itour-seo' ),
					'render' => static function () use ( $rules ): void {
						$s  = Seo::settings();
						$on = array_keys( array_filter( array_intersect_key( $s, $rules ) ) );
						echo Panel::row( __( 'Keep out of search results', 'itour-seo' ), Panel::choices( 'indexing', 'rules', $rules, $on ), __( 'Thin listing pages and feeds compete with the pages that sell. Search result pages are always hidden.', 'itour-seo' ) );
						/* translators: %s: sitemap address. */
						echo Panel::row( __( 'Sitemap', 'itour-seo' ), '<a class="itp-btn itp-btn--plain" href="' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '" target="_blank" rel="noopener">' . esc_html( home_url( '/wp-sitemap.xml' ) ) . '</a>', __( 'Submit this address in Google Search Console. Hidden pages, hidden content types and the archives above are left out automatically.', 'itour-seo' ) );
					},
					'save'   => static function ( int $unused, array $in ) use ( $rules ): void {
						$on = (array) ( $in['rules'] ?? array() );
						self::put( array_map( static fn( $k ) => in_array( $k, $on, true ) ? 1 : 0, array_combine( array_keys( $rules ), array_keys( $rules ) ) ) );
					},
				),
				'import'       => array(
					'label'  => __( 'Import', 'itour-seo' ),
					'render' => static function (): void {
						$n = Import::count();
						$u = wp_nonce_url( admin_url( 'admin-post.php?action=itour_seo_import' ), 'itour_seo_import' );
						/* translators: %d: number of pages. */
						$help = $n ? sprintf( _n( '%d page has Yoast SEO data. Importing copies its SEO title, description, focus keyphrase, canonical, noindex and social fields into iTour SEO. Fields you already filled here are kept.', '%d pages have Yoast SEO data. Importing copies their SEO titles, descriptions, focus keyphrases, canonicals, noindex and social fields into iTour SEO. Fields you already filled here are kept.', $n, 'itour-seo' ), $n ) : __( 'No Yoast SEO data found on this site.', 'itour-seo' );
						echo Panel::row( __( 'From Yoast SEO', 'itour-seo' ), $n ? '<a class="itp-btn itp-btn--primary" href="' . esc_url( $u ) . '">' . esc_html__( 'Import Yoast data', 'itour-seo' ) . '</a>' : '', $help );
					},
					'save'   => static function (): void {},
				),
			)
		);
		// phpcs:enable
	}

	/**
	 * The admin page.
	 */
	public static function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- result notice only.
		if ( isset( $_GET['imported'] ) ) {
			/* translators: %d: pages. */
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( _n( 'Imported Yoast data for %d page.', 'Imported Yoast data for %d pages.', absint( $_GET['imported'] ), 'itour-seo' ), absint( $_GET['imported'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		wp_enqueue_style( 'itour-seo-editor', plugins_url( 'assets/seo-editor.css', ITOUR_SEO_FILE ), array( 'itour-panel' ), '0.1.0' );
		Panel::render_screen( 'seo-settings' );
	}
}
