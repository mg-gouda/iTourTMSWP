<?php
/**
 * Minimal .xlsx writer and reader for the bulk import/export (no library: ZipArchive + XML).
 * Writes text cells only (inline strings), a bold frozen header row, wrapped multi-line cells and
 * column widths. Reads the first sheet: shared or inline strings, numbers and Excel date serials.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Read and write workbooks.
 */
final class Spreadsheet {

	/**
	 * Whether this server can make and read .xlsx files.
	 */
	public static function available(): bool {
		return class_exists( '\ZipArchive' ) && function_exists( 'simplexml_load_string' );
	}

	/**
	 * Column letters for a 0-based index (0 → A, 26 → AA).
	 *
	 * @param int $i Index.
	 */
	private static function col( int $i ): string {
		$s = '';
		for ( $i++; $i > 0; $i = intdiv( $i - 1, 26 ) ) {
			$s = chr( 65 + ( $i - 1 ) % 26 ) . $s;
		}
		return $s;
	}

	/**
	 * Write a workbook.
	 *
	 * @param string                                                               $file   Target path.
	 * @param array<string,array{rows:array<int,array<int,string>>,widths?:int[]}> $sheets Sheet name => rows (first row = header).
	 */
	public static function write( string $file, array $sheets ): bool {
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$x     = static fn( string $s ): string => htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		$names = array_keys( $sheets );
		$wb    = '';
		$rels  = '';
		$types = '';
		foreach ( $names as $n => $name ) {
			$id     = $n + 1;
			$wb    .= '<sheet name="' . $x( mb_substr( $name, 0, 31 ) ) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
			$rels  .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
			$types .= '<Override PartName="/xl/worksheets/sheet' . $id . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$sheet  = $sheets[ $name ];
			$cols   = '';
			foreach ( (array) ( $sheet['widths'] ?? array() ) as $c => $w ) {
				$cols .= '<col min="' . ( $c + 1 ) . '" max="' . ( $c + 1 ) . '" width="' . (int) $w . '" customWidth="1"/>';
			}
			$data = '';
			foreach ( $sheet['rows'] as $r => $row ) {
				$data .= '<row r="' . ( $r + 1 ) . '">';
				foreach ( array_values( $row ) as $c => $v ) {
					$v = (string) $v;
					if ( '' === $v ) {
						continue;
					}
					$style = 0 === $r ? 1 : 2; // 1 = header, 2 = wrapped text.
					$data .= '<c r="' . self::col( $c ) . ( $r + 1 ) . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $x( $v ) . '</t></is></c>';
				}
				$data .= '</row>';
			}
			$zip->addFromString(
				'xl/worksheets/sheet' . $id . '.xml',
				'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
				. '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
				. ( '' !== $cols ? '<cols>' . $cols . '</cols>' : '' ) . '<sheetData>' . $data . '</sheetData></worksheet>'
			);
		}
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $types . '</Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wb . '</sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		// Styles: 0 default, 1 header (bold, white on plum, wrapped), 2 body (top-aligned, wrapped).
		$zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF660066"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs></styleSheet>' );
		return $zip->close();
	}

	/**
	 * Read the first sheet as rows of strings (gaps filled with '').
	 *
	 * @param string $file Path.
	 * @return array<int,array<int,string>>|null Null when the file isn't a readable workbook.
	 */
	public static function read( string $file ): ?array {
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			return null;
		}
		$shared = array();
		$sst    = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( $sst ) {
			$xml = simplexml_load_string( $sst );
			foreach ( $xml ? $xml->si : array() as $si ) {
				$shared[] = isset( $si->t ) ? (string) $si->t : self::text( $si );
			}
		}
		$sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		$zip->close();
		$xml = $sheet ? simplexml_load_string( $sheet ) : false;
		if ( ! $xml ) {
			return null;
		}
		$rows = array();
		foreach ( $xml->sheetData->row as $row ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XML element name.
			$cells = array();
			foreach ( $row->c as $c ) {
				preg_match( '/^([A-Z]+)/', (string) $c['r'], $m );
				$i = 0;
				foreach ( str_split( $m[1] ?? 'A' ) as $ch ) {
					$i = $i * 26 + ( ord( $ch ) - 64 );
				}
				$t = (string) $c['t'];
				if ( 's' === $t ) {
					$v = $shared[ (int) $c->v ] ?? '';
				} elseif ( 'inlineStr' === $t ) {
					$v = isset( $c->is->t ) ? (string) $c->is->t : self::text( $c->is );
				} else {
					$v = (string) $c->v;
				}
				$cells[ $i - 1 ] = str_replace( "\r\n", "\n", $v );
			}
			if ( $cells ) {
				$line                        = array_fill( 0, max( array_keys( $cells ) ) + 1, '' );
				$rows[ (int) $row['r'] - 1 ] = array_replace( $line, $cells );
			}
		}
		ksort( $rows );
		return $rows;
	}

	/**
	 * All text runs of a rich-text string element.
	 *
	 * @param \SimpleXMLElement $el Element.
	 */
	private static function text( \SimpleXMLElement $el ): string {
		$runs = $el->xpath( './/*[local-name()="t"]' );
		return implode( '', array_map( 'strval', is_array( $runs ) ? $runs : array() ) );
	}

	/**
	 * A cell as Y-m-d: accepts 2026-12-24, 24/12/2026 and Excel date serials. '' when empty or unreadable.
	 *
	 * @param string $v Cell text.
	 */
	public static function date( string $v ): string {
		$v = trim( $v );
		if ( '' === $v ) {
			return '';
		}
		if ( is_numeric( $v ) && (float) $v > 20000 && (float) $v < 80000 ) {
			return gmdate( 'Y-m-d', (int) round( ( (float) $v - 25569 ) * DAY_IN_SECONDS ) ); // Excel serial (days since 1899-12-30).
		}
		if ( preg_match( '#^(\d{1,2})[/.](\d{1,2})[/.](\d{4})$#', $v, $m ) ) {
			return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] ); // Day first, as in Egypt and Europe.
		}
		$t = strtotime( $v );
		return $t ? gmdate( 'Y-m-d', $t ) : '';
	}
}
