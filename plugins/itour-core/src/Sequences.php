<?php
/**
 * Document numbers (BK-2026-00001, INV-2026-00042 …), safe under concurrent requests.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Numbering per document type, reset yearly, monthly or never.
 */
final class Sequences {

	/**
	 * Next number for a document type. Creates the sequence on first use.
	 *
	 * @param string $code   Document type, e.g. `booking`.
	 * @param string $prefix Prefix used when the sequence is created, e.g. `BK`.
	 */
	public function next( string $code, string $prefix = '' ): string {
		global $wpdb;
		$t = $wpdb->prefix . 'itour_sequences';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (code, prefix, separator_char, padding, next_number, reset_policy, period) VALUES (%s, %s, %s, 5, 1, %s, %s)',
				$t,
				$code,
				'' !== $prefix ? $prefix : strtoupper( substr( $code, 0, 3 ) ),
				'-',
				'yearly',
				wp_date( 'Y' )
			)
		);
		$seq    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE code = %s', $t, $code ) );
		$period = array(
			'yearly'  => wp_date( 'Y' ),
			'monthly' => wp_date( 'Y-m' ),
		)[ $seq->reset_policy ] ?? '';
		// One atomic UPDATE: resets on a new period, then claims the number via LAST_INSERT_ID.
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET next_number = LAST_INSERT_ID(IF(period = %s, next_number, 1)) + 1, period = %s WHERE code = %s',
				$t,
				$period,
				$period,
				$code
			)
		);
		$n = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		// phpcs:enable
		$parts = array_filter(
			array( $seq->prefix, str_replace( '-', '', $period ), str_pad( (string) $n, (int) $seq->padding, '0', STR_PAD_LEFT ) ),
			static fn( $p ) => '' !== $p
		);
		return implode( $seq->separator_char, $parts );
	}
}
