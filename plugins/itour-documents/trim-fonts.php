<?php
/**
 * Composer post-install: keep only the DejaVu Sans fonts (Latin, Greek, Cyrillic, Arabic) of mPDF; the other
 * ~90 MB of fonts cover scripts the documents never use. Documents set dejavusans as the only font.
 *
 * @package ITour\Documents
 */

// phpcs:disable -- build script, runs under Composer, not WordPress.
$dir = __DIR__ . '/vendor/mpdf/mpdf/ttfonts';
foreach ( (array) glob( $dir . '/*' ) as $f ) {
	if ( ! preg_match( '/^DejaVuSans(-Bold|-Oblique|-BoldOblique)?\.ttf$|^DejaVuinfo\.txt$/', basename( $f ) ) ) {
		unlink( $f );
	}
}
echo "mPDF fonts trimmed to DejaVu Sans\n";
