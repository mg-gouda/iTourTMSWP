<?php
/**
 * Plugin Name:       iTour Shield
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Speed and security in one plugin: page cache and front-end tweaks, hidden login and masked WordPress paths, firewall, login protection with two-factor codes, malware and file scan, hardening and an activity log. Works on any WordPress site.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-shield
 * Domain Path:       /languages
 *
 * @package ITour\Shield
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/autoload.php';

register_activation_hook( __FILE__, array( \ITour\Shield\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \ITour\Shield\Plugin::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( \ITour\Shield\Plugin::class, 'boot' ), 1 );
