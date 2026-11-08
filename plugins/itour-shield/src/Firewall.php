<?php
/**
 * Firewall. Runs from a tiny loader in mu-plugins, so it checks each request before
 * ordinary plugins load: IP lists, scanner bots, XML-RPC, attack patterns in the URL.
 * Once WordPress can tell who is logged in it also checks guest POSTs and the rate limit.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * Request filtering.
 */
final class Firewall {

	public const LOADER = 'itour-shield-firewall.php';

	/**
	 * Attack patterns, checked against the decoded URL and query string.
	 */
	private const RULES = array(
		'traversal' => '#(?:\.\.[/\\\\]){2,}|/etc/(?:passwd|shadow)|proc/self/environ|wp-config\.php#i',
		'wrapper'   => '#\b(?:php|data|expect|phar|zip|glob)://#i',
		'sqli'      => '#\bunion\b[\s/*+(]+(?:all[\s/*+]+)?select\b|\b(?:sleep|benchmark)\s*\(\s*\d+\s*[,)]|\binformation_schema\b|\bload_file\s*\(|\binto\s+(?:out|dump)file\b|\bwaitfor\s+delay\b#i',
		'code'      => '#\b(?:base64_decode|eval|assert|passthru|shell_exec|popen|proc_open)\s*\(#i',
		'null'      => '#\x00#',
		'xss'       => '#<\s*script\b|javascript\s*:|<[^>]+\bon(?:error|load|mouseover|focus|toggle|begin|animationstart)\s*=#i',
	);

	/**
	 * Scanner and attack-tool user agents.
	 */
	private const BOTS = '#sqlmap|nikto|nmap|masscan|wpscan|acunetix|nessus|openvas|zgrab|dirbuster|gobuster|feroxbuster|nuclei|havij|w3af|fimap|jorgee|xsser|commix|zmeu|morfeus|netsparker|arachni|whatweb|wfuzz#i';

	/**
	 * Fields never inspected (passwords may contain anything).
	 */
	private const SKIP_FIELDS = array( 'pwd', 'pass1', 'pass2', 'password', 'post_password', 'itour_shield_code' );

