<?php
/**
 * Transfers → Rules: a tabbed screen (same panel as the product editors, saves as you go).
 * Tabs: Booking rules (cut-off, flight number, waiting time, extras, return discount, deposit) and
 * Cancellation (tiers per vehicle or for all vehicles, core Policies).
 *
 * @package ITour\Transfers
 */

namespace ITour\Transfers;

use ITour\Core\Admin\Panel;
use ITour\Core\Admin\Rows;
use ITour\Core\Policies;

defined( 'ABSPATH' ) || exit;

/**
 * Rules + cancellation tiers.
 */
final class Settings {

	/**
	 * Option with the booking rules.
	 */
	public const OPTION = 'itour_transfers_settings';

	/**
	 * Option with the cancellation tiers.
	 */
	public const CANCEL = 'itour_transfers_cancel';

	/**
	 * Defaults.
	 *
	 * @return array<string,mixed>
	 */
	private static function defaults(): array {
		return array(
			'min_hours'        => 12,
			'flight_required'  => 1,
			'wait_airport'     => 60,
			'wait_hotel'       => 15,
			'child_seat_price' => '0',
			'extra_bag_price'  => '0',
			'return_discount'  => '0',
			'deposit_percent'  => '0',
		);
	}

	/**
	 * Saved rules with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		return array_merge( self::defaults(), (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Cancellation tiers that apply to a vehicle: its own rows, else the "all vehicles" rows.
	 *
	 * @param int $vehicle Vehicle id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function cancel_tiers( int $vehicle ): array {
		$all  = (array) get_option( self::CANCEL, array() );
		$own  = array_values( array_filter( $all, static fn( $r ) => (string) $vehicle === (string) $r['vehicle'] ) );
		$base = array_values( array_filter( $all, static fn( $r ) => '' === (string) $r['vehicle'] ) );
		return $own ? $own : $base;
	}

	/**
	 * Register the screen (after modules are loaded).
	 */
	public static function register(): void {
		$cols = static fn(): array => Policies::cols( Module::vehicle_options() );
		Panel::register_screen(
			'transfers-rules',
			__( 'Transfer rules', 'itour-transfers' ),
			( new Module() )->capability(),
			array(
				'booking'      => array(
					'label'  => __( 'Booking rules', 'itour-transfers' ),
					'render' => static function (): void {
						$v   = self::get();
						$num = static fn( string $k, string $min = '0' ): string => Panel::input( 'booking', $k, $v[ $k ], 'number', array( 'min' => $min ) );
						$dec = static fn( string $k ): string => Panel::input(
							'booking',
							$k,
							$v[ $k ],
							'number',
							array(
								'min'  => '0',
								'step' => 'any',
							)
						);
						$cur = '<span class="itp-unit">' . esc_html( itour()->currencies()->base() ) . '</span>';
						// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Panel helpers escape.
						echo Panel::row( __( 'Book at least', 'itour-transfers' ), $num( 'min_hours' ) . '<span class="itp-unit">' . esc_html__( 'hours before pick-up', 'itour-transfers' ) . '</span>', __( 'Closer bookings are refused with a “please call us” message.', 'itour-transfers' ), 'itp-booking-min_hours' );
						echo Panel::row(
							__( 'Flight number for airport pick-ups', 'itour-transfers' ),
							Panel::choices(
								'booking',
								'flight_required',
								array(
									'1' => __( 'Required', 'itour-transfers' ),
									'0' => __( 'Optional', 'itour-transfers' ),
								),
								(string) (int) $v['flight_required'],
								false
							),
							__( 'Lets the driver follow delays.', 'itour-transfers' )
						);
						echo Panel::row( __( 'Free waiting at airports', 'itour-transfers' ), $num( 'wait_airport' ) . '<span class="itp-unit">' . esc_html__( 'minutes', 'itour-transfers' ) . '</span>', __( 'Shown to the customer.', 'itour-transfers' ), 'itp-booking-wait_airport' );
						echo Panel::row( __( 'Free waiting at hotels', 'itour-transfers' ), $num( 'wait_hotel' ) . '<span class="itp-unit">' . esc_html__( 'minutes', 'itour-transfers' ) . '</span>', '', 'itp-booking-wait_hotel' );
						echo Panel::row( __( 'Child seat', 'itour-transfers' ), $dec( 'child_seat_price' ) . $cur, __( 'Per seat, per trip. 0 = free.', 'itour-transfers' ), 'itp-booking-child_seat_price' );
						echo Panel::row( __( 'Extra bag', 'itour-transfers' ), $dec( 'extra_bag_price' ) . $cur, __( 'Per bag above the vehicle’s limit, per trip.', 'itour-transfers' ), 'itp-booking-extra_bag_price' );
						echo Panel::row( __( 'Return trip discount', 'itour-transfers' ), $dec( 'return_discount' ) . '<span class="itp-unit">%</span>', __( 'Taken off the return leg when both ways are booked together.', 'itour-transfers' ), 'itp-booking-return_discount' );
						echo Panel::row( __( 'Deposit', 'itour-transfers' ), $dec( 'deposit_percent' ) . '<span class="itp-unit">%</span>', __( '0 = customers pay the full price at checkout. Otherwise they may pay this share now and the rest on the day. Payment methods are set in WooCommerce → Settings → Payments.', 'itour-transfers' ), 'itp-booking-deposit_percent' );
						// phpcs:enable
					},
					'save'   => static function ( int $unused, array $in ): void {
						$pct = static fn( $x ): string => (string) min( 100, max( 0, round( (float) $x, 2 ) ) );
						$amt = static fn( $x ): string => (string) max( 0, round( (float) $x, 2 ) );
						update_option(
							self::OPTION,
							array(
								'min_hours'        => absint( $in['min_hours'] ?? 12 ),
								'flight_required'  => '1' === (string) ( $in['flight_required'] ?? '1' ) ? 1 : 0,
								'wait_airport'     => absint( $in['wait_airport'] ?? 60 ),
								'wait_hotel'       => absint( $in['wait_hotel'] ?? 15 ),
								'child_seat_price' => $amt( $in['child_seat_price'] ?? 0 ),
								'extra_bag_price'  => $amt( $in['extra_bag_price'] ?? 0 ),
								'return_discount'  => $pct( $in['return_discount'] ?? 0 ),
								'deposit_percent'  => $pct( $in['deposit_percent'] ?? 0 ),
							),
							false
						);
					},
				),
				'cancellation' => array(
					'label'  => __( 'Cancellation', 'itour-transfers' ),
					'badge'  => static fn(): string => get_option( self::CANCEL ) ? (string) count( (array) get_option( self::CANCEL ) ) : '',
					'render' => static function () use ( $cols ): void {
						echo '<div class="itp-row itp-row--wide"><div class="itp-row-text"><p class="itp-help">' . esc_html__( 'One row per tier: cancelling this many days or more before the pick-up costs this share of the price. "All vehicles" rows apply to every vehicle without rows of its own. Add travel dates for a season tier. No rows = non-refundable.', 'itour-transfers' ) . '</p></div><div class="itp-table-wrap">';
						Rows::render( Panel::name( 'cancellation', 'rows' ), $cols(), (array) get_option( self::CANCEL, array() ), __( 'Add tier', 'itour-transfers' ) );
						echo '</div></div>';
					},
					'save'   => static function ( int $unused, array $in ) use ( $cols ): void {
						update_option( self::CANCEL, Policies::clean( $cols(), $in['rows'] ?? array() ), false );
					},
				),
			)
		);
	}

	/**
	 * The admin page.
	 */
	public static function render(): void {
		Panel::render_screen( 'transfers-rules' );
	}
}
