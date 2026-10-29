<?php
/**
 * Write the theme manual as a standalone HTML file for the ThemeForest package's documentation folder.
 * Runs inside the dev site: bin/dev.sh wp eval-file build-docs.php <output-file>
 *
 * @package ITour
 */

require_once get_template_directory() . '/inc/manual.php';

$out   = $args[0] ?? 'documentation.html';
$theme = wp_get_theme( 'wp-itourtms' );
$body  = '';
$toc   = '';
foreach ( wp_itourtms_manual() as $tab => $part ) {
	$toc  .= '<li><a href="#tab-' . esc_attr( $tab ) . '">' . esc_html( $part['title'] ) . '</a><ul>';
	$toc  .= implode( '', array_map( static fn( $s ) => '<li><a href="#' . esc_attr( $s['id'] ) . '">' . esc_html( $s['title'] ) . '</a></li>', $part['sections'] ) ) . '</ul></li>';
	$body .= '<h1 id="tab-' . esc_attr( $tab ) . '">' . esc_html( $part['title'] ) . '</h1>' . wp_itourtms_manual_sections_html( $part['sections'] );
}
$css  = 'body{margin:0 auto;max-width:860px;padding:32px 20px;font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1a2b48}h1{margin-top:2.5em;border-bottom:2px solid #e8851a;padding-bottom:6px}pre{overflow:auto;padding:12px;background:#f4f6f9;border-radius:8px}.wp-itourtms-note{padding:10px 14px;background:#fff6eb;border:1px solid #f2c48d;border-radius:8px}nav ul{padding-left:20px}a{color:#1a2b48}';
$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
	. esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ' – Documentation' ) . '</title><style>' . $css . '</style></head><body>'
	. '<p><strong>' . esc_html( $theme->get( 'Name' ) ) . '</strong> ' . esc_html( $theme->get( 'Version' ) ) . ' · ' . esc_html( $theme->get( 'Author' ) ) . '</p>'
	. '<nav aria-label="Contents"><ul>' . $toc . '</ul></nav>' . $body . '</body></html>';
file_put_contents( $out, $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI build tool.
echo esc_html( "Wrote {$out}\n" );
