<?php
/**
 * Apache rules: one marked block at the top of the root .htaccess (before WordPress's own),
 * plus a block in uploads/.htaccess that stops PHP running there.
 * After each write the home page is requested; a 5xx answer puts the old file back.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * .htaccess writer.
 */
final class Htaccess {

	private const BEGIN = '# BEGIN iTour Shield';
	private const END   = '# END iTour Shield';

	/**
	 * Root rules for the current settings.
	 *
	 * @return array<int,string>
	 */
	public static function rules(): array {
		$s = Settings::all();
		$r = array();
		if ( $s['no_indexes'] ) {
			$r[] = 'Options -Indexes';
		}
		if ( $s['protect_files'] ) {
			array_push(
				$r,
				'<FilesMatch "^(wp-config\.php|wp-config-sample\.php|readme\.html|license\.txt|debug\.log|error_log|\.htaccess|\.user\.ini|php\.ini)$">',
				'Require all denied',
				'</FilesMatch>',
				'<FilesMatch "\.(sql|sql\.gz|bak|old|orig|save|swp|log|env)$">',
				'Require all denied',
				'</FilesMatch>',
				'<IfModule mod_rewrite.c>',
				'RewriteEngine On',
				'RewriteRule (^|/)\.(?!well-known/) - [F,L]',
				'RewriteRule ^wp-admin/includes/ - [F,L]',
				'RewriteRule ^wp-includes/[^/]+\.php$ - [F,L]',
				'RewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [F,L]',
				'RewriteRule ^wp-includes/theme-compat/ - [F,L]',
				'</IfModule>'
			);
		}
		if ( $s['no_xmlrpc'] ) {
			array_push( $r, '<Files xmlrpc.php>', 'Require all denied', '</Files>' );
		}
		$mask = Paths::rewrite_rules();
		if ( $mask ) {
			$r = array_merge( $r, array( '<IfModule mod_rewrite.c>' ), $mask, array( '</IfModule>' ) );
		}
		if ( $s['cache'] ) {
			array_push(
				$r,
				'<IfModule mod_expires.c>',
				'ExpiresActive On',
				'ExpiresByType text/css "access plus 1 year"',
				'ExpiresByType text/javascript "access plus 1 year"',
				'ExpiresByType application/javascript "access plus 1 year"',
				'ExpiresByType image/svg+xml "access plus 6 months"',
				'ExpiresByType image/webp "access plus 6 months"',
				'ExpiresByType image/avif "access plus 6 months"',
				'ExpiresByType image/jpeg "access plus 6 months"',
				'ExpiresByType image/png "access plus 6 months"',
				'ExpiresByType image/gif "access plus 6 months"',
				'ExpiresByType image/x-icon "access plus 6 months"',
				'ExpiresByType font/woff2 "access plus 1 year"',
				'ExpiresByType font/woff "access plus 1 year"',
				'</IfModule>',
				'<IfModule mod_deflate.c>',
				'AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml text/javascript application/javascript application/json application/xml image/svg+xml',
				'</IfModule>'
			);
		}
		return $r;
	}

