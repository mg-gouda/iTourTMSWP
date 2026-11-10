<?php
/**
 * Product post types (Tour Packages, Excursions…): public pages edited in the block editor, each with its
 * own admin menu, its own Categories, the shared Languages and Suitability tags, and an "Info" box
 * (included, not included, FAQ, gallery). Prices, schedules and bookings stay in the module (D-003).
 *
 * @package ITour\Core
 */

namespace ITour\Core;

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
	 * Product types registered so far.
	 *
	 * @var string[]
	 */
	private static array $types = array();

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
		if ( ! self::$types ) {
			add_action( 'add_meta_boxes', array( self::class, 'box' ) );
			add_action( 'save_post', array( self::class, 'save' ) );
		}
		self::$types[] = $type;
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
	 * Add the "Info" box to every product type.
	 *
	 * @param string $post_type Post type.
	 */
	public static function box( $post_type ): void {
		if ( in_array( $post_type, self::$types, true ) ) {
			add_meta_box( 'itour-info', __( 'Info: included, FAQ, gallery', 'itour-core' ), array( self::class, 'render' ), $post_type, 'normal', 'default' );
		}
	}

	/**
	 * The box.
	 *
	 * @param \WP_Post $post Product.
	 */
	public static function render( \WP_Post $post ): void {
		$i = self::info( $post->ID );
		wp_enqueue_media();
		wp_enqueue_script( 'itour-gallery', plugins_url( 'assets/gallery.js', ITOUR_CORE_FILE ), array( 'jquery' ), ITOUR_CORE_VERSION, true );
		wp_nonce_field( 'itour_info_save', '_itour_info_nonce' );
		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px">';
		foreach ( array( 'includes' => __( 'Included (one per line)', 'itour-core' ), 'excludes' => __( 'Not included (one per line)', 'itour-core' ) ) as $k => $label ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			echo '<p style="margin:0"><label><strong>' . esc_html( $label ) . '</strong><br><textarea class="widefat" rows="6" name="itour_info[' . esc_attr( $k ) . ']">' . esc_textarea( $i[ $k ] ) . '</textarea></label></p>';
		}
		echo '</div><h3 style="margin:20px 0 6px;font-size:14px">' . esc_html__( 'FAQ', 'itour-core' ) . '</h3>';
		Rows::render( 'itour_info[faq]', self::faq_cols(), $i['faq'], __( 'Add question', 'itour-core' ) );
		echo '<h3 style="margin:20px 0 6px;font-size:14px">' . esc_html__( 'Gallery', 'itour-core' ) . '</h3><div data-itour-gallery>';
		echo '<input type="hidden" name="itour_info[gallery]" value="' . esc_attr( implode( ',', array_map( 'intval', $i['gallery'] ) ) ) . '">';
		echo '<ul class="itour-gallery__list" style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 8px;padding:0;list-style:none">';
		foreach ( $i['gallery'] as $id ) {
			echo '<li>' . wp_get_attachment_image( (int) $id, array( 80, 80 ), false, array( 'style' => 'display:block;width:80px;height:80px;object-fit:cover;border-radius:4px' ) ) . '</li>';
		}
		echo '</ul><button type="button" class="button" data-itour-gallery-pick data-title="' . esc_attr__( 'Gallery photos', 'itour-core' ) . '" data-button="' . esc_attr__( 'Use these photos', 'itour-core' ) . '">' . esc_html__( 'Choose photos', 'itour-core' ) . '</button> ';
		echo '<button type="button" class="button-link button-link-delete" data-itour-gallery-clear>' . esc_html__( 'Clear', 'itour-core' ) . '</button></div>';
	}

	/**
	 * Save the box.
	 *
	 * @param int $post_id Post id.
	 */
	public static function save( $post_id ): void {
		if ( ! isset( $_POST['_itour_info_nonce'], $_POST['itour_info'] ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_itour_info_nonce'] ) ), 'itour_info_save' ) ) {
			return;
		}
		$in = wp_unslash( (array) $_POST['itour_info'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each part sanitized below.
		update_post_meta(
			$post_id,
			self::INFO,
			array(
				'includes' => sanitize_textarea_field( (string) ( $in['includes'] ?? '' ) ),
				'excludes' => sanitize_textarea_field( (string) ( $in['excludes'] ?? '' ) ),
				'faq'      => Rows::clean( self::faq_cols(), $in['faq'] ?? array(), array( 'q', 'a' ) ),
				'gallery'  => array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $in['gallery'] ?? '' ) ) ), static fn( $id ) => wp_attachment_is_image( $id ) ) ),
			)
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
