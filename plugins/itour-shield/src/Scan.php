<?php
/**
 * File scan, run in small batches so it never times out:
 * 1. WordPress core files against the official checksums (changed and unknown files).
 * 2. Plugins from WordPress.org against their published checksums.
 * 3. PHP files hidden in uploads, and images with PHP code inside.
 * 4. Known malware and web-shell patterns in every PHP file.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Scanner.
 */
final class Scan {

	private const JOB     = 'itour_shield_scan_job';
	private const RESULT  = 'itour_shield_scan';
	private const BATCH   = 400;
	private const MAXSIZE = 3145728; // Skip files over 3 MB.

	/**
	 * Malware signatures: label => pattern.
	 */
	private const SIGNATURES = array(
		'Encoded code run with eval'       => '#\beval\s*\(\s*(?:base64_decode|gzinflate|gzuncompress|gzdecode|str_rot13|strrev|hex2bin)\s*\(#i',
		'Command run from request data'    => '#\b(?:assert|system|passthru|shell_exec|exec|popen|proc_open|pcntl_exec)\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE|SERVER)\b#i',
		'Function name taken from request' => '#\$_(?:GET|POST|REQUEST|COOKIE)\s*\[[^\]]{1,40}\]\s*\(#i',
		'Eval of request data'             => '#\beval\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)#i',
		'preg_replace /e code execution'   => '#preg_replace\s*\(\s*([\'"])(.).*?\2[imsxuADU]*e[imsxuADU]*\1\s*,#',
		'Known web shell'                  => '#FilesMan|\bWSO\s+\d|c99shell|r57shell|b374k|IndoXploit|AlfaShell|Mini\s+Shell|uname\s*-a.*?\$_(?:POST|GET)#i',
		'Very long encoded blob'           => '#(?:base64_decode|gzinflate)\s*\(\s*[\'"][A-Za-z0-9+/=]{2000,}#',
		'Heavily hex-encoded string'       => '#(?:\\\\x[0-9a-fA-F]{2}){60,}#',
		'Obfuscated variable functions'    => '#\$\w+\s*=\s*[\'"](?:as|ba|cr|ev|gz|st)[\'"]\s*\.\s*[\'"]\w+[\'"]\s*\.\s*[\'"]\w+[\'"]\s*;\s*\$\w+\s*\(#',
	);

	/**
	 * Hooks.
	 */
	public static function hooks(): void {
		add_action( 'wp_ajax_itour_shield_scan', array( self::class, 'ajax' ) );
	}

	/**
	 * Last results.
	 *
	 * @return array{time:int,findings:array<int,array{type:string,file:string,note:string,level:string}>,files:int,ignored:array<int,string>}
	 */
	public static function result(): array {
		return array_merge(
			array(
				'time'     => 0,
				'findings' => array(),
				'files'    => 0,
				'ignored'  => array(),
			),
			(array) get_option( self::RESULT, array() )
		);
	}

