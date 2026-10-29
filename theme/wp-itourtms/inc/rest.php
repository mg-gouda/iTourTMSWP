<?php
/**
 * REST route the Theme Options screen saves through (needs edit_theme_options).
 * POST body: { stores: { <option name>: { key: value, … }, … } }. Only stores declared by an
 * option section are accepted; each is sanitized against its own fields.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Save every submitted store; fields not sent keep their saved value.
 *
 * @param array<string,mixed> $stores Submitted values by store.
 * @return array<string,array<string,mixed>> Saved values by store.
 */
function wp_itourtms_save_stores( array $stores ): array {
	$known = array_unique( wp_list_pluck( wp_itourtms_option_fields(), 'store' ) );
	$saved = array();
	foreach ( $stores as $store => $values ) {
		if ( ! in_array( $store, $known, true ) || ! is_array( $values ) ) {
			continue;
		}
		$clean = wp_itourtms_sanitize_options( array_merge( wp_itourtms_options( $store ), $values ), $store );
		update_option( $store, $clean );
		$saved[ $store ] = $clean;
	}
	return $saved;
}

/**
 * GET returns the values; POST saves them and returns contrast warnings; DELETE resets the theme's options.
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
				'callback'            => static function () {
					$out = array();
					foreach ( array_unique( wp_list_pluck( wp_itourtms_option_fields(), 'store' ) ) as $store ) {
						$out[ $store ] = wp_itourtms_options( $store );
					}
					return rest_ensure_response( array( 'stores' => $out ) );
				},
			),
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'callback'            => static function ( WP_REST_Request $r ) {
					$saved = wp_itourtms_save_stores( (array) $r->get_param( 'stores' ) );
					return rest_ensure_response(
						array(
							'stores'   => $saved,
							'warnings' => wp_itourtms_contrast_warnings( wp_itourtms_options() ),
						)
					);
				},
			),
			array(
				'methods'             => 'DELETE',
				'permission_callback' => $can,
				'callback'            => static function () {
					delete_option( WP_ITOURTMS_STORE );
					wp_clean_theme_json_cache();
					return rest_ensure_response( array( 'stores' => array( WP_ITOURTMS_STORE => wp_itourtms_option_defaults() ) ) );
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'wp_itourtms_options_routes' );
