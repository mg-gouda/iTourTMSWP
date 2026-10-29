<?php
/**
 * Currencies and exchange rates (replaces a currency-switcher plugin).
 *
 * Rates are stored per currency per day as "1 unit of this currency = rate × base currency".
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Currency lookups and conversion.
 */
final class Currencies {

	/**
	 * The company's base currency code.
	 */
	public function base(): string {
		$code = (string) Company::get()['base_currency'];
		return '' !== $code ? $code : 'USD';
	}

	/**
	 * Active currencies: code => "CODE — Name".
	 *
	 * @return array<string,string>
	 */
	public function options(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT code, name FROM %i WHERE active = 1 ORDER BY code', $wpdb->prefix . 'itour_currencies' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r->code ] = $r->code . ' — ' . $r->name;
		}
		return $out;
	}

	/**
	 * Rate of a currency to the base on a date (the latest rate on or before it). Null when unknown.
	 *
	 * @param string      $code ISO code.
	 * @param string|null $date Y-m-d; today when null.
	 */
	public function rate( string $code, ?string $date = null ): ?string {
		if ( $code === $this->base() ) {
			return '1';
		}
		global $wpdb;
		$rate = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT rate FROM %i WHERE code = %s AND rate_date <= %s ORDER BY rate_date DESC LIMIT 1',
				$wpdb->prefix . 'itour_currency_rates',
				$code,
				$date ?? wp_date( 'Y-m-d' )
			)
		);
		return null === $rate ? null : (string) $rate;
	}

	/**
	 * Save a rate for a day (replaces that day's rate).
	 *
	 * @param string $code   ISO code.
	 * @param string $rate   Units of base per 1 unit of $code.
	 * @param string $date   Y-m-d.
	 * @param string $source manual|api|import.
	 */
	public function set_rate( string $code, string $rate, string $date, string $source = 'manual' ): void {
		global $wpdb;
		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'itour_currency_rates',
			array(
				'code'      => $code,
				'rate_date' => $date,
				'rate'      => $rate,
				'source'    => $source,
			)
		);
	}

	/**
	 * Convert an amount between currencies via the base. Null when a rate is missing.
	 *
	 * @param float       $amount Amount in $from.
	 * @param string      $from   ISO code.
	 * @param string      $to     ISO code.
	 * @param string|null $date   Y-m-d; today when null.
	 */
	public function convert( float $amount, string $from, string $to, ?string $date = null ): ?float {
		if ( $from === $to ) {
			return $amount;
		}
		$a = $this->rate( $from, $date );
		$b = $this->rate( $to, $date );
		// ponytail: float maths is fine for display prices; itour-finance posts in exact decimals.
		return null === $a || null === $b || 0.0 === (float) $b ? null : round( $amount * (float) $a / (float) $b, 6 );
	}
}
