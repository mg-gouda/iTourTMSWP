<?php
/**
 * WP iTourTMS Child: loads this stylesheet after the parent's.
 *
 * @package WP_iTourTMS_Child
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style( 'wp-itourtms-child', get_stylesheet_uri(), array( 'wp-itourtms' ), wp_get_theme()->get( 'Version' ) );
	},
	20
);
