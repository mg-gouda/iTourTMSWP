<?php
/**
 * "Tour details" box on the tour package edit screen: basics, dates, itinerary, prices, extras, supplements.
 * Included lists, FAQ and gallery are in the shared core "Info" box; categories, languages and
 * suitability in the editor sidebar.
 *
 * @package ITour\Tours
 */

namespace ITour\Tours;

use ITour\Core\Admin\Rows;
use ITour\Core\Locations;

defined( 'ABSPATH' ) || exit;

/**
 * Meta box render + save.
 */
final class Editor {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'add_meta_boxes_' . Tour::TYPE, array( $this, 'box' ) );
		add_action( 'save_post_' . Tour::TYPE, array( $this, 'save' ) );
	}

	/**
	 * Register the box.
	 */
	public function box(): void {
		add_meta_box( 'itour-tour', __( 'Tour details', 'itour-tours' ), array( $this, 'render' ), Tour::TYPE, 'normal', 'high' );
	}

	/**
	 * Column sets for the repeating tables.
	 *
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	private static function cols(): array {
		$money = static fn( string $label ): array => array(
			'label' => $label,
			'type'  => 'decimal',
			'width' => '6.5em',
		);
		$date  = static fn( string $label ): array => array(
			'label' => $label,
			'type'  => 'date',
			'width' => '9.5em',
		);
		return array(
			'departures'  => array(
				'date'  => $date( __( 'Departure date', 'itour-tours' ) ),
				'seats' => array(
					'label' => __( 'Seats (0 = no limit)', 'itour-tours' ),
					'type'  => 'number',
					'width' => '9em',
				),
			),
			'itinerary'   => array(
				'title'     => array(
					'label' => __( 'Day title', 'itour-tours' ),
					'type'  => 'text',
					'width' => '16%',
				),
				'place'     => array(
					'label'   => __( 'On the map', 'itour-tours' ),
					'type'    => 'select',
					'options' => array( Locations::class, 'all_options' ),
					'width'   => '16%',
				),
				'places'    => array(
					'label' => __( 'Places visited', 'itour-tours' ),
					'type'  => 'text',
					'width' => '16%',
				),
				'meals'     => array(
					'label'   => __( 'Meals', 'itour-tours' ),
					'type'    => 'select',
					'options' => Tour::meals(),
					'width'   => '10em',
				),
				'overnight' => array(
					'label' => __( 'Overnight', 'itour-tours' ),
					'type'  => 'text',
					'width' => '13%',
				),
				'text'      => array(
					'label' => __( 'What happens', 'itour-tours' ),
					'type'  => 'textarea',
				),
			),
			'rates'       => array(
				'hotel_class' => array(
					'label'   => __( 'Hotels', 'itour-tours' ),
					'type'    => 'select',
					'options' => Tour::classes(),
					'width'   => '9em',
				),
				'season_from' => $date( __( 'Season from', 'itour-tours' ) ),
				'season_to'   => $date( __( 'Season to', 'itour-tours' ) ),
				'pax_min'     => array(
					'label'   => __( 'From travellers', 'itour-tours' ),
					'type'    => 'number',
					'default' => 1,
					'width'   => '5.5em',
				),
				'pax_max'     => array(
					'label' => __( 'To travellers', 'itour-tours' ),
					'type'  => 'number',
					'width' => '5.5em',
				),
				'adult'       => $money( __( 'Adult (sharing)', 'itour-tours' ) ),
				'child'       => $money( __( 'Child', 'itour-tours' ) ),
				'infant'      => $money( __( 'Infant', 'itour-tours' ) ),
				'single_supp' => $money( __( 'Single room +', 'itour-tours' ) ),
				'triple_red'  => $money( __( 'Triple room −', 'itour-tours' ) ),
			),
			'extras'      => array(
				'name'  => array(
					'label' => __( 'Optional extra', 'itour-tours' ),
					'type'  => 'text',
				),
				'price' => $money( __( 'Price', 'itour-tours' ) ),
				'per'   => array(
					'label'   => __( 'Charged', 'itour-tours' ),
					'type'    => 'select',
					'options' => array(
						'person'  => __( 'per person', 'itour-tours' ),
						'booking' => __( 'per booking', 'itour-tours' ),
					),
					'width'   => '9em',
				),
			),
			'supplements' => array(
				'name'   => array(
					'label' => __( 'Supplement', 'itour-tours' ),
					'type'  => 'text',
				),
				'from'   => $date( __( 'Date', 'itour-tours' ) ),
				'to'     => $date( __( 'Until (optional)', 'itour-tours' ) ),
				'adult'  => $money( __( 'Adult', 'itour-tours' ) ),
				'child'  => $money( __( 'Child', 'itour-tours' ) ),
				'infant' => $money( __( 'Infant', 'itour-tours' ) ),
			),
		);
	}

	/**
	 * The box.
	 *
	 * @param \WP_Post $post Tour package.
	 */
	public function render( \WP_Post $post ): void {
		global $wpdb;
		$c    = Tour::config( $post->ID );
		$cols = self::cols();
		$h    = static fn( string $t, string $help = '' ) => print( '<h3 class="itour-box__h">' . esc_html( $t ) . '</h3>' . ( '' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		$name = static fn( string $k ): string => 'itour_tour[' . $k . ']';
		wp_nonce_field( 'itour_tour_save', '_itour_tour_nonce' );
		echo '<style>.itour-box__h{margin:24px 0 6px;font-size:14px}.itour-box__h:first-of-type{margin-top:8px}.itour-box .widefat td{padding:4px 6px;vertical-align:top}.itour-box__row{display:flex;flex-wrap:wrap;gap:8px 28px;align-items:end;margin:8px 0}.itour-box__days label{margin-inline-end:14px;display:inline-block;min-height:24px}</style><div class="itour-box">';

		$h( __( 'Basics', 'itour-tours' ) );
		$dest = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM %i WHERE type = 'destination' AND active = 1 ORDER BY name", $wpdb->prefix . 'itour_locations' ), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		echo '<div class="itour-box__row"><label>' . esc_html__( 'Main destination', 'itour-tours' ) . '<br><select name="' . esc_attr( $name( 'destination' ) ) . '"><option value="0">' . esc_html__( '— Choose —', 'itour-tours' ) . '</option>';
		foreach ( $dest as $id => $d ) {
			echo '<option value="' . (int) $id . '"' . selected( (int) $c['destination'], (int) $id, false ) . '>' . esc_html( $d->name ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__( 'Days', 'itour-tours' ) . '<br><input type="number" min="1" max="60" class="small-text" name="' . esc_attr( $name( 'days' ) ) . '" value="' . (int) $c['days'] . '"></label>';
		echo '<label>' . esc_html__( 'Booking', 'itour-tours' ) . '<br><select name="' . esc_attr( $name( 'booking' ) ) . '">';
		foreach ( array( 'instant' => __( 'Instant booking, pay online', 'itour-tours' ), 'enquiry' => __( 'Enquiry only (you reply with a quote)', 'itour-tours' ) ) as $k => $label ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $c['booking'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__( 'Deposit (%)', 'itour-tours' ) . '<br><input type="number" min="0" max="100" class="small-text" name="' . esc_attr( $name( 'deposit' ) ) . '" value="' . (int) $c['deposit'] . '"></label>';
		echo '</div><p class="description">' . esc_html__( 'Deposit 0 = customers pay the full price at checkout. Otherwise they may pay this share now and the rest before travel.', 'itour-tours' ) . '</p>';

		$h( __( 'Dates', 'itour-tours' ) );
		echo '<p><label><input type="radio" name="' . esc_attr( $name( 'dates' ) ) . '" value="weekdays"' . checked( $c['dates'], 'weekdays', false ) . '> ' . esc_html__( 'Starts on the weekdays below (tick all seven for any day)', 'itour-tours' ) . '</label><br>';
		echo '<label><input type="radio" name="' . esc_attr( $name( 'dates' ) ) . '" value="fixed"' . checked( $c['dates'], 'fixed', false ) . '> ' . esc_html__( 'Fixed departures only (dates and seats below)', 'itour-tours' ) . '</label></p>';
		echo '<fieldset class="itour-box__days"><legend>' . esc_html__( 'Start days', 'itour-tours' ) . '</legend>';
		foreach ( range( 1, 7 ) as $d ) {
			echo '<label><input type="checkbox" name="' . esc_attr( $name( 'start_days' ) ) . '[]" value="' . (int) $d . '"' . checked( in_array( $d, array_map( 'intval', (array) $c['start_days'] ), true ), true, false ) . '> ' . esc_html( wp_date( 'l', strtotime( "monday this week +{$d} days -1 day" ) ) ) . '</label>';
		}
		echo '</fieldset>';
		echo '<p><label>' . esc_html__( 'Booking closes (days before the start)', 'itour-tours' ) . ' <input type="number" min="0" class="small-text" name="' . esc_attr( $name( 'cutoff' ) ) . '" value="' . (int) $c['cutoff'] . '"></label></p>';
		echo '<p class="description">' . esc_html__( 'Fixed departures:', 'itour-tours' ) . '</p>';
		Rows::render( $name( 'departures' ), $cols['departures'], (array) $c['departures'], __( 'Add departure', 'itour-tours' ) );

		$h( __( 'Itinerary', 'itour-tours' ), __( 'One row per day, in order. "On the map" picks a place from Location Tree (it needs latitude and longitude there) to draw the route map.', 'itour-tours' ) );
		Rows::render( $name( 'itinerary' ), $cols['itinerary'], (array) $c['itinerary'], __( 'Add day', 'itour-tours' ) );

		$h( __( 'Prices', 'itour-tours' ), __( 'Price per person sharing a double or twin room. Add rows per hotel class, season and group size: the more travellers, the lower the price. "To travellers" empty = no upper limit. Season rows override all-year rows. Single room + is added per traveller in a single room; Triple room − is taken off per traveller in a triple room.', 'itour-tours' ) );
		Rows::render( $name( 'rates' ), $cols['rates'], Tour::rates( $post->ID ), __( 'Add price row', 'itour-tours' ) );

		$h( __( 'Optional extras', 'itour-tours' ), __( 'Paid extras the customer can tick, e.g. a hot-air balloon ride or a sound and light show.', 'itour-tours' ) );
		Rows::render( $name( 'extras' ), $cols['extras'], (array) $c['extras'], __( 'Add extra', 'itour-tours' ) );

		$h( __( 'Supplements', 'itour-tours' ), __( 'Charged automatically per person when the tour dates include these dates, e.g. a Christmas Gala Meal.', 'itour-tours' ) );
		Rows::render( $name( 'supplements' ), $cols['supplements'], (array) $c['supplements'], __( 'Add supplement', 'itour-tours' ) );
		echo '</div>';
	}

	/**
	 * Save the box.
	 *
	 * @param int $post_id Post id.
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST['_itour_tour_nonce'], $_POST['itour_tour'] ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_itour_tour_nonce'] ) ), 'itour_tour_save' ) ) {
			return;
		}
		global $wpdb;
		$in   = wp_unslash( (array) $_POST['itour_tour'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each part sanitized below.
		$cols = self::cols();
		$deps = Rows::clean( $cols['departures'], $in['departures'] ?? array(), array( 'date' ) );
		usort( $deps, static fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );
		update_post_meta(
			$post_id,
			Tour::META,
			array(
				'destination' => absint( $in['destination'] ?? 0 ),
				'days'        => max( 1, min( 60, absint( $in['days'] ?? 1 ) ) ),
				'booking'     => 'enquiry' === ( $in['booking'] ?? '' ) ? 'enquiry' : 'instant',
				'deposit'     => min( 100, absint( $in['deposit'] ?? 0 ) ),
				'dates'       => 'fixed' === ( $in['dates'] ?? '' ) ? 'fixed' : 'weekdays',
				'start_days'  => array_values( array_intersect( range( 1, 7 ), array_map( 'intval', (array) ( $in['start_days'] ?? array() ) ) ) ),
				'cutoff'      => absint( $in['cutoff'] ?? 7 ),
				'departures'  => $deps,
				'itinerary'   => Rows::clean( $cols['itinerary'], $in['itinerary'] ?? array(), array( 'title' ) ),
				'extras'      => Rows::clean( $cols['extras'], $in['extras'] ?? array(), array( 'name', 'price' ) ),
				'supplements' => Rows::clean( $cols['supplements'], $in['supplements'] ?? array(), array( 'name', 'from' ) ),
			)
		);

		$table = $wpdb->prefix . 'itour_tour_rates';
		$wpdb->delete( $table, array( 'post_id' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( Rows::clean( $cols['rates'], $in['rates'] ?? array(), array( 'adult' ) ) as $row ) {
			foreach ( $row as $k => $v ) {
				$row[ $k ] = '' !== $v || 'hotel_class' === $k ? $v : ( str_starts_with( $k, 'season_' ) ? null : 0 ); // Empty season = all year; empty number = 0.
			}
			$wpdb->insert( $table, array( 'post_id' => $post_id ) + $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		update_post_meta( $post_id, '_itour_price_from', Tour::from_price( $post_id ) ); // For sorting lists by price.
	}
}
