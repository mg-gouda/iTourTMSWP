<?php
/**
 * Product post types (Tour Packages, Excursions…): public pages edited in the block editor, each with its
 * own admin menu. Prices, schedules and bookings stay in the module's tables (D-003).
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a product post type.
 */
final class Products {

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
					/* translators: %s: product type. */
					'edit_item'     => sprintf( __( 'Edit %s', 'itour-core' ), $singular ),
					/* translators: %s: product type. */
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
	}
}
