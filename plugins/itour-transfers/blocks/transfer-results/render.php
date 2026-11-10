<?php
/**
 * Transfer results: the search box, then each vehicle that fits with its one-way and return price.
 *
 * @package ITour\Transfers
 *
 * @var array<string,mixed> $attributes Block attributes.
 */

use ITour\Core\Locations;
use ITour\Transfers\Booking;
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
		// phpcs:disable WordPress.Security.NonceVerification -- which vehicle's form to open (GET link, or a POST that failed checks).
		$itour_pick = absint( $_POST['itour_transfer']['vehicle'] ?? ( $_GET['vehicle'] ?? 0 ) );
		// phpcs:enable
		$itour_zf     = Locations::zone_of( $itour_from );
		$itour_zt     = Locations::zone_of( $itour_to );
		$itour_layout = Settings::layout();
		// Best fit: the cheapest vehicle with room for a bag per traveller (quote() is cheapest first and
		// already fits the group). Shown as a badge so groups don't pick a car that is too small.
		$itour_best = 0;
		foreach ( $itour_list as $itour_row ) {
			if ( (int) $itour_row['vehicle']->max_bags >= $itour_q['pax'] ) {
				$itour_best = (int) $itour_row['vehicle']->id;
				break;
			}
		}
		/* translators: 1: from place, 2: to place, 3: number of passengers. */
		$itour_title = sprintf( _n( '%1$s → %2$s, %3$d passenger', '%1$s → %2$s, %3$d passengers', $itour_q['pax'], 'itour-transfers' ), $itour_from->name, $itour_to->name, $itour_q['pax'] );
		if ( 'summary' === $itour_layout ) {
			$itour_rows = array(
				__( 'From', 'itour-transfers' )       => $itour_from->name,
				__( 'To', 'itour-transfers' )         => $itour_to->name,
				__( 'Date', 'itour-transfers' )       => '' !== $itour_q['date'] ? wp_date( get_option( 'date_format' ), strtotime( $itour_q['date'] ) ) : '—',
				__( 'Passengers', 'itour-transfers' ) => (string) $itour_q['pax'],
			);
			$itour_out .= '<div class="itour-tr__split"><aside class="itour-tr__summary"><h2 class="itour-tr__summary-title">' . esc_html__( 'Your transfer', 'itour-transfers' ) . '</h2><dl>';
			foreach ( $itour_rows as $itour_k => $itour_val ) {
				$itour_out .= '<div><dt>' . esc_html( $itour_k ) . '</dt><dd>' . esc_html( $itour_val ) . '</dd></div>';
			}
			/* translators: 1: minutes at airports, 2: minutes at hotels. */
			$itour_out .= '</dl><p>' . esc_html( sprintf( __( 'Free waiting: %1$d minutes at airports, %2$d minutes at hotels.', 'itour-transfers' ), $itour_rules['wait_airport'], $itour_rules['wait_hotel'] ) ) . '</p><a class="itour-tr__change" href="#itour-tr-search">' . esc_html__( 'Change search', 'itour-transfers' ) . '</a></aside><div class="itour-tr__results">';
		} else {
			$itour_out .= '<h2 class="itour-tr__title">' . esc_html( $itour_title ) . '</h2>';
		}
		$itour_out .= '<ul class="itour-tr__list itour-tr__list--' . esc_attr( $itour_layout ) . '">';
		foreach ( $itour_list as $itour_row ) {
			$itour_v      = $itour_row['vehicle'];
			$itour_return = Module::price( (int) $itour_v->id, $itour_zf, $itour_zt, $itour_q['pax'], true )['total'] ?? 0;
			$itour_open   = (int) $itour_v->id === $itour_pick;
			$itour_out   .= '<li class="itour-tr__item' . ( $itour_open ? ' is-open' : '' ) . ( (int) $itour_v->id === $itour_best ? ' is-best' : '' ) . '"><div class="itour-tr__vehicle">' . ( (int) $itour_v->id === $itour_best ? '<span class="itour-tr__badge">' . esc_html__( 'Best fit for your group', 'itour-transfers' ) . '</span>' : '' ) . '<strong>' . esc_html( $itour_v->name ) . '</strong><span>'
				/* translators: 1: passengers, 2: bags. */
				. esc_html( sprintf( __( 'Up to %1$d passengers · %2$d bags', 'itour-transfers' ), $itour_v->max_pax, $itour_v->max_bags ) ) . '</span>'
				. ( '' !== $itour_q['date'] ? '<span class="itour-tr__cancel">' . esc_html( \ITour\Core\Policies::summary( \ITour\Transfers\Settings::cancel_tiers( (int) $itour_v->id ), $itour_q['date'] ) ) . '</span>' : '' ) . '</div>'
				. '<div class="itour-tr__price"><span>' . esc_html__( 'One way', 'itour-transfers' ) . '</span><strong>' . esc_html( itour()->currencies()->format( $itour_row['price'] ) ) . '</strong></div>'
				. '<div class="itour-tr__price"><span>' . esc_html__( 'Return', 'itour-transfers' ) . '</span><strong>' . esc_html( itour()->currencies()->format( (float) $itour_return ) ) . '</strong></div>';
			if ( Booking::available() && ! $itour_open ) {
				$itour_out .= '<a class="itour-tr__book wp-element-button" href="' . esc_url( add_query_arg( 'vehicle', (int) $itour_v->id ) . '#itour-tb' ) . '">' . esc_html__( 'Book', 'itour-transfers' ) . '</a>';
			}
			if ( Booking::available() && $itour_open ) {
				$itour_out .= '<div class="itour-tr__form" id="itour-tb">' . Booking::form( $itour_q, $itour_from, $itour_to, $itour_v ) . '</div>';
			}
			$itour_out .= '</li>';
		}
		$itour_out .= '</ul>';
		/* translators: 1: minutes at airports, 2: minutes at hotels. */
		$itour_out .= '<p class="itour-tr__small">' . esc_html( 'summary' === $itour_layout ? __( 'Price per vehicle, not per person.', 'itour-transfers' ) : sprintf( __( 'Price per vehicle, not per person. Free waiting: %1$d minutes at airports, %2$d minutes at hotels.', 'itour-transfers' ), $itour_rules['wait_airport'], $itour_rules['wait_hotel'] ) ) . '</p>';
		if ( 'summary' === $itour_layout ) {
			$itour_out .= '</div></div>';
		}
	}
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'itour-tr itour-tr--' . Settings::layout() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<div id="itour-tr-search"></div>
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
