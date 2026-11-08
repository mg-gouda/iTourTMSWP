<?php
/**
 * Delete settings, log and per-user two-factor data. Files were already removed on deactivation.
 *
 * @package ITour\Shield
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'itour_shield', 'itour_shield_version', 'itour_shield_scan', 'itour_shield_scan_job', 'itour_shield_notice' ) as $itour_shield_opt ) {
	delete_option( $itour_shield_opt );
}
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'itour_shield_log' ); // phpcs:ignore WordPress.DB
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\_itour\_shield\_%'" ); // phpcs:ignore WordPress.DB
