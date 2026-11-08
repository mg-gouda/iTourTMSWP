<?php
/**
 * Adds the iTour blocks to the theme's built-in manual (when the WP iTourTMS theme is active).
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Manual section for the blocks the active iTour plugins provide.
 */
final class Manual {

	/**
	 * Hook the theme's manual filter.
	 */
	public function hooks(): void {
		add_filter( 'wp_itourtms_manual', array( $this, 'section' ) );
	}

	/**
	 * Add "iTour blocks" to the user manual.
	 *
	 * @param array<string,mixed> $manual Manual content.
	 * @return array<string,mixed>
	 */
	public function section( array $manual ): array {
		$items = array( __( 'Booking widget: the search box for tour packages, excursions and transfers. Pick a design in the block settings, or leave “Site default” to follow Theme Options → Design. Searches go to the results pages chosen in each product’s Theme Options tab.', 'itour-core' ) );
		if ( itour()->modules()->active( 'slider' ) ) {
			$items[] = __( 'Image slider: choose photos, then set the shape, slide or fade, autoplay (visitors can always pause it), arrows, dots and captions. Captions come from each image’s caption in the media library.', 'itour-core' );
		}
		if ( itour()->modules()->active( 'forms' ) ) {
			$items[] = __( 'Contact form: choose which fields show, who receives the email and the thank-you message. Messages are kept under iTour → Enquiries and protected against spam automatically.', 'itour-core' );
		}
		$manual['manual']['sections'][] = array(
			'id'     => 'itour-blocks',
			'title'  => __( 'iTour blocks', 'itour-core' ),
			'blocks' => array(
				array( 'p', __( 'In the block editor, click + and search for these blocks:', 'itour-core' ) ),
				array( 'list', $items ),
			),
		);
		return $manual;
	}
}