	/**
	 * Admin AJAX: start or continue a scan, one batch per call.
	 */
	public static function ajax(): void {
		check_ajax_referer( 'itour_shield_scan' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		if ( ! empty( $_POST['start'] ) ) {
			self::start();
		}
		wp_send_json_success( self::step() );
	}

	/**
	 * Daily scan from cron; emails when something new turns up.
	 */
	public static function cron(): void {
		$before = wp_list_pluck( self::result()['findings'], 'file' );
		self::start();
		$stop = time() + 240;
		do {
			$state = self::step();
		} while ( ! $state['done'] && time() < $stop );
		$new = array_diff( wp_list_pluck( self::result()['findings'], 'file' ), $before );
		if ( $new && Settings::get( 'alert_scan' ) ) {
			Log::alert(
				__( 'scan found new problems', 'itour-shield' ),
				__( 'The daily file scan found files that need a look:', 'itour-shield' ) . "\n\n" . implode( "\n", array_slice( $new, 0, 30 ) )
			);
		}
	}

	/**
	 * Build the file list and checksums.
	 */
	public static function start(): void {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$root     = wp_normalize_path( untrailingslashit( ABSPATH ) );
		$sums     = get_core_checksums( get_bloginfo( 'version' ), get_locale() );
		$sums     = is_array( $sums ) ? $sums : get_core_checksums( get_bloginfo( 'version' ), 'en_US' );
		$findings = array();
		if ( ! is_array( $sums ) ) {
			$findings[] = self::finding( 'core', '', __( 'Could not download the official WordPress checksums, so core files were not compared.', 'itour-shield' ), 'info' );
			$sums       = array();
		}
		// Only compare core folders; wp-content entries in the list are bundled extras.
		$sums = array_filter( $sums, static fn( $f ) => 0 !== strpos( (string) $f, 'wp-content/' ), ARRAY_FILTER_USE_KEY );

		$plugin_sums = array();
		foreach ( get_plugins() as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug || 0 === strpos( $slug, 'itour-' ) ) {
				continue;
			}
			$res = wp_remote_get( 'https://downloads.wordpress.org/plugin-checksums/' . rawurlencode( $slug ) . '/' . rawurlencode( (string) $data['Version'] ) . '.json', array( 'timeout' => 10 ) );
			$doc = 200 === wp_remote_retrieve_response_code( $res ) ? json_decode( wp_remote_retrieve_body( $res ), true ) : null;
			if ( is_array( $doc ) && ! empty( $doc['files'] ) ) {
				foreach ( $doc['files'] as $f => $h ) {
					$plugin_sums[ 'wp-content/plugins/' . $slug . '/' . $f ] = (string) ( is_array( $h['md5'] ) ? $h['md5'][0] : $h['md5'] );
				}
			}
		}

		$files = array();
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			$path = substr( wp_normalize_path( $f->getPathname() ), strlen( $root ) + 1 );
			if ( preg_match( '#(^|/)(\.git|node_modules|cache)/#', $path ) || ! $f->isFile() ) {
				continue;
			}
			$in_core = 0 === strpos( $path, 'wp-admin/' ) || 0 === strpos( $path, 'wp-includes/' ) || ! str_contains( $path, '/' );
			$is_php  = (bool) preg_match( '/\.(php\d?|phtml|phar|inc)$/i', $path );
			$is_img  = (bool) preg_match( '/\.(jpe?g|png|gif|ico|webp|svg)$/i', $path );
			if ( $in_core || $is_php || ( $is_img && 0 === strpos( $path, 'wp-content/uploads/' ) ) ) {
				$files[] = $path;
			}
		}
		update_option(
			self::JOB,
			array(
				'files'       => $files,
				'pos'         => 0,
				'sums'        => $sums,
				'plugin_sums' => $plugin_sums,
				'findings'    => $findings,
			),
			false
		);
	}

	/**
	 * Scan the next batch.
	 *
	 * @return array{done:bool,pos:int,total:int}
	 */
	public static function step(): array {
		$job = get_option( self::JOB );
		if ( ! is_array( $job ) ) {
			return array(
				'done'  => true,
				'pos'   => 0,
				'total' => 0,
			);
		}
		$root  = wp_normalize_path( untrailingslashit( ABSPATH ) );
		$self  = substr( wp_normalize_path( ITOUR_SHIELD_DIR ), strlen( $root ) + 1 ) . '/';
		$total = count( $job['files'] );
		$end   = min( $total, $job['pos'] + self::BATCH );
		for ( $i = $job['pos']; $i < $end; $i++ ) {
			$path = $job['files'][ $i ];
			$full = $root . '/' . $path;
			if ( 0 === strpos( $path, $self ) || ! is_readable( $full ) ) {
				continue;
			}
			$in_core = 0 === strpos( $path, 'wp-admin/' ) || 0 === strpos( $path, 'wp-includes/' );
			if ( isset( $job['sums'][ $path ] ) ) {
				if ( md5_file( $full ) !== $job['sums'][ $path ] ) {
					$job['findings'][] = self::finding( 'core', $path, __( 'Core file differs from the official copy. Reinstall WordPress from Dashboard → Updates.', 'itour-shield' ), 'bad' );
				}
				continue; // Matches the official file: no need for signature checks.
			}
			if ( $in_core && $job['sums'] ) {
				$job['findings'][] = self::finding( 'core', $path, __( 'Unknown file inside a WordPress core folder.', 'itour-shield' ), 'warn' );
			}
			if ( isset( $job['plugin_sums'][ $path ] ) ) {
				if ( md5_file( $full ) !== $job['plugin_sums'][ $path ] ) {
					$job['findings'][] = self::finding( 'plugin', $path, __( 'Plugin file differs from the copy on WordPress.org. Reinstall the plugin.', 'itour-shield' ), 'bad' );
				}
				continue;
			}
			$uploads = 0 === strpos( $path, 'wp-content/uploads/' );
			$is_php  = (bool) preg_match( '/\.(php\d?|phtml|phar|inc)$/i', $path );
			if ( $uploads && $is_php ) {
				$job['findings'][] = self::finding( 'uploads', $path, __( 'PHP file in the uploads folder. Uploads should only hold media.', 'itour-shield' ), 'bad' );
			}
			if ( ( ! $is_php && ! $uploads ) || filesize( $full ) > self::MAXSIZE ) {
				continue;
			}
			$code = (string) file_get_contents( $full ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( ! $is_php ) {
				if ( preg_match( '/<\?php|<\?=|\beval\s*\(/i', $code ) ) {
					$job['findings'][] = self::finding( 'uploads', $path, __( 'Image file with PHP code inside.', 'itour-shield' ), 'bad' );
				}
				continue;
			}
			foreach ( self::SIGNATURES as $label => $re ) {
				if ( preg_match( $re, $code ) ) {
					$job['findings'][] = self::finding( 'malware', $path, $label, 'bad' );
					break;
				}
			}
		}
		$job['pos'] = $end;
		if ( $end >= $total ) {
			$prev = self::result();
			update_option(
				self::RESULT,
				array(
					'time'     => time(),
					'files'    => $total,
					'findings' => $job['findings'],
					'ignored'  => $prev['ignored'],
				),
				false
			);
			delete_option( self::JOB );
			$bad = count( array_filter( $job['findings'], static fn( $f ) => ! in_array( self::key( $f ), $prev['ignored'], true ) && 'info' !== $f['level'] ) );
			/* translators: 1: files, 2: problems. */
			Log::add( 'scan', sprintf( __( '%1$d files checked, %2$d need a look', 'itour-shield' ), $total, $bad ) );
		} else {
			update_option( self::JOB, $job, false );
		}
		return array(
			'done'  => $end >= $total,
			'pos'   => $end,
			'total' => $total,
		);
	}

	/**
	 * Finding record.
	 *
	 * @param string $type  core|plugin|uploads|malware.
	 * @param string $file  Path from the site root.
	 * @param string $note  What is wrong.
	 * @param string $level bad|warn|info.
	 * @return array{type:string,file:string,note:string,level:string,hash:string}
	 */
	private static function finding( string $type, string $file, string $note, string $level ): array {
		$full = trailingslashit( ABSPATH ) . $file;
		return array(
			'type'  => $type,
			'file'  => $file,
			'note'  => $note,
			'level' => $level,
			'hash'  => '' !== $file && is_file( $full ) ? (string) md5_file( $full ) : '',
		);
	}

	/**
	 * Ignore key: the file and its content, so a later change shows up again.
	 *
	 * @param array<string,string> $f Finding.
	 */
	public static function key( array $f ): string {
		return md5( $f['file'] . '|' . ( $f['hash'] ?? '' ) . '|' . $f['note'] );
	}

	/**
	 * Ignore (or stop ignoring) a finding.
	 *
	 * @param string $key Key.
	 */
	public static function toggle_ignore( string $key ): void {
		$r            = self::result();
		$r['ignored'] = in_array( $key, $r['ignored'], true ) ? array_values( array_diff( $r['ignored'], array( $key ) ) ) : array_merge( $r['ignored'], array( $key ) );
		update_option( self::RESULT, $r, false );
	}
}
