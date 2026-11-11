<?php
/**
 * Transfer routes: one card per priced route (cheapest vehicle), airport first when the zone has one.
 *
 * @package ITour\Transfers
 *
 * @var array<string,mixed> $attributes Block attributes.
 */

use ITour\Transfers\Module;

defined( 'ABSPATH' ) || exit;

global $wpdb;
$itour_loc  = $wpdb->prefix . 'itour_locations';
$itour_rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"SELECT r.from_zone, r.to_zone, MIN(r.price) price, MAX(v.max_pax) pax,
			COALESCE((SELECT a.name FROM %i a WHERE a.parent_id = r.from_zone AND a.type = 'airport' AND a.active = 1 ORDER BY a.id LIMIT 1), f.name) from_name,
			t.name to_name, d.name dest
		FROM %i r JOIN %i v ON v.id = r.vehicle_id AND v.active = 1
		JOIN %i f ON f.id = r.from_zone JOIN %i t ON t.id = r.to_zone LEFT JOIN %i d ON d.id = f.parent_id
		GROUP BY r.from_zone, r.to_zone ORDER BY MIN(r.id) LIMIT %d",
		$itour_loc,
		$wpdb->prefix . 'itour_transfer_rates',
		$wpdb->prefix . 'itour_transfer_vehicles',
		$itour_loc,
		$itour_loc,
		$itour_loc,
		max( 1, (int) ( $attributes['count'] ?? 6 ) )
	)
);
if ( ! $itour_rows ) {
	return;
}
$itour_page = Module::page_url( 'results_page' );
$itour_page = '' !== $itour_page ? $itour_page : Module::page_url( 'listing_page' );
$itour_icon = static fn( string $d ): string => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
$itour_pin  = $itour_icon( 'M12 21s-6.75-5.6-6.75-11.25a6.75 6.75 0 0 1 13.5 0C18.75 15.4 12 21 12 21Z' );
$itour_car  = $itour_icon( 'M5 17H3.5a1 1 0 0 1-1-1v-3.5L5 9l1.6-3.2A1.5 1.5 0 0 1 8 5h8a1.5 1.5 0 0 1 1.4.8L19 9l2.5 3.5V16a1 1 0 0 1-1 1H19M5 9h14M9.5 17h5' );
$itour_ok   = $itour_icon( 'm5 12.5 4.5 4.5L19 7.5' );
$itour_tit  = (string) ( $attributes['title'] ?? '' );
$itour_sub  = (string) ( $attributes['subtitle'] ?? '' );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'itour-routes' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php if ( '' !== $itour_tit ) : ?>
		<header class="itour-routes__head"><h2><?php echo esc_html( $itour_tit ); ?></h2><?php echo '' !== $itour_sub ? '<p>' . esc_html( $itour_sub ) . '</p>' : ''; ?></header>
	<?php endif; ?>
	<ul class="itour-routes__grid">
		<?php foreach ( $itour_rows as $itour_r ) : ?>
			<li><a class="itour-routes__card" href="<?php echo esc_url( add_query_arg( array_map( 'rawurlencode', array( 'from' => (string) $itour_r->from_name, 'to' => (string) $itour_r->to_name, 'pax' => '2' ) ), $itour_page ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing ?>">
				<strong class="itour-routes__name"><?php echo esc_html( $itour_r->from_name . ' → ' . $itour_r->to_name ); ?></strong>
				<?php if ( ! empty( $itour_r->dest ) ) : ?>
					<span class="itour-routes__row"><?php echo $itour_pin . esc_html( (string) $itour_r->dest ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<?php endif; ?>
				<?php /* translators: %d: passengers. */ ?>
				<span class="itour-routes__row"><?php echo $itour_car . esc_html( sprintf( __( 'Private car, up to %d passengers', 'itour-transfers' ), (int) $itour_r->pax ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="itour-routes__row"><?php echo $itour_ok . esc_html__( 'Meet & greet, fixed price', 'itour-transfers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="itour-routes__price"><?php esc_html_e( 'From', 'itour-transfers' ); ?> <b><?php echo esc_html( itour()->currencies()->format( (float) $itour_r->price ) ); ?></b> <?php esc_html_e( 'per car', 'itour-transfers' ); ?></span>
			</a></li>
		<?php endforeach; ?>
	</ul>
	<?php if ( '' !== $itour_page ) : ?>
		<p class="itc-more"><a class="wp-element-button" href="<?php echo esc_url( $itour_page ); ?>"><?php esc_html_e( 'Book a transfer', 'itour-transfers' ); ?></a></p>
	<?php endif; ?>
</div>
