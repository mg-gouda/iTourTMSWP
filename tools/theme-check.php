<?php
/**
 * Run the WordPress.org Theme Check plugin against a theme from WP-CLI and print its findings.
 * Usage: bin/theme-check.sh [theme-slug]
 *
 * @package ITour
 */

$slug = $args[0] ?? 'wp-itourtms';
require_once WP_PLUGIN_DIR . '/theme-check/checkbase.php';
require_once WP_PLUGIN_DIR . '/theme-check/main.php';

$theme = wp_get_theme( $slug );
$ok    = run_themechecks_against_theme( $theme, $slug );
global $themechecks;
$count = 0;
foreach ( $themechecks as $check ) {
	foreach ( (array) $check->getError() as $msg ) {
		++$count;
		echo esc_html( wp_strip_all_tags( html_entity_decode( $msg ) ) ) . "\n";
	}
}
echo esc_html( $ok ? "PASS: {$slug} passed the required tests ({$count} notes).\n" : "FAIL: {$slug} ({$count} findings).\n" );
