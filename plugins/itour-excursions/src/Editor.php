<?php
/**
 * "Trip details" box on the excursion edit screen: schedule, pick-up, prices, add-ons, supplements.
 *
 * @package ITour\Excursions
 */

namespace ITour\Excursions;

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
		add_action( 'add_meta_boxes_' . Excursion::TYPE, array( $this, 'box' ) );
		add_action( 'save_post_' . Excursion::TYPE, array( $this, 'save' ) );
	}

	/**
	 * Register the box.
	 */
	public function box(): void {
		add_meta_box( 'itour-excursion', __( 'Trip details', 'itour-excursions' ), array( $this, 'render' ), Excursion::TYPE, 'normal', 'high' );
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
			'width' => '7em',
		);
		return array(
			'rates'       => array(
				'variant'     => array(
					'label'   => __( 'Type', 'itour-excursions' ),
					'type'    => 'select',
					'options' => Excursion::variants(),
					'width'   => '10em',
				),
				'season_from' => array(
					'label' => __( 'Season from', 'itour-excursions' ),
					'type'  => 'date',
				),
				'season_to'   => array(
					'label' => __( 'Season to', 'itour-excursions' ),
					'type'  => 'date',
				),
				'pax_min'     => array(
					'label'   => __( 'From travellers', 'itour-excursions' ),
					'type'    => 'number',
					'default' => 1,
					'width'   => '6em',
				),
				'pax_max'     => array(
					'label' => __( 'To travellers', 'itour-excursions' ),
					'type'  => 'number',
					'width' => '6em',
				),
				'adult'       => $money( __( 'Adult', 'itour-excursions' ) ),
				'child'       => $money( __( 'Child', 'itour-excursions' ) ),
				'infant'      => $money( __( 'Infant', 'itour-excursions' ) ),
			),
			'times'       => array(
				'time' => array(
					'label' => __( 'Start time', 'itour-excursions' ),
					'type'  => 'time',
				),
			),
			'blocked'     => array(
				'from' => array(
					'label' => __( 'Closed from', 'itour-excursions' ),
					'type'  => 'date',
				),
				'to'   => array(
					'label' => __( 'Closed to', 'itour-excursions' ),
					'type'  => 'date',
				),
			),
			'pickups'     => array(
				'zone_id'     => array(
					'label'   => __( 'Zone', 'itour-excursions' ),
					'type'    => 'select',
					'options' => array( Locations::class, 'zone_options' ),
				),
				'pickup_time' => array(
					'label' => __( 'Pick-up time', 'itour-excursions' ),
					'type'  => 'time',
				),
				'extra'       => $money( __( 'Extra per person', 'itour-excursions' ) ),
			),
			'addons'      => array(
				'name'  => array(
					'label' => __( 'Add-on', 'itour-excursions' ),
					'type'  => 'text',
				),
				'price' => $money( __( 'Price', 'itour-excursions' ) ),
				'per'   => array(
					'label'   => __( 'Charged', 'itour-excursions' ),
					'type'    => 'select',
					'options' => array(
						'person'  => __( 'per person', 'itour-excursions' ),
						'booking' => __( 'per booking', 'itour-excursions' ),
					),
					'width'   => '9em',
				),
			),
			'supplements' => array(
				'name'   => array(
					'label' => __( 'Supplement', 'itour-excursions' ),
					'type'  => 'text',
				),
				'from'   => array(
					'label' => __( 'Date', 'itour-excursions' ),
					'type'  => 'date',
				),
				'to'     => array(
					'label' => __( 'Until (optional)', 'itour-excursions' ),
					'type'  => 'date',
				),
				'adult'  => $money( __( 'Adult', 'itour-excursions' ) ),
				'child'  => $money( __( 'Child', 'itour-excursions' ) ),
				'infant' => $money( __( 'Infant', 'itour-excursions' ) ),
			),
		);
	}

	/**
	 * The box.
	 *
	 * @param \WP_Post $post Excursion.
	 */
	public function render( \WP_Post $post ): void {
		global $wpdb;
		$c    = Excursion::config( $post->ID );
		$cols = self::cols();
		$h    = static fn( string $t, string $help = '' ) => print( '<h3 class="itour-box__h">' . esc_html( $t ) . '</h3>' . ( '' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		wp_nonce_field( 'itour_excursion_save', '_itour_excursion_nonce' );
		echo '<style>.itour-box__h{margin:24px 0 6px;font-size:14px}.itour-box__h:first-of-type{margin-top:8px}.itour-box .widefat td{padding:4px 6px}.itour-box__days label{margin-inline-end:14px;display:inline-block;min-height:24px}</style><div class="itour-box">';

		$h( __( 'Basics', 'itour-excursions' ) );
		$dest = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM %i WHERE type = 'destination' AND active = 1 ORDER BY name", $wpdb->prefix . 'itour_locations' ), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		echo '<p><label>' . esc_html__( 'Destination', 'itour-excursions' ) . '<br><select name="itour_exc[destination]"><option value="0">' . esc_html__( '— Choose —', 'itour-excursions' ) . '</option>';
		foreach ( $dest as $id => $d ) {
			echo '<option value="' . (int) $id . '"' . selected( (int) $c['destination'], (int) $id, false ) . '>' . esc_html( $d->name ) . '</option>';
		}
		echo '</select></label> <span class="description">' . esc_html__( 'From Location Tree. Searches by destination use it.', 'itour-excursions' ) . '</span></p>';
		echo '<p><label>' . esc_html__( 'Duration', 'itour-excursions' ) . '<br><input type="text" class="regular-text" name="itour_exc[duration]" value="' . esc_attr( (string) $c['duration'] ) . '" placeholder="' . esc_attr__( 'e.g. 12 hours, or Half day', 'itour-excursions' ) . '"></label></p>';

		$h( __( 'Schedule', 'itour-excursions' ) );
		echo '<fieldset class="itour-box__days"><legend>' . esc_html__( 'Runs on', 'itour-excursions' ) . '</legend>';
		foreach ( range( 1, 7 ) as $d ) {
			echo '<label><input type="checkbox" name="itour_exc[days][]" value="' . (int) $d . '"' . checked( in_array( $d, array_map( 'intval', (array) $c['days'] ), true ), true, false ) . '> ' . esc_html( wp_date( 'l', strtotime( "monday this week +{$d} days -1 day" ) ) ) . '</label>';
		}
		echo '</fieldset>';
		Rows::render( 'itour_exc[times]', $cols['times'], array_map( static fn( $t ) => array( 'time' => $t ), (array) $c['times'] ), __( 'Add start time', 'itour-excursions' ) );
		echo '<p><label>' . esc_html__( 'Booking closes (hours before start)', 'itour-excursions' ) . ' <input type="number" min="0" class="small-text" name="itour_exc[cutoff]" value="' . (int) $c['cutoff'] . '"></label>';
		echo ' &nbsp; <label>' . esc_html__( 'Seats per day', 'itour-excursions' ) . ' <input type="number" min="0" class="small-text" name="itour_exc[seats]" value="' . (int) $c['seats'] . '"></label> <span class="description">' . esc_html__( '0 = no limit', 'itour-excursions' ) . '</span></p>';
		echo '<p><label>' . esc_html__( 'Deposit (%)', 'itour-excursions' ) . ' <input type="number" min="0" max="100" class="small-text" name="itour_exc[deposit]" value="' . (int) $c['deposit'] . '"></label> <span class="description">' . esc_html__( '0 = customers pay the full price at checkout. Otherwise they may pay this share now and the rest on the day.', 'itour-excursions' ) . '</span></p>';
		echo '<p class="description">' . esc_html__( 'Closed dates (holidays, weather):', 'itour-excursions' ) . '</p>';
		Rows::render( 'itour_exc[blocked]', $cols['blocked'], (array) $c['blocked'], __( 'Add closed dates', 'itour-excursions' ) );

		$h( __( 'Pick-up', 'itour-excursions' ) );
		$modes = array(
			'hotel'   => __( 'From hotels in the zones below', 'itour-excursions' ),
			'typed'   => __( 'Customer types their hotel or address', 'itour-excursions' ),
			'meeting' => __( 'Meeting point', 'itour-excursions' ),
		);
		echo '<fieldset class="itour-box__days">';
		foreach ( $modes as $k => $label ) {
			echo '<label><input type="checkbox" name="itour_exc[pickup_modes][]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, (array) $c['pickup_modes'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset>';
		$pickups = array_map(
			static function ( $p ) {
				$p['pickup_time'] = substr( (string) $p['pickup_time'], 0, 5 );
				return $p;
			},
			Excursion::pickups( $post->ID )
		);
		Rows::render( 'itour_exc[pickups]', $cols['pickups'], $pickups, __( 'Add zone', 'itour-excursions' ) );
		echo '<p><label>' . esc_html__( 'Meeting point', 'itour-excursions' ) . '<br><input type="text" class="large-text" name="itour_exc[meeting_point]" value="' . esc_attr( (string) $c['meeting_point'] ) . '" placeholder="' . esc_attr__( 'e.g. Marina Boulevard, in front of the pharmacy, 07:30', 'itour-excursions' ) . '"></label></p>';

		$h( __( 'Prices', 'itour-excursions' ), __( 'Price per person. Add one row per group size: the more travellers in the booking, the lower the price. "To travellers" empty = no upper limit. Rows with season dates override all-year rows on those dates. Infants count towards the group size.', 'itour-excursions' ) );
		Rows::render( 'itour_exc[rates]', $cols['rates'], Excursion::rates( $post->ID ), __( 'Add price row', 'itour-excursions' ) );

		$h( __( 'Add-ons', 'itour-excursions' ), __( 'Paid extras the customer can tick: lunch, entry tickets, a guide in their language, photos.', 'itour-excursions' ) );
		Rows::render( 'itour_exc[addons]', $cols['addons'], (array) $c['addons'], __( 'Add add-on', 'itour-excursions' ) );

		$h( __( 'Supplements', 'itour-excursions' ), __( 'Charged automatically on these dates, per person, e.g. a Christmas Gala Meal.', 'itour-excursions' ) );
		Rows::render( 'itour_exc[supplements]', $cols['supplements'], (array) $c['supplements'], __( 'Add supplement', 'itour-excursions' ) );
		echo '</div>';
	}

	/**
	 * Save the box.
	 *
	 * @param int $post_id Post id.
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST['_itour_excursion_nonce'], $_POST['itour_exc'] ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_itour_excursion_nonce'] ) ), 'itour_excursion_save' ) ) {
			return;
		}
		global $wpdb;
		$in   = wp_unslash( (array) $_POST['itour_exc'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each part sanitized below.
		$cols = self::cols();
		$cfg  = array(
			'destination'   => absint( $in['destination'] ?? 0 ),
			'duration'      => sanitize_text_field( (string) ( $in['duration'] ?? '' ) ),
			'days'          => array_values( array_intersect( range( 1, 7 ), array_map( 'intval', (array) ( $in['days'] ?? array() ) ) ) ),
			'times'         => array_values( array_unique( array_column( Rows::clean( $cols['times'], $in['times'] ?? array(), array( 'time' ) ), 'time' ) ) ),
			'cutoff'        => absint( $in['cutoff'] ?? 24 ),
			'seats'         => absint( $in['seats'] ?? 0 ),
			'deposit'       => min( 100, absint( $in['deposit'] ?? 0 ) ),
			'blocked'       => Rows::clean( $cols['blocked'], $in['blocked'] ?? array(), array( 'from' ) ),
			'pickup_modes'  => array_values( array_intersect( array( 'hotel', 'typed', 'meeting' ), (array) ( $in['pickup_modes'] ?? array() ) ) ),
			'meeting_point' => sanitize_text_field( (string) ( $in['meeting_point'] ?? '' ) ),
			'addons'        => Rows::clean( $cols['addons'], $in['addons'] ?? array(), array( 'name', 'price' ) ),
			'supplements'   => Rows::clean( $cols['supplements'], $in['supplements'] ?? array(), array( 'name', 'from' ) ),
		);
		sort( $cfg['times'] );
		update_post_meta( $post_id, Excursion::META, $cfg );

		$tables = array(
			'rates'   => array( 'adult' ),
			'pickups' => array( 'zone_id', 'pickup_time' ),
		);
		foreach ( $tables as $table => $required ) {
			$name = $wpdb->prefix . ( 'rates' === $table ? 'itour_excursion_rates' : 'itour_excursion_pickups' );
			$wpdb->delete( $name, array( 'post_id' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( Rows::clean( $cols[ $table ], $in[ $table ] ?? array(), $required ) as $row ) {
				foreach ( $row as $k => $v ) {
					$row[ $k ] = '' !== $v ? $v : ( str_starts_with( $k, 'season_' ) ? null : 0 ); // Empty season = all year; empty number = 0.
				}
				$wpdb->insert( $name, array( 'post_id' => $post_id ) + $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		update_post_meta( $post_id, '_itour_price_from', Excursion::from_price( $post_id ) ); // For sorting lists by price.
	}
}
