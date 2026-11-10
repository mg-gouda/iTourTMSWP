<?php
/**
 * Product post types (Tour Packages, Excursions…): public pages edited in the block editor, each with its
 * own admin menu, its own Categories, the shared Languages and Suitability tags, and shared panel tabs
 * (included, FAQ, photos). Prices, schedules and bookings stay in the module (D-003).
 *
 * @package ITour\Core
 */

namespace ITour\Core;

use ITour\Core\Admin\Panel;
use ITour\Core\Admin\Rows;

defined( 'ABSPATH' ) || exit;

/**
 * Registers product types and their shared info.
 */
final class Products {

	/**
	 * Meta key for the shared info.
	 */
	public const INFO = '_itour_info';

	/**
	 * Register on init.
	 *
	 * @param string $type     Post type key, e.g. itour_excursion.
	 * @param string $slug     URL base, e.g. excursion.
	 * @param string $plural   Menu and list name.
	 * @param string $singular One item.
	 * @param string $icon     Dashicon.
	 * @param int    $position Menu position.
	 */
	public static function register( string $type, string $slug, string $plural, string $singular, string $icon, int $position ): void {
		register_post_type(
			$type,
			array(
				'labels'        => array(
					'name'          => $plural,
					'singular_name' => $singular,
					'menu_name'     => $plural,
					/* translators: %s: product type, e.g. "excursion". */
					'add_new_item'  => sprintf( __( 'Add %s', 'itour-core' ), $singular ),
					/* translators: %s: product type, e.g. "excursion". */
					'edit_item'     => sprintf( __( 'Edit %s', 'itour-core' ), $singular ),
					/* translators: %s: product type, e.g. "excursion". */
					'view_item'     => sprintf( __( 'View %s', 'itour-core' ), $singular ),
					'all_items'     => $plural,
				),
				'public'        => true,
				'show_in_rest'  => true, // Block editor.
				'menu_icon'     => $icon,
				'menu_position' => $position,
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'rewrite'       => array( 'slug' => $slug ),
				'has_archive'   => false, // Listing pages are chosen in Theme Options.
			)
		);
		register_taxonomy(
			$type . '_cat',
			$type,
			array(
				'labels'            => array(
					'name'          => __( 'Categories', 'itour-core' ),
					'singular_name' => __( 'Category', 'itour-core' ),
				),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => $slug . '-category' ),
			)
		);
		$shared = array(
			'itour_language'    => array( __( 'Languages', 'itour-core' ), __( 'Language', 'itour-core' ), 'language' ),
			'itour_suitability' => array( __( 'Suitability', 'itour-core' ), __( 'Suitable for', 'itour-core' ), 'suitable-for' ),
		);
		foreach ( $shared as $tax => list( $name, $one, $base ) ) {
			if ( ! taxonomy_exists( $tax ) ) {
				register_taxonomy(
					$tax,
					array(),
					array(
						'labels'       => array(
							'name'          => $name,
							'singular_name' => $one,
						),
						'hierarchical' => false,
						'show_in_rest' => true,
						'rewrite'      => array( 'slug' => $base ),
					)
				);
			}
			register_taxonomy_for_object_type( $tax, $type );
		}
	}

	/**
	 * The shared info, with defaults.
	 *
	 * @param int $post Post id.
	 * @return array{includes:string,excludes:string,faq:array<int,array<string,string>>,gallery:int[]}
	 */
	public static function info( int $post ): array {
		return array_merge(
			array(
				'includes' => '',
				'excludes' => '',
				'faq'      => array(),
				'gallery'  => array(),
			),
			(array) get_post_meta( $post, self::INFO, true )
		);
	}

	/**
	 * FAQ columns.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function faq_cols(): array {
		return array(
			'q' => array(
				'label' => __( 'Question', 'itour-core' ),
				'type'  => 'text',
				'width' => '35%',
			),
			'a' => array(
				'label' => __( 'Answer', 'itour-core' ),
				'type'  => 'textarea',
			),
		);
	}

	/**
	 * Merge values into the shared info.
	 *
	 * @param int                 $post   Post id.
	 * @param array<string,mixed> $values Values.
	 */
	private static function put( int $post, array $values ): void {
		update_post_meta( $post, self::INFO, array_merge( self::info( $post ), $values ) );
	}

	/**
	 * The shared panel tabs every product appends: Included, FAQ, Photos.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function info_sections(): array {
		$lines = static fn( string $text ): string => (string) count( array_filter( array_map( 'trim', explode( "\n", $text ) ) ) );
		return array(
			'included' => array(
				'label'  => __( 'Included', 'itour-core' ),
				'badge'  => static function ( int $post ) use ( $lines ): string {
					$i = self::info( $post );
					return '' === trim( $i['includes'] . $i['excludes'] ) ? '' : $lines( $i['includes'] ) . '/' . $lines( $i['excludes'] );
				},
				'render' => static function ( \WP_Post $post ): void {
					$i = self::info( $post->ID );
					foreach ( array( 'includes' => array( __( 'Included', 'itour-core' ), __( 'One item per line, e.g. "Airport transfers".', 'itour-core' ) ), 'excludes' => array( __( 'Not included', 'itour-core' ), __( 'One item per line, e.g. "Flights", "Visa".', 'itour-core' ) ) ) as $k => list( $label, $help ) ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
						echo Panel::row( $label, '<textarea class="itp-input" id="itp-included-' . esc_attr( $k ) . '" rows="6" name="' . esc_attr( Panel::name( 'included', $k ) ) . '">' . esc_textarea( $i[ $k ] ) . '</textarea>', $help, 'itp-included-' . $k ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
					}
				},
				'save'   => static function ( int $post, array $in ): void {
					self::put(
						$post,
						array(
							'includes' => sanitize_textarea_field( (string) ( $in['includes'] ?? '' ) ),
							'excludes' => sanitize_textarea_field( (string) ( $in['excludes'] ?? '' ) ),
						)
					);
				},
			),
			'faq'      => array(
				'label'  => __( 'FAQ', 'itour-core' ),
				'badge'  => static fn( int $post ): string => self::info( $post )['faq'] ? (string) count( self::info( $post )['faq'] ) : '',
				'render' => static function ( \WP_Post $post ): void {
					echo '<div class="itp-row itp-row--wide"><div class="itp-row-text"><p class="itp-help">' . esc_html__( 'Questions travellers ask before booking. Shown as an expandable list on the page.', 'itour-core' ) . '</p></div><div class="itp-table-wrap">';
					Rows::render( Panel::name( 'faq', 'rows' ), self::faq_cols(), self::info( $post->ID )['faq'], __( 'Add question', 'itour-core' ) );
					echo '</div></div>';
				},
				'save'   => static function ( int $post, array $in ): void {
					self::put( $post, array( 'faq' => Rows::clean( self::faq_cols(), $in['rows'] ?? array(), array( 'q', 'a' ) ) ) );
				},
			),
			'photos'   => array(
				'label'  => __( 'Photos', 'itour-core' ),
				'badge'  => static fn( int $post ): string => self::info( $post )['gallery'] ? (string) count( self::info( $post )['gallery'] ) : '',
				'render' => static function ( \WP_Post $post ): void {
					$ids = array_map( 'intval', self::info( $post->ID )['gallery'] );
					wp_enqueue_media();
					wp_enqueue_script( 'itour-gallery', plugins_url( 'assets/gallery.js', ITOUR_CORE_FILE ), array( 'jquery' ), ITOUR_CORE_VERSION, true );
					$list = '';
					foreach ( $ids as $id ) {
						$list .= '<li>' . wp_get_attachment_image( $id, 'thumbnail' ) . '</li>';
					}
					$control = '<div data-itour-gallery><input type="hidden" name="' . esc_attr( Panel::name( 'photos', 'gallery' ) ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '"><ul class="itp-gallery-list" data-itour-gallery-list>' . $list . '</ul>'
						. '<button type="button" class="itp-btn" data-itour-gallery-pick data-title="' . esc_attr__( 'Gallery photos', 'itour-core' ) . '" data-button="' . esc_attr__( 'Use these photos', 'itour-core' ) . '">' . esc_html__( 'Choose photos', 'itour-core' ) . '</button> '
						. '<button type="button" class="itp-btn itp-btn--quiet" data-itour-gallery-clear>' . esc_html__( 'Remove all', 'itour-core' ) . '</button></div>';
					echo Panel::row( __( 'Gallery', 'itour-core' ), $control, __( 'Shown on the page below the details. Drag to reorder inside the picker. The featured image (editor sidebar) stays the main photo.', 'itour-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				},
				'save'   => static function ( int $post, array $in ): void {
					self::put( $post, array( 'gallery' => array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $in['gallery'] ?? '' ) ) ), static fn( $id ) => wp_attachment_is_image( $id ) ) ) ) );
				},
			),
		);
	}

	/**
	 * The info on the product page: included / not included, gallery, FAQ.
	 *
	 * @param int $post Post id.
	 */
	public static function render_info( int $post ): string {
		$i     = self::info( $post );
		$h     = '';
		$lists = array(
			'includes' => __( 'Included', 'itour-core' ),
			'excludes' => __( 'Not included', 'itour-core' ),
		);
		$cols  = '';
		foreach ( $lists as $k => $title ) {
			$items = array_filter( array_map( 'trim', explode( "\n", $i[ $k ] ) ) );
			if ( $items ) {
				$cols .= '<div class="itour-info__list itour-info__list--' . esc_attr( $k ) . '"><h3>' . esc_html( $title ) . '</h3><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li></ul></div>';
			}
		}
		if ( '' !== $cols ) {
			$h .= '<div class="itour-info__lists">' . $cols . '</div>';
		}
		if ( $i['gallery'] ) {
			$h .= '<h2 class="itour-exc__title">' . esc_html__( 'Photos', 'itour-core' ) . '</h2><ul class="itour-info__gallery">';
			foreach ( $i['gallery'] as $id ) {
				$h .= '<li><a href="' . esc_url( (string) wp_get_attachment_image_url( (int) $id, 'full' ) ) . '">' . wp_get_attachment_image( (int) $id, 'medium', false, array( 'loading' => 'lazy' ) ) . '</a></li>';
			}
			$h .= '</ul>';
		}
		if ( $i['faq'] ) {
			$h .= '<h2 class="itour-exc__title">' . esc_html__( 'Questions', 'itour-core' ) . '</h2><div class="itour-info__faq">';
			foreach ( $i['faq'] as $f ) {
				$h .= '<details><summary>' . esc_html( $f['q'] ) . '</summary><p>' . nl2br( esc_html( $f['a'] ) ) . '</p></details>';
			}
			$h .= '</div>';
		}
		$terms = array();
		foreach ( array( 'itour_language' => __( 'Languages', 'itour-core' ), 'itour_suitability' => __( 'Suitable for', 'itour-core' ) ) as $tax => $label ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			$names = wp_get_post_terms( $post, $tax, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $names ) && $names ) {
				$terms[ $label ] = implode( ', ', $names );
			}
		}
		if ( $terms ) {
			$t = '<dl class="itour-info__terms">';
			foreach ( $terms as $k => $v ) {
				$t .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
			}
			$h = $t . '</dl>' . $h;
		}
		return '' === $h ? '' : '<div class="itour-info">' . $h . '</div>';
	}

	/**
	 * Load the shared product page styles (facts, price tables, info).
	 */
	public static function enqueue_style(): void {
		wp_enqueue_style( 'itour-products', plugins_url( 'assets/products.css', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION );
	}
}
