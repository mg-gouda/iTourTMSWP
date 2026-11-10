<?php
/**
 * Plugin Name:       iTour WebP
 * Plugin URI:        https://github.com/mg-gouda/iTourTMSWP
 * Description:       Serves the WebP copy ("photo.jpg.webp") of any uploaded JPG/PNG that has one, by rewriting image addresses in the page. Originals stay untouched, so deactivating restores the old links. New uploads get WebP copies automatically; for older uploads run "wp itour-webp generate".
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Mohamed Gouda
 * Author URI:        https://wa.me/+201002805139
 * License:           GPL-2.0-or-later
 * Text Domain:       itour-webp
 *
 * @package ITour\WebP
 */

defined( 'ABSPATH' ) || exit;

/**
 * An uploads address → its WebP twin when that file exists, else unchanged.
 *
 * @param string $url Address as found in the page (may be JSON-escaped).
 */
function itour_webp_swap( string $url ): string {
	static $base_path = null, $base_dir = null;
	if ( null === $base_path ) {
		$u         = wp_get_upload_dir();
		$base_path = (string) wp_parse_url( $u['baseurl'], PHP_URL_PATH );
		$base_dir  = $u['basedir'];
	}
	$clean = (string) preg_replace( '/[?#].*$/', '', $url );
	$path  = (string) wp_parse_url( str_replace( '\/', '/', $clean ), PHP_URL_PATH );
	if ( '' === $path || ! str_starts_with( $path, $base_path . '/' ) || ! file_exists( $base_dir . substr( $path, strlen( $base_path ) ) . '.webp' ) ) {
		return $url;
	}
	return $clean . '.webp'; // Keeps the original escaping ("\/" in inline JSON).
}

/**
 * Rewrite absolute, JSON-escaped and root-relative uploads JPG/PNG addresses (sub-folder installs too).
 *
 * @param string $html Page.
 */
function itour_webp_buffer( string $html ): string {
	if ( strlen( $html ) < 500 || false === stripos( $html, '<html' ) ) {
		return $html;
	}
	$host = preg_quote( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '#' );
	return (string) preg_replace_callback(
		'#(?:(?:https?:)?(?:\\\\?/){2}' . $host . ')?(?:\\\\?/[\w.\-]+)*?(?:\\\\?/)wp-content(?:\\\\?/)uploads(?:\\\\?/)[^"\'\s\)\(,<>]+?\.(?:jpe?g|png)(?=["\'\s\)\(,<>?\#&]|$)#i',
		static fn( $m ) => itour_webp_swap( $m[0] ),
		$html
	);
}

add_action(
	'template_redirect',
	static function (): void {
		if ( is_feed() || is_robots() || is_trackback() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_customize_preview() ) {
			return;
		}
		ob_start( 'itour_webp_buffer' );
	},
	1
);

/**
 * Files of an attachment: original, generated sizes, pre-scaling original.
 *
 * @param int                      $id   Attachment id.
 * @param array<string,mixed>|null $meta Metadata (null = saved metadata).
 * @return string[]
 */
function itour_webp_files( int $id, ?array $meta = null ): array {
	$file = (string) get_attached_file( $id );
	if ( '' === $file ) {
		return array();
	}
	$meta  = $meta ?? wp_get_attachment_metadata( $id );
	$meta  = is_array( $meta ) ? $meta : array();
	$files = array( $file );
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $s ) {
		$files[] = path_join( dirname( $file ), $s['file'] );
	}
	if ( ! empty( $meta['original_image'] ) ) {
		$files[] = path_join( dirname( $file ), $meta['original_image'] );
	}
	return array_unique( $files );
}

/**
 * Make WebP copies of an attachment's JPG/PNG files; a copy is kept only when smaller.
 *
 * @param int                      $id   Attachment id.
 * @param array<string,mixed>|null $meta Metadata (null = saved metadata).
 * @return int Copies made.
 */
function itour_webp_make( int $id, ?array $meta = null ): int {
	if ( ! function_exists( 'imagewebp' ) ) {
		return 0;
	}
	$made = 0;
	foreach ( itour_webp_files( $id, $meta ) as $f ) {
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $f ) || ! file_exists( $f ) || file_exists( $f . '.webp' ) ) {
			continue;
		}
		$info = @getimagesize( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- broken uploads are skipped.
		$png  = $info && IMAGETYPE_PNG === $info[2];
		$img  = $png ? @imagecreatefrompng( $f ) : @imagecreatefromjpeg( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $img ) {
			continue;
		}
		if ( $png ) {
			imagepalettetotruecolor( $img );
			imagealphablending( $img, true );
			imagesavealpha( $img, true );
		}
		if ( @imagewebp( $img, $f . '.webp', 80 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( filesize( $f . '.webp' ) >= filesize( $f ) ) {
				wp_delete_file( $f . '.webp' );
			} else {
				++$made;
			}
		}
		imagedestroy( $img );
	}
	return $made;
}

add_filter(
	'wp_generate_attachment_metadata',
	static function ( $meta, $id ) {
		itour_webp_make( (int) $id, is_array( $meta ) ? $meta : null ); // Metadata isn't saved yet: pass it.
		return $meta;
	},
	20,
	2
);

add_action(
	'delete_attachment',
	static function ( $id ): void {
		foreach ( itour_webp_files( (int) $id ) as $f ) {
			wp_delete_file( $f . '.webp' );
		}
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Make WebP copies for every JPG/PNG already in the Media Library.
	 *
	 * ## EXAMPLES
	 *
	 *     wp itour-webp generate
	 */
	WP_CLI::add_command( // @phpstan-ignore class.notFound
		'itour-webp generate',
		static function (): void {
			$ids  = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_mime_type' => 'image', // JPG/PNG filtered per file.
					'post_status'    => 'inherit',
					'numberposts'    => -1,
					'fields'         => 'ids',
				)
			);
			$made = 0;
			foreach ( $ids as $id ) {
				$made += itour_webp_make( (int) $id );
			}
			WP_CLI::success( sprintf( '%d WebP copies made for %d images.', $made, count( $ids ) ) ); // @phpstan-ignore class.notFound
		}
	);
}
