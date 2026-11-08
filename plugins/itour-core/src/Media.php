<?php
/**
 * SVG and WebP uploads. WebP is native in WordPress; SVG is allowed for users who can upload
 * files and every SVG is sanitized on upload (SVG can carry scripts).
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP's DOM API uses camelCase.

/**
 * Upload rules and SVG sanitizing.
 */
final class Media {

	/**
	 * Elements kept in an uploaded SVG; everything else is removed.
	 */
	private const ALLOWED = array( 'svg', 'a', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'textpath', 'defs', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'pattern', 'symbol', 'use', 'title', 'desc', 'style', 'filter', 'fegaussianblur', 'feoffset', 'feblend', 'fecolormatrix', 'feflood', 'fecomposite', 'femerge', 'femergenode', 'image', 'marker', 'metadata' );

	/**
	 * Hook the upload filters.
	 */
	public function hooks(): void {
		add_filter( 'upload_mimes', array( $this, 'mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check' ), 10, 4 );
		// Both paths into the media library: browser/REST uploads and sideloads (imports, URLs, WP-CLI).
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_upload' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'sanitize_upload' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'svg_size' ), 10, 2 );
	}

	/**
	 * Allow SVG (and make sure WebP is listed).
	 *
	 * @param array<string,string> $mimes Allowed types.
	 * @return array<string,string>
	 */
	public function mimes( array $mimes ): array {
		$mimes['webp'] = 'image/webp';
		if ( current_user_can( 'upload_files' ) ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * WordPress can't sniff SVG as an image; confirm the type from the extension.
	 *
	 * @param array<string,mixed> $data     Detected data.
	 * @param string              $file     Temp path.
	 * @param string              $filename Original name.
	 * @param string[]|null       $mimes    Allowed types (unused).
	 * @return array<string,mixed>
	 */
	public function check( array $data, string $file, string $filename, $mimes ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- filter signature.
		if ( 'svg' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) && current_user_can( 'upload_files' ) ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}
		return $data;
	}

	/**
	 * Sanitize SVG uploads in place; reject files that can't be parsed.
	 *
	 * @param array<string,mixed> $file Upload.
	 * @return array<string,mixed>
	 */
	public function sanitize_upload( array $file ): array {
		if ( 'svg' !== strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ) ) {
			return $file;
		}
		$clean = self::sanitize( (string) file_get_contents( (string) $file['tmp_name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp upload.
		if ( '' === $clean ) {
			$file['error'] = __( 'This SVG file could not be read safely, so it was not uploaded.', 'itour-core' );
			return $file;
		}
		file_put_contents( (string) $file['tmp_name'], $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- rewriting the temp upload.
		return $file;
	}

	/**
	 * Remove scripts, event handlers, foreign content and external references from SVG markup.
	 *
	 * @param string $svg SVG markup.
	 * @return string Clean markup, or '' when it isn't a valid SVG.
	 */
	public static function sanitize( string $svg ): string {
		if ( '' === trim( $svg ) || preg_match( '/<!ENTITY|<!DOCTYPE[^>]*\[/i', $svg ) ) {
			return ''; // No custom entities (XXE / billion laughs).
		}
		$doc  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $doc->loadXML( $svg, LIBXML_NONET ); // No network; entities are never expanded.
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok || ! $doc->documentElement || 'svg' !== strtolower( $doc->documentElement->localName ) ) {
			return '';
		}
		$xpath = new \DOMXPath( $doc );
		foreach ( iterator_to_array( $xpath->query( '//*' ) ) as $node ) {
			if ( ! in_array( strtolower( $node->localName ), self::ALLOWED, true ) ) {
				$node->parentNode->removeChild( $node );
				continue;
			}
			foreach ( iterator_to_array( $node->attributes ) as $attr ) {
				$name  = strtolower( $attr->nodeName );
				$value = strtolower( preg_replace( '/\s+/', '', (string) $attr->nodeValue ) );
				$link  = in_array( $name, array( 'href', 'xlink:href' ), true );
				if ( 0 === strpos( $name, 'on' )
					|| false !== strpos( $value, 'javascript:' )
					|| ( $link && '' !== $value && '#' !== $value[0] && 0 !== strpos( $value, 'data:image/' ) )
					|| ( 'style' === $name && preg_match( '/expression|url\(\s*[\'"]?(?!#|data:image)/i', (string) $attr->nodeValue ) ) ) {
					$node->removeAttributeNode( $attr );
				}
			}
			if ( 'style' === strtolower( $node->localName ) && preg_match( '/@import|url\(\s*[\'"]?(?!#|data:image)/i', (string) $node->textContent ) ) {
				$node->parentNode->removeChild( $node );
			}
		}
		return (string) $doc->saveXML( $doc->documentElement );
	}

	/**
	 * Record an SVG's width and height (from its attributes or viewBox) so it displays correctly.
	 *
	 * @param array<string,mixed> $meta          Attachment metadata.
	 * @param int                 $attachment_id Attachment.
	 * @return array<string,mixed>
	 */
	public function svg_size( array $meta, int $attachment_id ): array {
		$file = (string) get_attached_file( $attachment_id );
		if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) || ! is_readable( $file ) ) {
			return $meta;
		}
		$xml = simplexml_load_string( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload.
		if ( false === $xml ) {
			return $meta;
		}
		$a = $xml->attributes();
		$w = (float) $a->width;
		$h = (float) $a->height;
		if ( ( ! $w || ! $h ) && isset( $a->viewBox ) ) {
			$box = preg_split( '/[\s,]+/', trim( (string) $a->viewBox ) );
			$w   = (float) ( $box[2] ?? 0 );
			$h   = (float) ( $box[3] ?? 0 );
		}
		if ( $w && $h ) {
			$meta['width']  = (int) round( $w );
			$meta['height'] = (int) round( $h );
			$meta['file']   = _wp_relative_upload_path( $file );
		}
		return $meta;
	}
}
