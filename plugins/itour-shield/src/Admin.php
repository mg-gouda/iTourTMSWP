<?php
/**
 * The Shield admin screen: one form, vertical tabs, plus scan, activity and tools.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI.
 */
final class Admin {

	private const SLUG = 'itour-shield';

	/**
	 * Hooks.
	 */
	public static function hooks(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_itour_shield_save', array( self::class, 'save' ) );
		add_action( 'admin_post_itour_shield_purge', array( self::class, 'purge' ) );
		add_action( 'admin_post_itour_shield_clean', array( self::class, 'clean' ) );
		add_action( 'admin_post_itour_shield_ignore', array( self::class, 'ignore' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'plugin_action_links_itour-shield/itour-shield.php', static fn( $l ) => array_merge( array( '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'itour-shield' ) . '</a>' ), (array) $l ) );
	}

	/**
	 * Screen URL.
	 *
	 * @param string $tab Tab.
	 */
	private static function url( string $tab = '' ): string {
		return admin_url( 'admin.php?page=' . self::SLUG . ( $tab ? '&tab=' . $tab : '' ) );
	}

	/**
	 * Menu.
	 */
	public static function menu(): void {
		$bad = count( array_filter( Hardening::checks(), static fn( $c ) => ! $c['ok'] && 'bad' === $c['level'] ) );
		add_menu_page(
			__( 'Shield', 'itour-shield' ),
			__( 'Shield', 'itour-shield' ) . ( $bad ? ' <span class="awaiting-mod">' . (int) $bad . '</span>' : '' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render' ),
			'dashicons-shield',
			80
		);
	}

	/**
	 * CSS and JS on our screen only.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function assets( $hook ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		$url = plugins_url( 'assets/', ITOUR_SHIELD_FILE );
		wp_enqueue_style( 'itour-shield-admin', $url . 'admin.css', array(), ITOUR_SHIELD_VERSION );
		wp_enqueue_script( 'itour-shield-admin', $url . 'admin.js', array(), ITOUR_SHIELD_VERSION, true );
		wp_localize_script(
			'itour-shield-admin',
			'itourShield',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'itour_shield_scan' ),
				'ip'    => Log::ip(),
				'i18n'  => array(
					/* translators: 1: files checked so far, 2: total files. */
					'scanning' => __( 'Checking files… %1$s of %2$s', 'itour-shield' ),
					'failed'   => __( 'The scan stopped with an error. Reload the page and try again.', 'itour-shield' ),
					'clean'    => __( 'Permanently delete these items? This cannot be undone.', 'itour-shield' ),
				),
			)
		);
	}

	/**
	 * Settings rows per tab: key => [type, label, help, extra].
	 *
	 * @return array<string,array<string,array<int,array<string,mixed>>>>
	 */
	private static function fields(): array {
		$home = trailingslashit( home_url() );
		$site = trailingslashit( site_url() );
		$f    = static fn( string $key, string $type, string $label, string $help = '', array $extra = array() ) => array_merge( compact( 'key', 'type', 'label', 'help' ), $extra );
		return array(
			'speed'     => array(
				__( 'Page cache', 'itour-shield' )    => array(
					$f( 'cache', 'switch', __( 'Cache pages for visitors', 'itour-shield' ), __( 'Saves each page as a file the first time a guest opens it, then serves that file without running WordPress. Signed-in users always get a fresh page.', 'itour-shield' ) ),
					$f(
						'cache_ttl',
						'number',
						__( 'Keep pages for', 'itour-shield' ),
						__( 'The cache also clears itself whenever you publish, edit a menu or change a setting.', 'itour-shield' ),
						array(
							'unit' => __( 'hours', 'itour-shield' ),
							'min'  => 1,
							'max'  => 168,
							'if'   => 'cache',
						)
					),
					$f( 'cache_preload', 'switch', __( 'Refill after clearing', 'itour-shield' ), __( 'Visits the home page and your newest pages a minute after the cache clears, so real visitors get the fast copy.', 'itour-shield' ), array( 'if' => 'cache' ) ),
					$f( 'cache_exclude', 'textarea', __( 'Never cache', 'itour-shield' ), __( 'One address part per line. Any page whose address contains it is skipped. Booking, cart and account pages belong here.', 'itour-shield' ), array( 'if' => 'cache' ) ),
				),
				__( 'Lighter pages', 'itour-shield' ) => array(
					$f( 'defer_js', 'switch', __( 'Defer scripts', 'itour-shield' ), __( 'Scripts load after the page is drawn. WordPress keeps a script blocking when other code needs it early. Test your booking forms after turning this on.', 'itour-shield' ) ),
					$f( 'no_emoji', 'switch', __( 'Remove the emoji script', 'itour-shield' ), __( 'Browsers draw emoji themselves; the extra script is not needed.', 'itour-shield' ) ),
					$f( 'no_embeds', 'switch', __( 'Remove the embed script', 'itour-shield' ), __( 'Stops loading the script that lets other sites embed your posts.', 'itour-shield' ) ),
					$f( 'preconnect_fonts', 'switch', __( 'Connect early to Google Fonts', 'itour-shield' ), __( 'Only added when the page uses a Google Font.', 'itour-shield' ) ),
					$f(
						'heartbeat',
						'number',
						__( 'Editor heartbeat every', 'itour-shield' ),
						__( 'How often the editor checks in with the server. 60 halves the load of the default; 0 leaves the WordPress default.', 'itour-shield' ),
						array(
							'unit' => __( 'seconds', 'itour-shield' ),
							'min'  => 0,
							'max'  => 120,
						)
					),
				),
			),
			'paths'     => array(
				__( 'Login page', 'itour-shield' ) => array(
					$f(
						'login_slug',
						'text',
						__( 'Login address', 'itour-shield' ),
						__( 'Leave empty to keep wp-login.php. When set, wp-login.php and wp-admin show “not found” to anyone not signed in. Bookmark the new address: the old one stops working at once.', 'itour-shield' ),
						array(
							'prefix'      => $site,
							'placeholder' => 'team-login',
						)
					),
				),
				__( 'Public folder names', 'itour-shield' ) => array(
					$f( 'mask_paths', 'switch', __( 'Rename WordPress folders in public links', 'itour-shield' ), __( 'Scanners spot WordPress sites by the wp-content and wp-includes folders in the page source. Files stay where they are; only the public links change, so turning this off undoes it completely.', 'itour-shield' ), array( 'disabled' => ! Paths::can_mask() ) ),
					$f(
						'mask_content',
						'text',
						'wp-content',
						'',
						array(
							'prefix' => $home,
							'if'     => 'mask_paths',
						)
					),
					$f(
						'mask_includes',
						'text',
						'wp-includes',
						'',
						array(
							'prefix' => $home,
							'if'     => 'mask_paths',
						)
					),
					$f(
						'mask_plugins',
						'text',
						'wp-content/plugins',
						'',
						array(
							'prefix' => $home,
							'if'     => 'mask_paths',
						)
					),
					$f(
						'mask_themes',
						'text',
						'wp-content/themes',
						'',
						array(
							'prefix' => $home,
							'if'     => 'mask_paths',
						)
					),
					$f(
						'mask_uploads',
						'text',
						'wp-content/uploads',
						'',
						array(
							'prefix' => $home,
							'if'     => 'mask_paths',
						)
					),
					$f(
						'rest_prefix',
						'text',
						__( 'REST API address', 'itour-shield' ),
						__( 'Leave empty to keep wp-json. Plugins that hard-code /wp-json/ stop working when this changes.', 'itour-shield' ),
						array(
							'prefix'      => $home,
							'placeholder' => 'wp-json',
						)
					),
				),
			),
			'firewall'  => array(
				__( 'Firewall', 'itour-shield' )     => array(
					$f( 'firewall', 'switch', __( 'Check every request', 'itour-shield' ), __( 'Blocks SQL injection, path traversal, code injection and script injection in addresses, and the same in forms sent by visitors who are not signed in. Runs before other plugins load.', 'itour-shield' ) ),
					$f( 'block_bots', 'switch', __( 'Block scanner tools', 'itour-shield' ), __( 'Turns away sqlmap, WPScan, Nikto, Nuclei and similar tools by their signature.', 'itour-shield' ), array( 'if' => 'firewall' ) ),
					$f(
						'rate_limit',
						'number',
						__( 'Requests per visitor', 'itour-shield' ),
						__( 'Per minute, per IP, for visitors who are not signed in. Cached pages don’t count. 0 turns the limit off.', 'itour-shield' ),
						array(
							'unit' => __( 'a minute', 'itour-shield' ),
							'min'  => 0,
							'max'  => 10000,
							'if'   => 'firewall',
						)
					),
				),
				__( 'IP addresses', 'itour-shield' ) => array(
					$f( 'blocked_ips', 'textarea', __( 'Always block', 'itour-shield' ), __( 'One per line. Single addresses or ranges such as 203.0.113.0/24.', 'itour-shield' ), array( 'mono' => true ) ),
					$f(
						'allowed_ips',
						'textarea',
						__( 'Never block', 'itour-shield' ),
						__( 'Your office and your own connection. These skip the firewall and login lockouts.', 'itour-shield' ),
						array(
							'mono' => true,
							'myip' => true,
						)
					),
				),
			),
			'login'     => array(
				__( 'Failed logins', 'itour-shield' )    => array(
					$f( 'login_limit', 'switch', __( 'Lock out after failed attempts', 'itour-shield' ), __( 'Stops password guessing. Works for the login page, XML-RPC and the REST API.', 'itour-shield' ) ),
					$f(
						'login_attempts',
						'number',
						__( 'Attempts allowed', 'itour-shield' ),
						'',
						array(
							'unit' => __( 'per hour', 'itour-shield' ),
							'min'  => 1,
							'max'  => 50,
							'if'   => 'login_limit',
						)
					),
					$f(
						'lockout_minutes',
						'number',
						__( 'Lockout lasts', 'itour-shield' ),
						'',
						array(
							'unit' => __( 'minutes', 'itour-shield' ),
							'min'  => 1,
							'max'  => 1440,
							'if'   => 'login_limit',
						)
					),
					$f( 'generic_errors', 'switch', __( 'Don’t reveal which usernames exist', 'itour-shield' ), __( 'Login and lost-password screens give the same answer for real and made-up accounts.', 'itour-shield' ) ),
				),
				__( 'Two-factor login', 'itour-shield' ) => array(
					$f( 'two_factor', 'switch', __( 'Allow two-factor login', 'itour-shield' ), __( 'Each user turns it on in their profile with an authenticator app and gets ten backup codes.', 'itour-shield' ) ),
					$f( 'two_factor_force', 'switch', __( 'Require it for administrators', 'itour-shield' ), __( 'Administrators without it are sent to their profile to set it up before they can use the dashboard.', 'itour-shield' ), array( 'if' => 'two_factor' ) ),
				),
				__( 'Email alerts', 'itour-shield' )     => array(
					$f( 'alert_email', 'email', __( 'Send alerts to', 'itour-shield' ), __( 'Empty uses the site’s admin email.', 'itour-shield' ), array( 'placeholder' => (string) get_option( 'admin_email' ) ) ),
					$f( 'alert_lockouts', 'switch', __( 'When an IP is locked out', 'itour-shield' ) ),
					$f( 'alert_admin_login', 'switch', __( 'When an administrator signs in from a new IP', 'itour-shield' ) ),
					$f( 'alert_scan', 'switch', __( 'When the daily scan finds something new', 'itour-shield' ) ),
					$f(
						'log_days',
						'number',
						__( 'Keep the activity log for', 'itour-shield' ),
						'',
						array(
							'unit' => __( 'days', 'itour-shield' ),
							'min'  => 1,
							'max'  => 365,
						)
					),
				),
			),
			'hardening' => array(
				__( 'WordPress', 'itour-shield' ) => array(
					$f( 'no_xmlrpc', 'switch', __( 'Turn off XML-RPC', 'itour-shield' ), __( 'An old remote-publishing interface that allows hundreds of password guesses in one request. The mobile app and Jetpack need it.', 'itour-shield' ) ),
					$f( 'no_user_enum', 'switch', __( 'Hide usernames', 'itour-shield' ), __( 'Blocks ?author=1 lookups, the public REST users list and the users sitemap.', 'itour-shield' ) ),
					$f( 'no_file_edit', 'switch', __( 'Turn off the theme and plugin file editor', 'itour-shield' ), __( 'A stolen admin login can no longer write PHP from the dashboard.', 'itour-shield' ) ),
					$f( 'hide_version', 'switch', __( 'Hide the WordPress version', 'itour-shield' ), __( 'Removes it from the page source and from file links.', 'itour-shield' ) ),
					$f( 'no_app_passwords', 'switch', __( 'Turn off application passwords', 'itour-shield' ), __( 'Leave on only if no app or service signs in to this site.', 'itour-shield' ) ),
				),
				__( 'Browser', 'itour-shield' )   => array(
					$f( 'headers', 'switch', __( 'Send security headers', 'itour-shield' ), __( 'Stops other sites framing yours, MIME sniffing and full-address referrers; denies camera, microphone and location access.', 'itour-shield' ) ),
					$f( 'hsts', 'switch', __( 'Force HTTPS for a year (HSTS)', 'itour-shield' ), __( 'Browsers will refuse plain http:// for this domain and its subdomains for a year. Only turn on when every subdomain has a working certificate.', 'itour-shield' ), array( 'if' => 'headers' ) ),
				),
				__( 'Server', 'itour-shield' )    => array(
					$f( 'protect_files', 'switch', __( 'Block sensitive files', 'itour-shield' ), __( 'wp-config.php, readme.html, license.txt, debug.log, backups (.sql, .bak), .git and .env answer “forbidden”, and PHP inside wp-includes can’t be called directly.', 'itour-shield' ) ),
					$f( 'uploads_no_php', 'switch', __( 'Stop PHP running in uploads', 'itour-shield' ), __( 'An uploaded script can’t run even if an attacker slips one in.', 'itour-shield' ) ),
					$f( 'no_indexes', 'switch', __( 'Hide folder listings', 'itour-shield' ), __( 'Folders without an index file answer “forbidden” instead of listing their files.', 'itour-shield' ) ),
				),
			),
		);
	}

	/**
	 * Tabs.
	 *
	 * @return array<string,string>
	 */
	private static function tabs(): array {
		return array(
			'overview'  => __( 'Overview', 'itour-shield' ),
			'speed'     => __( 'Speed', 'itour-shield' ),
			'paths'     => __( 'Login & paths', 'itour-shield' ),
			'firewall'  => __( 'Firewall', 'itour-shield' ),
			'login'     => __( 'Login protection', 'itour-shield' ),
			'hardening' => __( 'Hardening', 'itour-shield' ),
			'scan'      => __( 'File scan', 'itour-shield' ),
			'activity'  => __( 'Activity', 'itour-shield' ),
		);
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) && isset( $tabs[ sanitize_key( $_GET['tab'] ) ] ) ? sanitize_key( $_GET['tab'] ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$s    = Settings::all();
		$f    = self::fields();
		?>
		<div class="wrap itsh">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Shield', 'itour-shield' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="itsh-form">
				<input type="hidden" name="action" value="itour_shield_save">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" id="itsh-tab">
				<?php wp_nonce_field( 'itour_shield_save' ); ?>
				<header class="itsh-head">
					<div>
						<p class="itsh-title"><?php esc_html_e( 'Shield', 'itour-shield' ); ?></p>
						<p class="itsh-summary"><?php echo wp_kses( self::summary(), array( 'strong' => array() ) ); ?></p>
					</div>
					<button type="submit" class="itsh-btn itsh-btn--primary" data-save><?php esc_html_e( 'Save changes', 'itour-shield' ); ?></button>
				</header>
				<?php self::notices(); ?>
				<div class="itsh-split">
					<nav class="itsh-nav" aria-label="<?php esc_attr_e( 'Shield sections', 'itour-shield' ); ?>">
						<?php foreach ( $tabs as $id => $label ) : ?>
							<a href="<?php echo esc_url( self::url( $id ) ); ?>" class="itsh-nav-item<?php echo $id === $tab ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $id ); ?>"<?php echo $id === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</nav>
					<div class="itsh-panels">
						<?php
						foreach ( $tabs as $id => $label ) {
							echo '<section class="itsh-panel" data-panel="' . esc_attr( $id ) . '"' . ( $id === $tab ? '' : ' hidden' ) . ' aria-label="' . esc_attr( $label ) . '">';
							if ( isset( $f[ $id ] ) ) {
								foreach ( $f[ $id ] as $group => $rows ) {
									self::group( $group, $rows, $s, $id );
								}
							}
							$extra = 'panel_' . $id;
							if ( method_exists( self::class, $extra ) ) {
								self::$extra( $s );
							}
							echo '</section>';
						}
						?>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * One sentence that says where things stand.
	 */
	private static function summary(): string {
		$checks = Hardening::checks();
		$bad    = count( array_filter( $checks, static fn( $c ) => ! $c['ok'] && 'info' !== $c['level'] ) );
		$block  = Log::count( 'blocked' );
		$locks  = Log::count( 'lockout' );
		$cache  = Settings::get( 'cache' ) ? Cache::status()['pages'] : -1;
		$parts  = array(
			$bad
				/* translators: %d: checks. */
				? sprintf( _n( '<strong>%d check</strong> needs attention', '<strong>%d checks</strong> need attention', $bad, 'itour-shield' ), $bad )
				: __( 'All checks pass', 'itour-shield' ),
			/* translators: 1: blocked requests, 2: lockouts. */
			sprintf( __( '%1$s blocked and %2$s locked out in the last 24 hours', 'itour-shield' ), number_format_i18n( $block ), number_format_i18n( $locks ) ),
			$cache < 0
				? __( 'page cache off', 'itour-shield' )
				/* translators: %s: cached pages. */
				: sprintf( _n( '%s page cached', '%s pages cached', $cache, 'itour-shield' ), number_format_i18n( $cache ) ),
		);
		return implode( ' · ', $parts );
	}

	/**
	 * A titled group of setting rows.
	 *
	 * @param string                         $title Title.
	 * @param array<int,array<string,mixed>> $rows  Rows.
	 * @param array<string,mixed>            $s     Settings.
	 * @param string                         $tab   Tab.
	 */
	private static function group( string $title, array $rows, array $s, string $tab ): void {
		echo '<div class="itsh-group"><h2 class="itsh-group-title">' . esc_html( $title ) . '</h2>';
		if ( 'paths' === $tab && __( 'Public folder names', 'itour-shield' ) === $title && ! Paths::can_mask() ) {
			echo '<p class="itsh-callout">' . esc_html__( 'Folder renaming needs an Apache server and WordPress installed in the site root with the standard wp-content folder. This site doesn’t match, so the option is off.', 'itour-shield' ) . '</p>';
		}
		if ( 'paths' === $tab && __( 'Login page', 'itour-shield' ) === $title ) {
			echo '<p class="itsh-callout">' . esc_html__( 'Your login page is now', 'itour-shield' ) . ' <a href="' . esc_url( wp_login_url() ) . '"><code>' . esc_html( wp_login_url() ) . '</code></a>. ' . esc_html__( 'Locked out? Add', 'itour-shield' ) . " <code>define( 'ITOUR_SHIELD_DISABLE_PATHS', true );</code> " . esc_html__( 'to wp-config.php.', 'itour-shield' ) . '</p>';
		}
		foreach ( $rows as $r ) {
			self::row( $r, $s );
		}
		echo '</div>';
	}

	/**
	 * One setting row.
	 *
	 * @param array<string,mixed> $r Row.
	 * @param array<string,mixed> $s Settings.
	 */
	private static function row( array $r, array $s ): void {
		$key  = (string) $r['key'];
		$id   = 'itsh-' . $key;
		$name = 'shield[' . $key . ']';
		$val  = $s[ $key ];
		$help = '' !== $r['help'] ? '<p class="itsh-help" id="' . esc_attr( $id ) . '-help">' . esc_html( (string) $r['help'] ) . '</p>' : '';
		$desc = '' !== $r['help'] ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : '';
		$if   = ! empty( $r['if'] ) ? ' data-if="' . esc_attr( (string) $r['if'] ) . '"' : '';
		echo '<div class="itsh-row itsh-row--' . esc_attr( (string) $r['type'] ) . '"' . $if . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( 'switch' === $r['type'] ) {
			$off = ! empty( $r['disabled'] );
			echo '<div class="itsh-row-text"><label class="itsh-label" for="' . esc_attr( $id ) . '">' . esc_html( (string) $r['label'] ) . '</label>' . $help . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<span class="itsh-switch"><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" role="switch" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $val && ! $off, true, false ) . disabled( $off, true, false ) . $desc . ' data-key="' . esc_attr( $key ) . '"><span aria-hidden="true"></span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<div class="itsh-row-text"><label class="itsh-label" for="' . esc_attr( $id ) . '">' . esc_html( (string) $r['label'] ) . '</label>' . $help . '</div><div class="itsh-control">'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( 'textarea' === $r['type'] ) {
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" class="itsh-input' . ( ! empty( $r['mono'] ) ? ' is-mono' : '' ) . '"' . $desc . ' spellcheck="false">' . esc_textarea( (string) $val ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput
				if ( ! empty( $r['myip'] ) ) {
					/* translators: %s: IP address. */
					echo '<button type="button" class="itsh-btn itsh-btn--quiet" data-add-ip="' . esc_attr( $id ) . '">' . esc_html( sprintf( __( 'Add my IP (%s)', 'itour-shield' ), Log::ip() ) ) . '</button>';
				}
			} else {
				$type = 'number' === $r['type'] ? 'number' : ( 'email' === $r['type'] ? 'email' : 'text' );
				echo '<div class="itsh-field">';
				if ( ! empty( $r['prefix'] ) ) {
					echo '<span class="itsh-affix"><bdi dir="ltr">' . esc_html( (string) $r['prefix'] ) . '</bdi></span>';
				}
				echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '" class="itsh-input"' . $desc // phpcs:ignore WordPress.Security.EscapeOutput
					. ( isset( $r['min'] ) ? ' min="' . (int) $r['min'] . '"' : '' )
					. ( isset( $r['max'] ) ? ' max="' . (int) $r['max'] . '"' : '' )
					. ( ! empty( $r['placeholder'] ) ? ' placeholder="' . esc_attr( (string) $r['placeholder'] ) . '"' : '' )
					. ( 'text' === $type ? ' pattern="[a-z0-9\-]*" spellcheck="false" autocomplete="off"' : '' ) . '>';
				if ( ! empty( $r['unit'] ) ) {
					echo '<span class="itsh-affix">' . esc_html( (string) $r['unit'] ) . '</span>';
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Overview: the checks list.
	 */
	private static function panel_overview(): void {
		$checks = Hardening::checks();
		$rank   = array(
			'bad'  => 0,
			'warn' => 1,
			'info' => 2,
		);
		usort( $checks, static fn( $a, $b ) => array( $a['ok'], $rank[ $a['level'] ] ) <=> array( $b['ok'], $rank[ $b['level'] ] ) );
		$scan  = Scan::result();
		$open  = count( array_filter( $scan['findings'], static fn( $x ) => 'info' !== $x['level'] && ! in_array( Scan::key( $x ), $scan['ignored'], true ) ) );
		$words = array(
			'bad'  => __( 'Fix', 'itour-shield' ),
			'warn' => __( 'Should fix', 'itour-shield' ),
			'info' => __( 'Optional', 'itour-shield' ),
		);
		echo '<div class="itsh-group"><h2 class="itsh-group-title">' . esc_html__( 'Site checks', 'itour-shield' ) . '</h2><ul class="itsh-checks">';
		foreach ( $checks as $c ) {
			$state = $c['ok'] ? 'ok' : $c['level'];
			echo '<li class="itsh-check is-' . esc_attr( $state ) . '"><span class="itsh-dot" aria-hidden="true"></span><div><p class="itsh-check-label">' . esc_html( $c['label'] ) . '</p>';
			if ( ! $c['ok'] ) {
				echo '<p class="itsh-help">' . esc_html( $c['note'] ) . '</p>';
			}
			echo '</div><span class="itsh-check-state">';
			if ( $c['ok'] ) {
				esc_html_e( 'OK', 'itour-shield' );
			} elseif ( 'profile' === $c['tab'] ) {
				echo '<a href="' . esc_url( admin_url( 'profile.php#itour-2fa' ) ) . '">' . esc_html__( 'Set up', 'itour-shield' ) . '</a>';
			} elseif ( $c['tab'] ) {
				echo '<a href="' . esc_url( self::url( $c['tab'] ) ) . '" data-tab="' . esc_attr( $c['tab'] ) . '">' . esc_html( $words[ $c['level'] ] ) . '</a>';
			} else {
				echo esc_html( $words[ $c['level'] ] );
			}
			echo '</span></li>';
		}
		echo '</ul></div>';
		echo '<div class="itsh-group"><h2 class="itsh-group-title">' . esc_html__( 'Last file scan', 'itour-shield' ) . '</h2><div class="itsh-row"><div class="itsh-row-text"><p class="itsh-label">';
		if ( $scan['time'] ) {
			/* translators: 1: time ago, 2: files, 3: problems. */
			echo esc_html( sprintf( __( '%1$s ago: %2$s files checked, %3$s need a look', 'itour-shield' ), human_time_diff( $scan['time'] ), number_format_i18n( $scan['files'] ), number_format_i18n( $open ) ) );
		} else {
			esc_html_e( 'No scan yet. The first daily scan runs within 24 hours, or start one now.', 'itour-shield' );
		}
		echo '</p></div><a class="itsh-btn" href="' . esc_url( self::url( 'scan' ) ) . '" data-tab="scan">' . esc_html__( 'Open file scan', 'itour-shield' ) . '</a></div></div>';
	}

	/**
	 * Speed: cache state and database clean-up.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private static function panel_speed( array $s ): void {
		$st     = Cache::status();
		$counts = Speed::db_counts();
		echo '<div class="itsh-group"><h2 class="itsh-group-title">' . esc_html__( 'Cache state', 'itour-shield' ) . '</h2><dl class="itsh-facts">';
		$rows = array(
			__( 'Cached pages', 'itour-shield' ) => sprintf( '%s · %s', number_format_i18n( $st['pages'] ), size_format( $st['bytes'] ) ),
			__( 'Cache loader', 'itour-shield' ) => array(
				'none'  => __( 'Not installed', 'itour-shield' ),
				'ours'  => __( 'Installed', 'itour-shield' ),
				'other' => __( 'Another caching plugin owns wp-content/advanced-cache.php. Turn that plugin off first.', 'itour-shield' ),
			)[ $st['dropin'] ],
			'WP_CACHE'                           => $st['wp_cache'] ? __( 'On', 'itour-shield' ) : ( $s['cache'] ? __( 'Off: wp-config.php is not writable. Add define( \'WP_CACHE\', true ); to it.', 'itour-shield' ) : __( 'Off', 'itour-shield' ) ),
		);
		foreach ( $rows as $k => $v ) {
			echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
		}
		echo '</dl><div class="itsh-actions"><a class="itsh-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=itour_shield_purge' ), 'itour_shield_purge' ) ) . '">' . esc_html__( 'Clear cache now', 'itour-shield' ) . '</a></div></div>';

		$labels = array(
			'revisions'  => __( 'Post revisions', 'itour-shield' ),
			'autodrafts' => __( 'Auto-drafts', 'itour-shield' ),
			'trash'      => __( 'Posts in the trash', 'itour-shield' ),
			'spam'       => __( 'Spam and trashed comments', 'itour-shield' ),
			'transients' => __( 'Expired temporary data', 'itour-shield' ),
		);
		echo '<div class="itsh-group"><h2 class="itsh-group-title">' . esc_html__( 'Database clean-up', 'itour-shield' ) . '</h2><dl class="itsh-facts">';
		foreach ( $labels as $k => $label ) {
			echo '<div><dt>' . esc_html( $label ) . '</dt><dd class="is-num">' . esc_html( number_format_i18n( $counts[ $k ] ) ) . '</dd></div>';
		}
		$total = array_sum( $counts );
		echo '</dl><div class="itsh-actions">';
		if ( $total ) {
			echo '<a class="itsh-btn itsh-btn--danger" data-confirm href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=itour_shield_clean' ), 'itour_shield_clean' ) ) . '">' . esc_html( sprintf( /* translators: %s: items. */ _n( 'Delete %s item', 'Delete %s items', $total, 'itour-shield' ), number_format_i18n( $total ) ) ) . '</a><span class="itsh-help">' . esc_html__( 'Permanent. Take a backup first if you might need old revisions.', 'itour-shield' ) . '</span>';
		} else {
			echo '<span class="itsh-help">' . esc_html__( 'Nothing to clean.', 'itour-shield' ) . '</span>';
		}
		echo '</div></div>';
	}

	/**
	 * Login protection: who has 2FA.
	 */
	private static function panel_login(): void {
		$admins = get_users(
			array(
				'role__in' => array( 'administrator' ),
				'fields'   => array( 'ID', 'user_login' ),
			)
		);
		echo '<div class="itsh-group"><h2 class="itsh-group-title">' . esc_html__( 'Administrators', 'itour-shield' ) . '</h2><table class="itsh-table"><thead><tr><th scope="col">' . esc_html__( 'User', 'itour-shield' ) . '</th><th scope="col">' . esc_html__( 'Two-factor login', 'itour-shield' ) . '</th></tr></thead><tbody>';
		foreach ( $admins as $u ) {
			$on = TwoFactor::enabled( (int) $u->ID );
			echo '<tr><td>' . esc_html( $u->user_login ) . '</td><td><span class="itsh-tag ' . ( $on ? 'is-ok' : 'is-warn' ) . '">' . esc_html( $on ? __( 'On', 'itour-shield' ) : __( 'Off', 'itour-shield' ) ) . '</span>';
			if ( get_current_user_id() === (int) $u->ID && ! $on ) {
				echo ' <a href="' . esc_url( admin_url( 'profile.php#itour-2fa' ) ) . '">' . esc_html__( 'Set up yours', 'itour-shield' ) . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * File scan.
	 */
	private static function panel_scan(): void {
		$r       = Scan::result();
		$types   = array(
			'core'    => __( 'WordPress core', 'itour-shield' ),
			'plugin'  => __( 'Plugin', 'itour-shield' ),
			'uploads' => __( 'Uploads', 'itour-shield' ),
			'malware' => __( 'Malware pattern', 'itour-shield' ),
		);
		$visible = array_filter( $r['findings'], static fn( $x ) => ! in_array( Scan::key( $x ), $r['ignored'], true ) );
		$hidden  = count( $r['findings'] ) - count( $visible );
		echo '<div class="itsh-group"><h2 class="itsh-group-title">' . esc_html__( 'File scan', 'itour-shield' ) . '</h2>';
		echo '<div class="itsh-row"><div class="itsh-row-text"><p class="itsh-label">' . esc_html__( 'Compare WordPress and plugins with the official copies and look for malware', 'itour-shield' ) . '</p><p class="itsh-help" id="itsh-scan-status" aria-live="polite">';
		if ( $r['time'] ) {
			/* translators: 1: time ago, 2: files. */
			echo esc_html( sprintf( __( 'Last run %1$s ago on %2$s files. Runs by itself once a day.', 'itour-shield' ), human_time_diff( $r['time'] ), number_format_i18n( $r['files'] ) ) );
		} else {
			esc_html_e( 'Not run yet. Takes a minute or two on most sites.', 'itour-shield' );
		}
		echo '</p><progress class="itsh-progress" id="itsh-scan-progress" max="100" value="0" hidden></progress></div><button type="button" class="itsh-btn itsh-btn--primary" id="itsh-scan-start">' . esc_html__( 'Scan now', 'itour-shield' ) . '</button></div>';
		if ( $r['time'] && ! $visible ) {
			echo '<p class="itsh-empty">' . esc_html__( 'Nothing suspicious found.', 'itour-shield' ) . '</p>';
		}
		if ( $visible ) {
			echo '<table class="itsh-table"><thead><tr><th scope="col">' . esc_html__( 'File', 'itour-shield' ) . '</th><th scope="col">' . esc_html__( 'Problem', 'itour-shield' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Action', 'itour-shield' ) . '</span></th></tr></thead><tbody>';
			foreach ( $visible as $x ) {
				echo '<tr class="is-' . esc_attr( $x['level'] ) . '"><td><code class="itsh-path">' . esc_html( '' !== $x['file'] ? $x['file'] : '—' ) . '</code><span class="itsh-meta">' . esc_html( $types[ $x['type'] ] ?? $x['type'] ) . '</span></td><td>' . esc_html( $x['note'] ) . '</td><td class="is-end">';
				if ( '' !== $x['file'] ) {
					echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=itour_shield_ignore&key=' . Scan::key( $x ) ), 'itour_shield_ignore' ) ) . '">' . esc_html__( 'Ignore', 'itour-shield' ) . '</a>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table><p class="itsh-help">' . esc_html__( 'Not sure about a file? Compare it with a fresh download of the plugin or theme, or ask your developer before deleting anything.', 'itour-shield' ) . '</p>';
		}
		if ( $hidden ) {
			/* translators: %d: ignored findings. */
			echo '<p class="itsh-help">' . esc_html( sprintf( _n( '%d ignored finding is hidden. It shows again if the file changes.', '%d ignored findings are hidden. They show again if the files change.', $hidden, 'itour-shield' ), $hidden ) ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Activity log.
	 */
	private static function panel_activity(): void {
		$types = array(
			''                  => __( 'Everything', 'itour-shield' ),
			'blocked'           => __( 'Blocked requests', 'itour-shield' ),
			'login'             => __( 'Sign-ins', 'itour-shield' ),
			'login_failed'      => __( 'Failed logins', 'itour-shield' ),
			'lockout'           => __( 'Lockouts', 'itour-shield' ),
			'two_factor'        => __( 'Two-factor changes', 'itour-shield' ),
			'two_factor_failed' => __( 'Wrong two-factor codes', 'itour-shield' ),
			'scan'              => __( 'Scans', 'itour-shield' ),
			'settings'          => __( 'Setting changes', 'itour-shield' ),
		);
		$type  = isset( $_GET['type'] ) && isset( $types[ sanitize_key( $_GET['type'] ) ] ) ? sanitize_key( $_GET['type'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows  = Log::latest( 200, $type );
		echo '<div class="itsh-group"><div class="itsh-group-head"><h2 class="itsh-group-title">' . esc_html__( 'Activity', 'itour-shield' ) . '</h2><label class="screen-reader-text" for="itsh-type">' . esc_html__( 'Show', 'itour-shield' ) . '</label><select id="itsh-type" class="itsh-input itsh-select" data-filter="' . esc_url( self::url( 'activity' ) ) . '">';
		foreach ( $types as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $type, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
		if ( ! $rows ) {
			echo '<p class="itsh-empty">' . esc_html__( 'Nothing recorded yet.', 'itour-shield' ) . '</p></div>';
			return;
		}
		echo '<table class="itsh-table"><thead><tr><th scope="col">' . esc_html__( 'When', 'itour-shield' ) . '</th><th scope="col">' . esc_html__( 'Event', 'itour-shield' ) . '</th><th scope="col">IP</th><th scope="col">' . esc_html__( 'User', 'itour-shield' ) . '</th><th scope="col">' . esc_html__( 'Details', 'itour-shield' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$t = strtotime( $row['created_at'] . ' UTC' );
			echo '<tr><td class="is-num" title="' . esc_attr( wp_date( 'Y-m-d H:i:s', $t ) ) . '">' . esc_html( wp_date( 'M j, H:i', $t ) ) . '</td><td><span class="itsh-tag is-' . esc_attr( in_array( $row['type'], array( 'blocked', 'lockout', 'two_factor_failed' ), true ) ? 'bad' : ( 'login_failed' === $row['type'] ? 'warn' : 'plain' ) ) . '">' . esc_html( $types[ $row['type'] ] ?? $row['type'] ) . '</span></td><td><code class="itsh-path">' . esc_html( $row['ip'] ) . '</code></td><td>' . esc_html( $row['user_login'] ) . '</td><td class="itsh-detail">' . esc_html( $row['detail'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Messages after an action.
	 */
	private static function notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$msg = array();
		if ( isset( $_GET['saved'] ) ) {
			$msg[] = array( 'ok', __( 'Settings saved.', 'itour-shield' ) );
		}
		if ( isset( $_GET['newlogin'] ) ) {
			/* translators: %s: login URL. */
			$msg[] = array( 'warn', sprintf( __( 'Your login page moved to %s. Bookmark it now; a copy was emailed to the site admin.', 'itour-shield' ), wp_login_url() ) );
		}
		if ( isset( $_GET['purged'] ) ) {
			$msg[] = array( 'ok', __( 'Cache cleared.', 'itour-shield' ) );
		}
		if ( isset( $_GET['cleaned'] ) ) {
			/* translators: %s: items. */
			$msg[] = array( 'ok', sprintf( __( '%s items deleted from the database.', 'itour-shield' ), number_format_i18n( absint( $_GET['cleaned'] ) ) ) );
		}
		// phpcs:enable
		$n = get_option( 'itour_shield_notice' );
		if ( 'htaccess_readonly' === $n ) {
			$msg[] = array( 'bad', __( 'The .htaccess file is not writable, so server rules (blocked files, folder names, browser caching) were not applied. Ask your host to make it writable by WordPress.', 'itour-shield' ) );
		} elseif ( 'htaccess_rollback' === $n ) {
			$msg[] = array( 'bad', __( 'The server rejected the new .htaccess rules, so the previous file was put back. Your host may not allow “Options” overrides: turn off “Hide folder listings” and save again.', 'itour-shield' ) );
		}
		delete_option( 'itour_shield_notice' );
		foreach ( $msg as $m ) {
			echo '<div class="itsh-notice is-' . esc_attr( $m[0] ) . '" role="status"><p>' . esc_html( $m[1] ) . '</p></div>';
		}
	}

	/**
	 * Save settings and apply file changes.
	 */
	public static function save(): void {
		check_admin_referer( 'itour_shield_save' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'itour-shield' ), 403 );
		}
		$before = Settings::all();
		$input  = isset( $_POST['shield'] ) && is_array( $_POST['shield'] ) ? wp_unslash( $_POST['shield'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per key in Settings::save().
		$after  = Settings::save( $input );
		Htaccess::sync();
		Cache::sync();
		if ( $after['firewall'] ) {
			Firewall::install();
		} else {
			Firewall::remove();
		}
		if ( $before['rest_prefix'] !== $after['rest_prefix'] ) {
			delete_option( 'rewrite_rules' );
		}
		$changed = array_keys( array_diff_assoc( array_map( 'strval', $after ), array_map( 'strval', $before ) ) );
		if ( $changed ) {
			Log::add( 'settings', 'Changed: ' . implode( ', ', $changed ), wp_get_current_user()->user_login );
		}
		$args = array( 'saved' => 1 );
		if ( $before['login_slug'] !== $after['login_slug'] ) {
			$url = '' !== $after['login_slug'] ? site_url( $after['login_slug'] . '/' ) : site_url( 'wp-login.php' );
			wp_mail(
				(string) get_option( 'admin_email' ),
				/* translators: %s: site name. */
				sprintf( __( '[%s] New login address', 'itour-shield' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
				/* translators: %s: login URL. */
				sprintf( __( "The login page of this site is now:\n\n%s\n\nKeep this email. If you are ever locked out, add define( 'ITOUR_SHIELD_DISABLE_PATHS', true ); to wp-config.php.", 'itour-shield' ), $url )
			);
			$args['newlogin'] = 1;
		}
		$tab = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : '';
		wp_safe_redirect( add_query_arg( $args, self::url( $tab ) ) );
		exit;
	}

	/**
	 * Clear cache.
	 */
	public static function purge(): void {
		check_admin_referer( 'itour_shield_purge' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		Cache::purge();
		$back = wp_get_referer();
		wp_safe_redirect( $back && false === strpos( $back, 'page=' . self::SLUG ) ? $back : add_query_arg( 'purged', 1, self::url( 'speed' ) ) );
		exit;
	}

	/**
	 * Database clean-up.
	 */
	public static function clean(): void {
		check_admin_referer( 'itour_shield_clean' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		wp_safe_redirect( add_query_arg( 'cleaned', Speed::db_clean(), self::url( 'speed' ) ) );
		exit;
	}

	/**
	 * Ignore a scan finding.
	 */
	public static function ignore(): void {
		check_admin_referer( 'itour_shield_ignore' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		Scan::toggle_ignore( isset( $_GET['key'] ) ? sanitize_key( $_GET['key'] ) : '' );
		wp_safe_redirect( self::url( 'scan' ) );
		exit;
	}
}
