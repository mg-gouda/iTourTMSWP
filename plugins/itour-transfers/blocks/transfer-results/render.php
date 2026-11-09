<?php
/**
 * Transfer results: the search box, then each vehicle that fits with its one-way and return price.
 *
 * @package ITour\Transfers
 *
 * @var array<string,mixed> $attributes Block attributes.
 */

use ITour\Core\Locations;
use ITour\Transfers\Module;
use ITour\Transfers\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public search form (GET).
$itour_q = array(
	'from' => sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) ),
	'to'   => sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) ),
	'date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['date'] ?? '' ) ) ? (string) wp_unslash( $_GET['date'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- regex-checked.
	'pax'  => max( 1, min( 99, absint( $_GET['pax'] ?? 1 ) ) ),
);
// phpcs:enable
$itour_rules = Settings::get();
$itour_out   = '';

if ( '' !== $itour_q['from'] && '' !== $itour_q['to'] ) {
	$itour_from = Locations::find( $itour_q['from'] );
	$itour_to   = Locations::find( $itour_q['to'] );
	$itour_miss = array_filter( array( $itour_from ? '' : $itour_q['from'], $itour_to ? '' : $itour_q['to'] ) );
	$itour_list = $itour_miss ? array() : Module::quote( Locations::zone_of( $itour_from ), Locations::zone_of( $itour_to ), $itour_q['pax'] );

	if ( $itour_miss ) {
		/* translators: %s: place name(s) as typed. */
		$itour_out .= '<p class="itour-tr__note" role="status">' . esc_html( sprintf( __( 'We couldn’t find “%s” in our list. Pick one of the suggestions while typing, or contact us for a price.', 'itour-transfers' ), implode( '”, “', $itour_miss ) ) ) . '</p>';
	} elseif ( ! $itour_list ) {
		$itour_out .= '<p class="itour-tr__note" role="status">' . esc_html__( 'We don’t have a fixed price for this route and group size yet. Contact us and we’ll quote it.', 'itour-transfers' ) . '</p>';
	} else {
		$itour_disc = (float) $itour_rules['return_discount'];
		$itour_back = array();
		foreach ( Module::quote( Locations::zone_of( $itour_to ), Locations::zone_of( $itour_from ), $itour_q['pax'] ) as $itour_row ) {
			$itour_back[ $itour_row['vehicle']->id ] = $itour_row['price'];
		}
		/* translators: 1: from place, 2: to place, 3: number of passengers. */
		$itour_out .= '<h2 class="itour-tr__title">' . esc_html( sprintf( _n( '%1$s → %2$s, %3$d passenger', '%1$s → %2$s, %3$d passengers', $itour_q['pax'], 'itour-transfers' ), $itour_from->name, $itour_to->name, $itour_q['pax'] ) ) . '</h2>';
		$itour_out .= '<ul class="itour-tr__list">';
		foreach ( $itour_list as $itour_row ) {
			$itour_v      = $itour_row['vehicle'];
			$itour_return = $itour_row['price'] + ( $itour_back[ $itour_v->id ] ?? $itour_row['price'] ) * ( 1 - $itour_disc / 100 ); // Return leg at the reverse route's price.
			$itour_out   .= '<li class="itour-tr__item"><div class="itour-tr__vehicle"><strong>' . esc_html( $itour_v->name ) . '</strong><span>'
				/* translators: 1: passengers, 2: bags. */
				. esc_html( sprintf( __( 'Up to %1$d passengers · %2$d bags', 'itour-transfers' ), $itour_v->max_pax, $itour_v->max_bags ) ) . '</span></div>'
				. '<div class="itour-tr__price"><span>' . esc_html__( 'One way', 'itour-transfers' ) . '</span><strong>' . esc_html( itour()->currencies()->format( $itour_row['price'] ) ) . '</strong></div>'
				. '<div class="itour-tr__price"><span>' . esc_html__( 'Return', 'itour-transfers' ) . '</span><strong>' . esc_html( itour()->currencies()->format( $itour_return ) ) . '</strong></div></li>';
		}
		$itour_out .= '</ul>';
		/* translators: 1: minutes at airports, 2: minutes at hotels. */
		$itour_out .= '<p class="itour-tr__small">' . esc_html( sprintf( __( 'Price per vehicle, not per person. Free waiting: %1$d minutes at airports, %2$d minutes at hotels.', 'itour-transfers' ), $itour_rules['wait_airport'], $itour_rules['wait_hotel'] ) ) . '</p>';
	}
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'itour-tr' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php
	echo render_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block output.
		array(
			'blockName' => 'itour/booking-widget',
			'attrs'     => array( 'tabs' => array( 'transfers' ) ),
		)
	);
	echo $itour_out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	?>
</div>