	/**
	 * The same protections as Nginx lines, for hosts without .htaccess. Shown for copying;
	 * Shield can't write Nginx config itself.
	 */
	public static function nginx(): string {
		$s    = Settings::all();
		$base = trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		$r    = array( '# iTour Shield: paste inside this site\'s server { } block, then reload Nginx.' );
		if ( $s['no_indexes'] ) {
			$r[] = 'autoindex off;';
		}
		if ( $s['protect_files'] ) {
			array_push(
				$r,
				'location ~* /(wp-config\.php|wp-config-sample\.php|readme\.html|license\.txt|debug\.log|error_log|\.user\.ini|php\.ini)$ { deny all; }',
				'location ~* \.(sql|sql\.gz|bak|old|orig|save|swp|log|env)$ { deny all; }',
				'location ~ /\.(?!well-known/) { deny all; }',
				'location ~* ^' . $base . 'wp-admin/includes/ { deny all; }',
				'location ~* ^' . $base . 'wp-includes/[^/]+\.php$ { deny all; }',
				'location ~* ^' . $base . 'wp-includes/theme-compat/ { deny all; }'
			);
		}
		if ( $s['uploads_no_php'] ) {
			$r[] = 'location ~* ^' . $base . 'wp-content/uploads/.*\.(php\d?|phtml|phar|pht|shtml)$ { deny all; }';
		}
		if ( $s['no_xmlrpc'] ) {
			$r[] = 'location = ' . $base . 'xmlrpc.php { deny all; }';
		}
		$mask = Paths::rewrite_rules( 'nginx' );
		if ( $mask ) {
			$r[] = "# Folder names. After adding these, put define( 'ITOUR_SHIELD_NGINX', true ); in wp-config.php.";
			$r   = array_merge( $r, $mask );
		}
		return implode( "\n", $r ) . "\n";
	}

	/**
	 * Apache (or LiteSpeed). WP-CLI never looks like Apache, so there an existing .htaccess counts.
	 */
	public static function apache(): bool {
		global $is_apache;
		return $is_apache || ( defined( 'WP_CLI' ) && WP_CLI && is_file( self::root() ) );
	}

	/**
	 * Write current rules (Apache only).
	 */
	public static function sync(): void {
		if ( ! self::apache() ) {
			return;
		}
		$ok = self::write( self::root(), self::rules() );
		$up = wp_get_upload_dir();
		if ( $ok && is_dir( $up['basedir'] ) ) {
			self::write( $up['basedir'] . '/.htaccess', Settings::get( 'uploads_no_php' ) ? array( '<FilesMatch "\.(?i:php\d?|phtml|phar|pht|shtml)$">', 'Require all denied', '</FilesMatch>' ) : array() );
		}
	}

	/**
	 * Remove our blocks.
	 */
	public static function remove(): void {
		self::write( self::root(), array() );
		$up = wp_get_upload_dir();
		self::write( $up['basedir'] . '/.htaccess', array() );
	}

	/**
	 * Root .htaccess path.
	 */
	private static function root(): string {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		return get_home_path() . '.htaccess';
	}

	/**
	 * Replace our block at the top of a file; roll back on a 5xx.
	 *
	 * @param string            $file  File.
	 * @param array<int,string> $lines Lines ([] removes the block).
	 * @return bool Written (or nothing to do).
	 */
	private static function write( string $file, array $lines ): bool {
		$old  = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$rest = (string) preg_replace( '/' . preg_quote( self::BEGIN, '/' ) . '.*?' . preg_quote( self::END, '/' ) . '\R*/s', '', $old );
		$new  = $lines ? self::BEGIN . "\n# Added by the iTour Shield plugin. Edit the settings, not these lines.\n" . implode( "\n", $lines ) . "\n" . self::END . "\n\n" . $rest : $rest;
		if ( $new === $old ) {
			return true;
		}
		if ( '' === $new && is_file( $file ) && '' === trim( $old ) ) {
			return true;
		}
		if ( ( is_file( $file ) && ! wp_is_writable( $file ) ) || ( ! is_file( $file ) && ! wp_is_writable( dirname( $file ) ) ) ) {
			update_option( 'itour_shield_notice', 'htaccess_readonly', false );
			return false;
		}
		file_put_contents( $file, $new, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$res = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'   => 10,
				'sslverify' => false,
			)
		);
		if ( ! is_wp_error( $res ) && wp_remote_retrieve_response_code( $res ) >= 500 ) {
			file_put_contents( $file, $old, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			update_option( 'itour_shield_notice', 'htaccess_rollback', false );
			Log::add( 'settings', 'Server rules caused an error and were rolled back' );
			return false;
		}
		return true;
	}
}
