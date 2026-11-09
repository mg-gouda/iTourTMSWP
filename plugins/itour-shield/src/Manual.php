<?php
/**
 * A "Speed and security" chapter in the WP iTourTMS theme's built-in manual (when that theme is active).
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Manual section.
 */
final class Manual {

	/**
	 * Hook the theme's manual filter.
	 */
	public static function hooks(): void {
		add_filter( 'wp_itourtms_manual', array( self::class, 'section' ) );
	}

	/**
	 * Add the chapter.
	 *
	 * @param array<string,mixed> $manual Manual content.
	 * @return array<string,mixed>
	 */
	public static function section( array $manual ): array {
		$manual['manual']['sections'][] = array(
			'id'     => 'itour-shield',
			'title'  => __( 'Speed and security (Shield)', 'itour-shield' ),
			'blocks' => array(
				array( 'p', __( 'Everything is under Shield in the admin menu. Start with Overview: it lists what needs attention, and each line links to the setting that fixes it.', 'itour-shield' ) ),
				array(
					'list',
					array(
						__( 'Speed: turn on the page cache. Pages are saved for visitors and cleared automatically when you publish, edit a menu or change a setting. Add booking and account pages to “Never cache”.', 'itour-shield' ),
						__( 'Login & paths: give the login page its own address. The old wp-login.php and wp-admin then show “not found” to anyone not signed in. Bookmark the new address; it is also emailed to the site admin.', 'itour-shield' ),
						__( 'Firewall: on by default. Add your office IP under “Never block” so you are never locked out.', 'itour-shield' ),
						__( 'Login protection: lockouts after failed logins, and two-factor login. Each user turns two-factor on in their own profile with an authenticator app and keeps the ten backup codes somewhere safe.', 'itour-shield' ),
						__( 'Hardening: safe switches for headers, XML-RPC, usernames and sensitive files.', 'itour-shield' ),
						__( 'File scan: runs every day and emails you when something new turns up. “Ignore” hides a finding until the file changes.', 'itour-shield' ),
						__( 'Activity: every sign-in, lockout, blocked request and setting change.', 'itour-shield' ),
					),
				),
				array( 'note', __( 'Locked out after changing the login address? Add define( \'ITOUR_SHIELD_DISABLE_PATHS\', true ); to wp-config.php, sign in at wp-login.php, fix the setting, then remove the line. Lost your phone? Use a backup code, or ask another administrator to turn two-factor off in your profile.', 'itour-shield' ) ),
				array( 'p', __( 'On Nginx servers Shield can’t write server rules itself. Copy the rules shown under Hardening → Server into the site’s Nginx config (or send them to your host). Deactivating Shield removes every file change it made.', 'itour-shield' ) ),
			),
		);
		return $manual;
	}
}
