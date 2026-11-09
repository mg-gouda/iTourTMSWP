<?php
/**
 * Transfer rules: booking cut-off, flight number, free waiting time, extras and return discount.
 *
 * @package ITour\Transfers
 */

namespace ITour\Transfers;

use ITour\Core\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * One option, one form (iTour → Transfer rules).
 */
final class Settings {

	/**
	 * Option name.
	 */
	public const OPTION = 'itour_transfers_settings';

	/**
	 * Field definitions.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function fields(): array {
		return array(
			array(
				'key'     => 'min_hours',
				'label'   => __( 'Book at least (hours before pick-up)', 'itour-transfers' ),
				'type'    => 'number',
				'default' => 12,
			),
			array(
				'key'            => 'flight_required',
				'label'          => __( 'Flight number', 'itour-transfers' ),
				'type'           => 'checkbox',
				'checkbox_label' => __( 'Required for airport pick-ups', 'itour-transfers' ),
				'default'        => 1,
			),
			array(
				'key'     => 'wait_airport',
				'label'   => __( 'Free waiting at airports (minutes)', 'itour-transfers' ),
				'type'    => 'number',
				'default' => 60,
				'help'    => __( 'Shown to the customer.', 'itour-transfers' ),
			),
			array(
				'key'     => 'wait_hotel',
				'label'   => __( 'Free waiting at hotels (minutes)', 'itour-transfers' ),
				'type'    => 'number',
				'default' => 15,
			),
			array(
				'key'     => 'child_seat_price',
				'label'   => __( 'Child seat, each', 'itour-transfers' ),
				'type'    => 'decimal',
				'default' => '0',
				'help'    => __( '0 = free. Per seat, per trip, in your base currency.', 'itour-transfers' ),
			),
			array(
				'key'     => 'extra_bag_price',
				'label'   => __( 'Extra bag, each', 'itour-transfers' ),
				'type'    => 'decimal',
				'default' => '0',
				'help'    => __( 'For bags above the vehicle’s limit. Per bag, per trip.', 'itour-transfers' ),
			),
			array(
				'key'     => 'return_discount',
				'label'   => __( 'Return trip discount (%)', 'itour-transfers' ),
				'type'    => 'decimal',
				'default' => '0',
				'help'    => __( 'Taken off the return leg when both ways are booked together.', 'itour-transfers' ),
			),
		);
	}

	/**
	 * Saved values with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$defaults = array_column( self::fields(), 'default', 'key' );
		return array_merge( $defaults, (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Errors from the last save attempt.
	 *
	 * @var array<string,string>
	 */
	private static array $errors = array();

	/**
	 * Save before output.
	 */
	public static function load(): void {
		if ( empty( $_POST['itour_save'] ) || ! current_user_can( ( new Module() )->capability() ) ) {
			return;
		}
		check_admin_referer( 'itour_transfers_settings' );
		$raw = isset( $_POST['itour'] ) && is_array( $_POST['itour'] ) ? wp_unslash( $_POST['itour'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Fields::clean().

		list( $row, self::$errors ) = Fields::clean( self::fields(), $raw );
		foreach ( array( 'min_hours', 'wait_airport', 'wait_hotel', 'child_seat_price', 'extra_bag_price', 'return_discount' ) as $k ) {
			if ( '' !== $row[ $k ] && (float) $row[ $k ] < 0 ) {
				self::$errors[ $k ] = __( 'Can’t be negative.', 'itour-transfers' );
			}
		}
		if ( (float) $row['return_discount'] > 100 ) {
			self::$errors['return_discount'] = __( 'At most 100%.', 'itour-transfers' );
		}
		if ( self::$errors ) {
			return;
		}
		update_option( self::OPTION, $row, false );
		wp_safe_redirect( admin_url( 'admin.php?page=itour-transfers-settings&done=saved' ) );
		exit;
	}

	/**
	 * The form.
	 */
	public static function render(): void {
		echo '<div class="wrap itour"><h1>' . esc_html__( 'Transfer rules', 'itour-transfers' ) . '</h1>';
		if ( isset( $_GET['done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'itour-transfers' ) . '</p></div>';
		}
		if ( self::$errors ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Please fix the highlighted fields.', 'itour-transfers' ) . '</p></div>';
		}
		echo '<form method="post" novalidate>';
		wp_nonce_field( 'itour_transfers_settings' );
		$values = self::get();
		if ( self::$errors && isset( $_POST['itour'] ) && is_array( $_POST['itour'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in load().
			$values = array_merge( $values, array_map( 'sanitize_text_field', wp_unslash( $_POST['itour'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		Fields::table( self::fields(), $values, self::$errors );
		submit_button( __( 'Save', 'itour-transfers' ), 'primary', 'itour_save' );
		echo '</form></div>';
	}
}
