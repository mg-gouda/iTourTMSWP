<?php
/**
 * Transfer booking: the form under a chosen vehicle, server-side checks and price, then WooCommerce
 * (D-005) for cart, checkout, payment and the order. One hidden "Transfer" product carries every
 * booking; its price and details ride in the cart item. A booking row is written when the order is placed.
 *
 * @package ITour\Transfers
 */

namespace ITour\Transfers;

use ITour\Core\Locations;

defined( 'ABSPATH' ) || exit;

/**
 * Booking form + WooCommerce glue.
 */
final class Booking {

	/**
	 * Cart item key holding the booking.
	 */
	private const KEY = 'itour_transfer';

	/**
	 * Option holding the hidden product's id.
	 */
	private const PRODUCT = 'itour_transfers_product';

	/**
	 * Field errors from the last submit (shown by the form).
	 *
	 * @var array<string,string>
	 */
	private static array $errors = array();

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'template_redirect', array( $this, 'submit' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'cart_prices' ) );
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_details' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'order_item' ), 10, 3 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'record' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'record' ) );
	}

	/**
	 * Whether online booking is possible (WooCommerce active).
	 */
	public static function available(): bool {
		return function_exists( 'WC' );
	}

	/**
	 * The booking form for one vehicle, with the visitor's values after a failed submit.
	 *
	 * @param array<string,mixed> $q    Search: from, to, date, pax.
	 * @param object              $from From location.
	 * @param object              $to   To location.
	 * @param object              $v    Vehicle row.
	 */
	public static function form( array $q, object $from, object $to, object $v ): string {
		$rules = Settings::get();
		$req   = empty( $rules['flight_required'] ) ? '' : ' required';
		$p     = isset( $_POST[ self::KEY ] ) && is_array( $_POST[ self::KEY ] ) ? array_map( 'sanitize_textarea_field', wp_unslash( $_POST[ self::KEY ] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- refill only; checked in submit().
		$back  = 'return' === ( $p['trip'] ?? 'one' );

		$val = static fn( string $k, $d = '' ) => esc_attr( (string) ( $p[ $k ] ?? $d ) );
		$inv = static fn( string $k ): string => isset( self::$errors[ $k ] ) ? ' aria-invalid="true" aria-describedby="itour-tb-' . esc_attr( $k ) . '-error"' : '';
		$err = static function ( string $k ): string {
			return isset( self::$errors[ $k ] ) ? '<span class="itour-tb__error" id="itour-tb-' . esc_attr( $k ) . '-error">' . esc_html( self::$errors[ $k ] ) . '</span>' : '';
		};
		$row = static function ( string $k, string $label, string $input, string $extra = '' ) use ( $err ): string {
			return '<div class="itour-tb__field ' . esc_attr( $extra ) . '"><label for="itour-tb-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label>' . $input . $err( $k ) . '</div>';
		};

		$h  = '<form method="post" class="itour-tb" novalidate>';
		$h .= wp_nonce_field( 'itour_transfer_book', '_itour_tb', true, false );
		foreach ( array( 'from', 'to', 'pax' ) as $k ) {
			$h .= '<input type="hidden" name="' . self::KEY . '[' . $k . ']" value="' . esc_attr( (string) $q[ $k ] ) . '">';
		}
		$h .= '<input type="hidden" name="' . self::KEY . '[vehicle]" value="' . (int) $v->id . '">';
		/* translators: %s: vehicle name. */
		$h .= '<h3 class="itour-tb__title">' . esc_html( sprintf( __( 'Book the %s', 'itour-transfers' ), $v->name ) ) . '</h3>';
		if ( self::$errors ) {
			$h .= '<p class="itour-tb__alert" role="alert">' . esc_html__( 'Please check the highlighted fields.', 'itour-transfers' ) . '</p>';
		}
		$h .= '<fieldset class="itour-tb__trip"><legend>' . esc_html__( 'Trip', 'itour-transfers' ) . '</legend>';
		$h .= '<label><input type="radio" name="' . self::KEY . '[trip]" value="one"' . checked( ! $back, true, false ) . '> ' . esc_html__( 'One way', 'itour-transfers' ) . '</label>';
		$h .= '<label><input type="radio" name="' . self::KEY . '[trip]" value="return"' . checked( $back, true, false ) . '> ' . esc_html__( 'Return', 'itour-transfers' ) . '</label></fieldset>';

		$h .= '<div class="itour-tb__grid">';
		$h .= $row( 'date', __( 'Pick-up date', 'itour-transfers' ), '<input type="date" id="itour-tb-date" name="' . self::KEY . '[date]" required min="' . esc_attr( wp_date( 'Y-m-d' ) ) . '" value="' . $val( 'date', $q['date'] ) . '"' . $inv( 'date' ) . '>' );
		$h .= $row( 'time', 'airport' === $from->type ? __( 'Flight landing time', 'itour-transfers' ) : __( 'Pick-up time', 'itour-transfers' ), '<input type="time" id="itour-tb-time" name="' . self::KEY . '[time]" required value="' . $val( 'time' ) . '"' . $inv( 'time' ) . '>' );
		if ( 'airport' === $from->type ) {
			$h .= $row( 'flight', __( 'Arrival flight number', 'itour-transfers' ), '<input type="text" id="itour-tb-flight" name="' . self::KEY . '[flight]" maxlength="20" autocomplete="off" placeholder="MS 911"' . $req . ' value="' . $val( 'flight' ) . '"' . $inv( 'flight' ) . '>' );
		}
		$h .= '</div><div class="itour-tb__grid itour-tb__return">';
		$h .= $row( 'return_date', __( 'Return date', 'itour-transfers' ), '<input type="date" id="itour-tb-return_date" name="' . self::KEY . '[return_date]" min="' . esc_attr( wp_date( 'Y-m-d' ) ) . '" value="' . $val( 'return_date' ) . '"' . $inv( 'return_date' ) . '>' );
		$h .= $row( 'return_time', __( 'Return pick-up time', 'itour-transfers' ), '<input type="time" id="itour-tb-return_time" name="' . self::KEY . '[return_time]" value="' . $val( 'return_time' ) . '"' . $inv( 'return_time' ) . '>' );
		if ( 'airport' === $to->type ) {
			$h .= $row( 'return_flight', __( 'Departure flight number', 'itour-transfers' ), '<input type="text" id="itour-tb-return_flight" name="' . self::KEY . '[return_flight]" maxlength="20" autocomplete="off" value="' . $val( 'return_flight' ) . '"' . $inv( 'return_flight' ) . '>' );
		}
		$h .= '</div><div class="itour-tb__grid">';
		$h .= $row( 'seats', __( 'Child seats', 'itour-transfers' ), '<input type="number" id="itour-tb-seats" name="' . self::KEY . '[seats]" min="0" max="' . (int) $q['pax'] . '" inputmode="numeric" value="' . $val( 'seats', 0 ) . '"' . $inv( 'seats' ) . '>' );
		$h .= $row( 'bags', __( 'Bags', 'itour-transfers' ), '<input type="number" id="itour-tb-bags" name="' . self::KEY . '[bags]" min="0" max="99" inputmode="numeric" value="' . $val( 'bags', min( (int) $q['pax'], (int) $v->max_bags ) ) . '"' . $inv( 'bags' ) . '>' );
		$h .= $row( 'notes', __( 'Notes for the driver', 'itour-transfers' ), '<textarea id="itour-tb-notes" name="' . self::KEY . '[notes]" rows="2" maxlength="500" placeholder="' . esc_attr__( 'Room number, exact address, wheelchair…', 'itour-transfers' ) . '">' . esc_textarea( (string) ( $p['notes'] ?? '' ) ) . '</textarea>', 'itour-tb__field--wide' );
		$h .= '</div>';

		if ( (float) $rules['deposit_percent'] > 0 ) {
			$deposit = 'deposit' === ( $p['pay'] ?? 'full' );
			$h      .= '<fieldset class="itour-tb__trip"><legend>' . esc_html__( 'Pay now', 'itour-transfers' ) . '</legend>';
			$h      .= '<label><input type="radio" name="' . self::KEY . '[pay]" value="full"' . checked( ! $deposit, true, false ) . '> ' . esc_html__( 'Full price', 'itour-transfers' ) . '</label>';
			/* translators: %s: deposit percentage. */
			$h .= '<label><input type="radio" name="' . self::KEY . '[pay]" value="deposit"' . checked( $deposit, true, false ) . '> ' . esc_html( sprintf( __( 'Deposit (%s%%), the rest on the day', 'itour-transfers' ), number_format_i18n( (float) $rules['deposit_percent'] ) ) ) . '</label></fieldset>';
		}
		$h .= '<button type="submit" class="itour-tb__submit wp-element-button">' . esc_html__( 'Continue to checkout', 'itour-transfers' ) . '</button>';
		/* translators: 1: minutes at airports, 2: minutes at hotels. */
		$h .= '<p class="itour-tb__small">' . esc_html( sprintf( __( 'The final price, with extras, is shown at checkout. Free waiting: %1$d minutes at airports, %2$d minutes at hotels.', 'itour-transfers' ), $rules['wait_airport'], $rules['wait_hotel'] ) ) . '</p>';
		return $h . '</form>';
	}

	/**
	 * Handle a booking submit: check it, price it on the server, add it to the cart, go to checkout.
	 * On errors the page renders again with the form showing them.
	 */
	public function submit(): void {
		if ( ! isset( $_POST[ self::KEY ], $_POST['_itour_tb'] ) || ! self::available() ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_itour_tb'] ) ), 'itour_transfer_book' ) ) {
			self::$errors['date'] = __( 'This page expired. Please try again.', 'itour-transfers' );
			return;
		}
		$p    = array_map( 'sanitize_textarea_field', wp_unslash( (array) $_POST[ self::KEY ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by array_map.
		$from = Locations::find( $p['from'] ?? '' );
		$to   = Locations::find( $p['to'] ?? '' );
		$pax  = max( 1, min( 99, (int) ( $p['pax'] ?? 1 ) ) );
		$back = 'return' === ( $p['trip'] ?? 'one' );
		if ( ! $from || ! $to ) {
			return; // Tampered or renamed place: the results block explains.
		}
		$rules = Settings::get();
		$tz    = wp_timezone();
		$at    = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', ( $p['date'] ?? '' ) . ' ' . ( $p['time'] ?? '' ), $tz );
		$e     = array();
		if ( ! $at ) {
			$e['date'] = __( 'Enter the pick-up date and time.', 'itour-transfers' );
		} elseif ( $at < new \DateTimeImmutable( '+' . (int) $rules['min_hours'] . ' hours', $tz ) ) {
			/* translators: %d: hours. */
			$e['date'] = sprintf( _n( 'Book at least %d hour before pick-up. For sooner, please call us.', 'Book at least %d hours before pick-up. For sooner, please call us.', (int) $rules['min_hours'], 'itour-transfers' ), (int) $rules['min_hours'] );
		}
		$flight = strtoupper( trim( (string) ( $p['flight'] ?? '' ) ) );
		if ( 'airport' === $from->type && ! empty( $rules['flight_required'] ) && '' === $flight ) {
			$e['flight'] = __( 'Enter your flight number so the driver can follow delays.', 'itour-transfers' );
		}
		$ret     = null;
		$rflight = strtoupper( trim( (string) ( $p['return_flight'] ?? '' ) ) );
		if ( $back ) {
			$ret = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', ( $p['return_date'] ?? '' ) . ' ' . ( $p['return_time'] ?? '' ), $tz );
			if ( ! $ret ) {
				$e['return_date'] = __( 'Enter the return date and time.', 'itour-transfers' );
			} elseif ( $at && $ret <= $at ) {
				$e['return_date'] = __( 'The return must be after the first pick-up.', 'itour-transfers' );
			}
			if ( 'airport' === $to->type && ! empty( $rules['flight_required'] ) && '' === $rflight ) {
				$e['return_flight'] = __( 'Enter your departure flight number.', 'itour-transfers' );
			}
		}
		$seats = (int) ( $p['seats'] ?? 0 );
		$bags  = (int) ( $p['bags'] ?? 0 );
		if ( $seats < 0 || $seats > $pax ) {
			$e['seats'] = __( 'At most one child seat per passenger.', 'itour-transfers' );
		}
		if ( $bags < 0 || $bags > 99 ) {
			$e['bags'] = __( 'Enter the number of bags.', 'itour-transfers' );
		}
		$price = Module::price( (int) ( $p['vehicle'] ?? 0 ), Locations::zone_of( $from ), Locations::zone_of( $to ), $pax, $back, max( 0, $seats ), max( 0, $bags ) );
		if ( ! $price ) {
			$e['date'] = __( 'This vehicle is no longer available for this route. Please search again.', 'itour-transfers' );
		}
		if ( $e ) {
			self::$errors = $e;
			return;
		}

		$deposit = (float) $rules['deposit_percent'] > 0 && 'deposit' === ( $p['pay'] ?? 'full' );
		$now     = $deposit ? round( $price['total'] * (float) $rules['deposit_percent'] / 100, 2 ) : $price['total'];
		$data    = array(
			'uid'           => wp_generate_uuid4(), // Keeps two identical bookings as two cart lines.
			'vehicle_id'    => (int) $price['vehicle']->id,
			'vehicle'       => $price['vehicle']->name,
			'from'          => $from->name,
			'to'            => $to->name,
			'pax'           => $pax,
			'pickup_at'     => $at->format( 'Y-m-d H:i' ),
			'flight'        => $flight,
			'return_at'     => $ret ? $ret->format( 'Y-m-d H:i' ) : '',
			'return_flight' => $back ? $rflight : '',
			'seats'         => $seats,
			'bags'          => $bags,
			'notes'         => mb_substr( trim( (string) ( $p['notes'] ?? '' ) ), 0, 500 ),
			'total'         => $price['total'],
			'now'           => $now,
		);
		WC()->cart->add_to_cart( self::product_id(), 1, 0, array(), array( self::KEY => $data ) );
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * The hidden product every transfer booking uses (created on first use).
	 */
	public static function product_id(): int {
		$id = (int) get_option( self::PRODUCT );
		if ( $id && 'product' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}
		$product = new \WC_Product_Simple();
		$product->set_name( __( 'Transfer', 'itour-transfers' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_regular_price( '0' );
		$product->set_sold_individually( false );
		$id = $product->save();
		update_option( self::PRODUCT, $id, false );
		return $id;
	}

	/**
	 * Charge what the booking says (full price or deposit), never the product's own price.
	 *
	 * @param \WC_Cart $cart Cart.
	 */
	public function cart_prices( $cart ): void {
		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item[ self::KEY ] ) ) {
				$item['data']->set_price( (float) $item[ self::KEY ]['now'] );
				$item['data']->set_name( self::title( $item[ self::KEY ] ) ); // Each cart line has its own product object.
			}
		}
	}

	/**
	 * Trip details under the cart line (cart, checkout, also the block checkout).
	 *
	 * @param array<int,array<string,string>> $data Shown details.
	 * @param array<string,mixed>             $item Cart item.
	 * @return array<int,array<string,string>>
	 */
	public function cart_details( $data, $item ): array {
		if ( isset( $item[ self::KEY ] ) ) {
			foreach ( self::lines( $item[ self::KEY ] ) as $label => $value ) {
				$data[] = array(
					'key'   => $label,
					'value' => $value,
				);
			}
		}
		return (array) $data;
	}

	/**
	 * Copy the booking onto the order line (shown in the admin order and in order emails).
	 *
	 * @param \WC_Order_Item_Product $item   Order line.
	 * @param string                 $key    Cart item key.
	 * @param array<string,mixed>    $values Cart item.
	 */
	public function order_item( $item, $key, $values ): void {
		if ( ! isset( $values[ self::KEY ] ) ) {
			return;
		}
		$item->set_name( self::title( $values[ self::KEY ] ) );
		foreach ( self::lines( $values[ self::KEY ] ) as $label => $value ) {
			$item->add_meta_data( $label, $value );
		}
		$item->add_meta_data( '_' . self::KEY, $values[ self::KEY ] );
	}

	/**
	 * Write a booking row per transfer line once the order exists. Runs once per order.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function record( $order ): void {
		global $wpdb;
		if ( $order->get_meta( '_itour_transfers_recorded' ) ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$b = $item->get_meta( '_' . self::KEY );
			if ( ! is_array( $b ) ) {
				continue;
			}
			$ref = itour()->sequences()->next( 'transfer', 'TR' );
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'itour_transfer_bookings',
				array(
					'ref'           => $ref,
					'order_id'      => $order->get_id(),
					'vehicle_id'    => $b['vehicle_id'],
					'vehicle'       => $b['vehicle'],
					'from_place'    => $b['from'],
					'to_place'      => $b['to'],
					'pax'           => $b['pax'],
					'pickup_at'     => $b['pickup_at'],
					'flight'        => $b['flight'],
					'return_at'     => '' !== $b['return_at'] ? $b['return_at'] : null,
					'return_flight' => $b['return_flight'],
					'child_seats'   => $b['seats'],
					'bags'          => $b['bags'],
					'notes'         => $b['notes'],
					'total'         => $b['total'],
					'paid_now'      => $b['now'],
					'currency'      => $order->get_currency(),
					'created_at'    => current_time( 'mysql' ),
				)
			);
			$item->add_meta_data( __( 'Booking reference', 'itour-transfers' ), $ref, true );
			$item->save();
		}
		$order->update_meta_data( '_itour_transfers_recorded', '1' );
		$order->save();
	}

	/**
	 * "Transfer: A → B".
	 *
	 * @param array<string,mixed> $b Booking.
	 */
	private static function title( array $b ): string {
		/* translators: 1: from place, 2: to place. */
		return sprintf( __( 'Transfer: %1$s → %2$s', 'itour-transfers' ), $b['from'], $b['to'] );
	}

	/**
	 * Booking details as label => value.
	 *
	 * @param array<string,mixed> $b Booking.
	 * @return array<string,string>
	 */
	private static function lines( array $b ): array {
		$when  = static fn( string $at ): string => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $at . ' ' . wp_timezone_string() ) );
		$money = static fn( float $v ): string => html_entity_decode( wp_strip_all_tags( wc_price( $v ) ) );
		$out   = array(
			__( 'Vehicle', 'itour-transfers' )    => $b['vehicle'],
			__( 'Passengers', 'itour-transfers' ) => (string) $b['pax'],
			__( 'Pick-up', 'itour-transfers' )    => $when( $b['pickup_at'] ),
		);
		if ( '' !== $b['flight'] ) {
			$out[ __( 'Flight number', 'itour-transfers' ) ] = $b['flight'];
		}
		if ( '' !== $b['return_at'] ) {
			$out[ __( 'Return pick-up', 'itour-transfers' ) ] = $when( $b['return_at'] );
			if ( '' !== $b['return_flight'] ) {
				$out[ __( 'Return flight number', 'itour-transfers' ) ] = $b['return_flight'];
			}
		}
		if ( $b['seats'] ) {
			$out[ __( 'Child seats', 'itour-transfers' ) ] = (string) $b['seats'];
		}
		$out[ __( 'Bags', 'itour-transfers' ) ] = (string) $b['bags'];
		if ( '' !== $b['notes'] ) {
			$out[ __( 'Notes', 'itour-transfers' ) ] = $b['notes'];
		}
		if ( (float) $b['now'] < (float) $b['total'] ) {
			$out[ __( 'Total price', 'itour-transfers' ) ]    = $money( (float) $b['total'] );
			$out[ __( 'Pay on the day', 'itour-transfers' ) ] = $money( (float) $b['total'] - (float) $b['now'] );
		}
		return $out;
	}
}
