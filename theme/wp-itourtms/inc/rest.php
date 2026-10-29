<?php
/**
 * REST route the Theme Options screen saves through (needs edit_theme_options).
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * GET returns the options; POST saves them (sanitized) and returns contrast warnings; DELETE resets.
 */
function wp_itourtms_options_routes(): void {
	$can = static fn() => current_user_can( 'edit_theme_options' );
	register_rest_route(
		'wp-itourtms/v1',
		'/options',
		array(
			array(
				'methods'             => 'GET',
				'permission_callback' => $can,
				'callback'            => static fn() => rest_ensure_response( array( 'values' => wp_itourtms_options() ) ),
			),
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'callback'            => static function ( WP_REST_Request $r ) {
					// Fields not sent keep their saved value.
					$clean = wp_itourtms_sanitize_options( array_merge( wp_itourtms_options(), (array) $r->get_param( 'values' ) ) );
					update_option( 'wp_itourtms_options', $clean );
					return rest_ensure_response(
						array(
							'values'   => $clean,
							'warnings' => wp_itourtms_contrast_warnings( $clean ),
						)
					);
				},
			),
			array(
				'methods'             => 'DELETE',
				'permission_callback' => $can,
				'callback'            => static function () {
					delete_option( 'wp_itourtms_options' );
					wp_clean_theme_json_cache();
					return rest_ensure_response( array( 'values' => wp_itourtms_option_defaults() ) );
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'wp_itourtms_options_routes' );
