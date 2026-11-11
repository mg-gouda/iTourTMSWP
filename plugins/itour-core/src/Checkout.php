<?php
/**
 * Shared WooCommerce glue for every iTour product (D-005). A module prices and checks a booking, then
 * calls Checkout::add(); one hidden "Booking" product carries it through cart, checkout and the order.
 * When the order is placed the module writes its own booking row through the
 * `itour/checkout/record_{type}` filter, which returns the booking reference shown on the order.
 *
 * Booking array: type, title, lines (label => value, shown in cart, order and emails), total, now
 * (charged at checkout: total or deposit) plus anything the module needs to record it.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Cart + order hooks.
 */
final class Checkout {

	/**
	 * Cart item / order item meta key.
	 */
	private const KEY = 'itour_booking';

	/**
	 * Option holding the hidden product's id.
	 */
	private const PRODUCT = 'itour_booking_product';

	/**
	 * Hooks (harmless without WooCommerce: they never fire).
	 */
	public function hooks(): void {
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
	 * Put a booking in the cart and go to checkout.
	 *
	 * @param array<string,mixed> $booking  Booking (see file header).
	 * @param bool                $redirect Go to checkout now (false for AJAX callers, e.g. the AI assistant).
	 */
	public static function add( array $booking, bool $redirect = true ): void {
		$booking['uid'] = wp_generate_uuid4(); // Two identical bookings stay two cart lines.
		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart(); // admin-ajax requests have no cart until asked.
		}
		WC()->cart->add_to_cart( self::product_id(), 1, 0, array(), array( self::KEY => $booking ) );
		if ( $redirect ) {
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	/**
	 * The hidden product every booking uses (created on first use).
	 */
	public static function product_id(): int {
		$id = (int) get_option( self::PRODUCT );
		if ( $id && 'product' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}
		$product = new \WC_Product_Simple();
		$product->set_name( __( 'Booking', 'itour-core' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_regular_price( '0' );
		$id = $product->save();
		update_option( self::PRODUCT, $id, false );
		return $id;
	}

	/**
	 * Charge what the booking says and name the line after it (each cart line has its own product object).
	 *
	 * @param \WC_Cart $cart Cart.
	 */
	public function cart_prices( $cart ): void {
		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item[ self::KEY ] ) ) {
				$item['data']->set_price( (float) $item[ self::KEY ]['now'] );
				$item['data']->set_name( (string) $item[ self::KEY ]['title'] );
			}
		}
	}

	/**
	 * Booking details under the cart line (classic and block checkout).
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
	 * Copy the booking onto the order line (admin order screen and emails).
	 *
	 * @param \WC_Order_Item_Product $item   Order line.
	 * @param string                 $key    Cart item key.
	 * @param array<string,mixed>    $values Cart item.
	 */
	public function order_item( $item, $key, $values ): void {
		if ( ! isset( $values[ self::KEY ] ) ) {
			return;
		}
		$item->set_name( (string) $values[ self::KEY ]['title'] );
		foreach ( self::lines( $values[ self::KEY ] ) as $label => $value ) {
			$item->add_meta_data( $label, $value );
		}
		$item->add_meta_data( '_' . self::KEY, $values[ self::KEY ] );
	}

	/**
	 * Once the order exists, let each module record its bookings. Runs once per order.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function record( $order ): void {
		if ( $order->get_meta( '_itour_recorded' ) ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$b = $item->get_meta( '_' . self::KEY );
			if ( ! is_array( $b ) ) {
				continue;
			}
			/**
			 * Write the module's booking row; return its reference ('' if none).
			 *
			 * @param string    $ref   Reference so far.
			 * @param array     $b     Booking.
			 * @param \WC_Order $order Order.
			 */
			$ref = (string) apply_filters( 'itour/checkout/record_' . $b['type'], '', $b, $order );
			if ( '' !== $ref ) {
				$item->add_meta_data( __( 'Booking reference', 'itour-core' ), $ref, true );
				$item->save();
			}
		}
		$order->update_meta_data( '_itour_recorded', '1' );
		$order->save();
	}

	/**
	 * Details plus, for deposits, the total and the balance.
	 *
	 * @param array<string,mixed> $b Booking.
	 * @return array<string,string>
	 */
	private static function lines( array $b ): array {
		$out = (array) $b['lines'];
		if ( (float) $b['now'] < (float) $b['total'] ) {
			$money                                       = static fn( float $v ): string => html_entity_decode( wp_strip_all_tags( wc_price( $v ) ) );
			$out[ __( 'Total price', 'itour-core' ) ]    = $money( (float) $b['total'] );
			$out[ __( 'Pay on the day', 'itour-core' ) ] = $money( (float) $b['total'] - (float) $b['now'] );
		}
		return $out;
	}
}