	/**
	 * Early checks, called by the mu-plugins loader.
	 */
	public static function run(): void {
		$s = Settings::all();
		if ( ! $s['firewall'] || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$ip = Log::ip();
		if ( self::ip_in( $ip, (string) $s['allowed_ips'] ) ) {
			return;
		}
		if ( self::ip_in( $ip, (string) $s['blocked_ips'] ) ) {
			self::deny( 'Blocked IP address' );
		}
		if ( $s['no_xmlrpc'] && defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			self::deny( 'XML-RPC is turned off' );
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only matched.
		if ( $s['block_bots'] && preg_match( self::BOTS, $ua, $m ) ) {
			self::deny( 'Scanner bot: ' . $m[0] );
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only matched.
		$hit = self::match( rawurldecode( rawurldecode( $uri ) ), array_keys( self::RULES ) );
		if ( '' !== $hit ) {
			self::deny( 'Attack pattern in URL (' . $hit . ')' );
		}
		add_action( 'plugins_loaded', array( self::class, 'guest_checks' ), -1000 );
	}

	/**
	 * Guest POST inspection and rate limit. Runs once WordPress can validate the login cookie,
	 * so a faked cookie does not skip the checks.
	 */
	public static function guest_checks(): void {
		if ( is_user_logged_in() || wp_doing_cron() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- inspection only, nothing is processed.
		if ( ! empty( $_POST ) ) {
			$hit = self::scan_values( (array) wp_unslash( $_POST ) );
			if ( '' !== $hit ) {
				self::deny( 'Attack pattern in form data (' . $hit . ')' );
			}
		}
		// phpcs:enable
		$limit = (int) Settings::get( 'rate_limit' );
		if ( $limit > 0 && self::count_request( Log::ip() ) > $limit ) {
			self::deny( 'Too many requests', 429 );
		}
	}

	/**
	 * Check every POST value (XSS rule excluded: comments and enquiries may quote code).
	 *
	 * @param array<mixed> $values Values.
	 * @return string Rule name or ''.
	 */
	private static function scan_values( array $values ): string {
		$rules = array_diff( array_keys( self::RULES ), array( 'xss' ) );
		foreach ( $values as $k => $v ) {
			if ( in_array( $k, self::SKIP_FIELDS, true ) ) {
				continue;
			}
			$hit = is_array( $v ) ? self::scan_values( $v ) : self::match( substr( (string) $v, 0, 20000 ), $rules );
			if ( '' !== $hit ) {
				return $hit;
			}
		}
		return '';
	}

	/**
	 * First matching rule.
	 *
	 * @param string            $text  Text.
	 * @param array<int,string> $rules Rule names.
	 */
	private static function match( string $text, array $rules ): string {
		foreach ( $rules as $r ) {
			if ( preg_match( self::RULES[ $r ], $text ) ) {
				return $r;
			}
		}
		return '';
	}

	/**
	 * Count this request for the IP in the current minute (a small file per IP; no DB writes).
	 *
	 * @param string $ip IP.
	 * @return int Requests so far this minute.
	 */
	private static function count_request( string $ip ): int {
		$dir = WP_CONTENT_DIR . '/cache/itour-shield/rate';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return 0;
		}
		$file   = $dir . '/' . md5( $ip . wp_salt() );
		$minute = (int) floor( time() / 60 );
		$parts  = is_readable( $file ) ? explode( '|', (string) file_get_contents( $file ) ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$count  = ( 2 === count( $parts ) && (int) $parts[0] === $minute ) ? (int) $parts[1] + 1 : 1;
		file_put_contents( $file, $minute . '|' . $count, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $count;
	}

	/**
	 * Stop the request. Logs at most once a minute per IP so floods can't fill the database.
	 *
	 * @param string $reason Why.
	 * @param int    $status HTTP status.
	 * @return never
	 */
	private static function deny( string $reason, int $status = 403 ) {
		$key = 'itour_shield_fw_' . md5( Log::ip() );
		if ( ! get_transient( $key ) ) {
			set_transient( $key, 1, MINUTE_IN_SECONDS );
			$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded, then sanitized.
			Log::add( 'blocked', $reason . ' · ' . $uri );
		}
		if ( ! headers_sent() ) {
			http_response_code( $status );
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'Cache-Control: no-store' );
			if ( 429 === $status ) {
				header( 'Retry-After: 60' );
			}
		}
		echo 429 === $status ? 'Too many requests. Please wait a minute.' : 'This request was blocked by the site firewall.';
		exit;
	}

	/**
	 * Is the IP in a list of addresses and CIDR ranges (one per line or comma separated)?
	 *
	 * @param string $ip   IP.
	 * @param string $ranges List.
	 */
	public static function ip_in( string $ip, string $ranges ): bool {
		$bin = filter_var( $ip, FILTER_VALIDATE_IP ) ? inet_pton( $ip ) : false;
		if ( false === $bin ) {
			return false;
		}
		foreach ( (array) preg_split( '/[\s,]+/', $ranges, -1, PREG_SPLIT_NO_EMPTY ) as $entry ) {
			$entry = (string) $entry;
			if ( false === strpos( $entry, '/' ) ) {
				if ( $entry === $ip ) {
					return true;
				}
				continue;
			}
			list( $net, $bits ) = explode( '/', $entry, 2 );
			$net                = filter_var( $net, FILTER_VALIDATE_IP ) ? inet_pton( $net ) : false;
			if ( false === $net || strlen( $net ) !== strlen( $bin ) ) {
				continue;
			}
			$bits  = max( 0, min( strlen( $bin ) * 8, (int) $bits ) );
			$bytes = intdiv( $bits, 8 );
			$rest  = $bits % 8;
			if ( substr( $bin, 0, $bytes ) !== substr( $net, 0, $bytes ) ) {
				continue;
			}
			$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
			if ( 0 === $rest || ( ord( $bin[ $bytes ] ) & $mask ) === ( ord( $net[ $bytes ] ) & $mask ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Path of the mu-plugins loader.
	 */
	private static function loader_file(): string {
		return ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins' ) . '/' . self::LOADER;
	}

	/**
	 * Write the mu-plugins loader.
	 */
	public static function install(): void {
		$file = self::loader_file();
		if ( ! is_dir( dirname( $file ) ) && ! wp_mkdir_p( dirname( $file ) ) ) {
			return;
		}
		$code = "<?php\n/**\n * Plugin Name: iTour Shield firewall\n * Description: Runs the iTour Shield firewall before other plugins load. Added and removed by iTour Shield.\n */\n\n"
			. "defined( 'ABSPATH' ) || exit;\n\n"
			. "if ( ! defined( 'ITOUR_SHIELD_NO_FIREWALL' ) && is_readable( WP_PLUGIN_DIR . '/itour-shield/autoload.php' ) && in_array( 'itour-shield/itour-shield.php', (array) get_option( 'active_plugins', array() ), true ) ) {\n"
			. "\trequire_once WP_PLUGIN_DIR . '/itour-shield/autoload.php';\n"
			. "\t\\ITour\\Shield\\Firewall::run();\n}\n";
		if ( ( ! is_readable( $file ) || file_get_contents( $file ) !== $code ) && wp_is_writable( is_file( $file ) ? $file : dirname( $file ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $file, $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * Remove the loader.
	 */
	public static function remove(): void {
		if ( is_file( self::loader_file() ) ) {
			wp_delete_file( self::loader_file() );
		}
	}

	/**
	 * Is the loader in place?
	 */
	public static function loaded(): bool {
		return is_file( self::loader_file() );
	}
}
