<?php
/**
 * Plugin Name:       iTour Social Auto Pilot
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Shares every blog post on all channels the way a social media manager would (link post on Facebook, LinkedIn and X; a reel on Facebook and Instagram; TikTok and YouTube Shorts; a carousel on Instagram, LinkedIn and Facebook; a story on Facebook and Instagram; an email to the Mailchimp list; Google Business Profile posts), works through older posts one at a time, and posts one tour package, day trip or transfer route every day, new ones first. Channels connect with their official APIs (Social Auto Pilot menu). Ported from Rehlla Social autopilot.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  itour-core
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-social
 *
 * @package ITour\Social
 */

defined( 'ABSPATH' ) || exit;

define( 'ITSOC_DIR', __DIR__ . '/' );
define( 'ITSOC_URL', plugin_dir_url( __FILE__ ) );
define( 'ITSOC_VER', '1.0' );

require ITSOC_DIR . 'includes/settings.php';
require ITSOC_DIR . 'includes/http.php';
require ITSOC_DIR . 'includes/content.php';
require ITSOC_DIR . 'includes/captions.php';
require ITSOC_DIR . 'includes/media.php';
require ITSOC_DIR . 'includes/queue.php';
require ITSOC_DIR . 'includes/channels/meta.php';
require ITSOC_DIR . 'includes/channels/linkedin.php';
require ITSOC_DIR . 'includes/channels/x.php';
require ITSOC_DIR . 'includes/channels/tiktok.php';
require ITSOC_DIR . 'includes/channels/google.php';
require ITSOC_DIR . 'includes/channels/mailchimp.php';
require ITSOC_DIR . 'includes/oauth.php';
if ( is_admin() ) {
	require ITSOC_DIR . 'includes/admin.php';
}

register_activation_hook( __FILE__, 'itsoc_activate' );
register_deactivation_hook( __FILE__, 'itsoc_deactivate' );

function itsoc_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( "CREATE TABLE {$wpdb->prefix}itsoc_jobs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		kind varchar(12) NOT NULL,
		object_id varchar(40) NOT NULL,
		channel varchar(20) NOT NULL,
		format varchar(12) NOT NULL,
		run_at datetime NOT NULL,
		status varchar(12) NOT NULL DEFAULT 'queued',
		attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
		state text NULL,
		remote varchar(255) NOT NULL DEFAULT '',
		message text NULL,
		created datetime NOT NULL,
		done_at datetime NULL,
		PRIMARY KEY  (id),
		KEY due (status, run_at),
		KEY obj (kind, object_id)
	) {$wpdb->get_charset_collate()};" );

	// Everything that exists today is "old"; products added from now on are "new" and jump the daily queue.
	if ( false === get_option( 'itsoc_seen' ) ) {
		update_option( 'itsoc_seen', array_fill_keys( itsoc_product_keys(), 1 ), false );
	}
	itsoc_schedule();
}

function itsoc_deactivate() {
	wp_clear_scheduled_hook( 'itsoc_tick' );
	wp_clear_scheduled_hook( 'itsoc_daily' );
}

add_filter( 'cron_schedules', function ( $s ) {
	$s['itsoc_10min'] = array( 'interval' => 600, 'display' => 'Every 10 minutes' );
	return $s;
} );

/** Worker every 10 minutes; daily planner at the chosen hour (Cairo time). */
function itsoc_schedule() {
	if ( ! wp_next_scheduled( 'itsoc_tick' ) ) {
		wp_schedule_event( time() + 60, 'itsoc_10min', 'itsoc_tick' );
	}
	wp_clear_scheduled_hook( 'itsoc_daily' );
	$at = new DateTime( 'today ' . (int) itsoc_opt( 'daily_hour' ) . ':00', wp_timezone() );
	if ( $at->getTimestamp() < time() ) {
		$at->modify( '+1 day' );
	}
	wp_schedule_event( $at->getTimestamp(), 'daily', 'itsoc_daily' );
}

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'itsoc_tick' ) || ! wp_next_scheduled( 'itsoc_daily' ) ) {
		itsoc_schedule();
	}
} );
