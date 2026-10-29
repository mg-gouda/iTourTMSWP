<?php
/**
 * Dev smoke check: run migrations and exercise core services.
 * Usage: docker cp tools/dev-check.php itourwp-wp:/var/www/html/ && bin/dev.sh wp eval-file dev-check.php
 *
 * @package ITour
 */

( new \ITour\Core\Migrator( itour()->modules() ) )->maybe_migrate();
global $wpdb;
foreach ( array( 'branches', 'currencies', 'currency_rates', 'countries', 'partners', 'sequences' ) as $t ) {
	echo esc_html( $t . ': ' . $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'itour_' . $t ) ) ) . "\n"; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
$cur = itour()->currencies();
$cur->set_rate( 'EUR', '1.08', wp_date( 'Y-m-d' ) );
echo esc_html( 'base ' . $cur->base() . ', 100 EUR = ' . $cur->convert( 100, 'EUR', 'USD' ) . ' USD' ) . "\n";
echo esc_html( itour()->sequences()->next( 'dev_test', 'TST' ) . ' ' . itour()->sequences()->next( 'dev_test' ) ) . "\n";
