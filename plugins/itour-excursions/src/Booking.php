<?php
/**
 * Excursion booking: bookable slots (weekdays × start times, minus closed dates, the cut-off and full
 * days), the form on the excursion page, server-side checks and price, then the shared iTour Checkout.
 *
 * @package ITour\Excursions
 */

namespace ITour\Excursions;

use ITour\Core\Admin\Crud;
use ITour\Core\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Slots, form, submit, record, bookings screen.
 */
final class Booking {

	/**
	 * Form field prefix.
	 */
	private const KEY = 'itour_exc_book';

	/**
	 * How far ahead the date list goes.
	 */
	private const DAYS_AHEAD = 120;

	/**
	 * Field errors from the last submit.
	 *
	 * @var array<string,string>
	 */
	private static array $errors = array();

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'template_redirect', array( $this, 'submit' ) );
		add_filter( 'itour/checkout/record_excursion', array( $this, 'record' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status' ), 10, 3 );
	}

	/**
	 * Travellers already booked per start, for seat limits: 'Y-m-d' => count.
	 *
	 * @param int $post Post id.
	 * @return array<string,int>
	 */
	private static function taken( int $post ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT DATE(start_at) d, SUM(adults + children + infants) n FROM %i WHERE post_id = %d AND status = 'active' AND start_at >= CURDATE() GROUP BY d", $wpdb->prefix . 'itour_excursion_bookings', $post ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( 'intval', array_column( $rows, 'n', 'd' ) );
	}

	/**
	 * Bookable starts: 'Y-m-d H:i' => [ label, seats left or null for no limit ].
	 *
	 * @param int $post Post id.
	 * @return array<string,array{0:string,1:int|null}>
	 */
	public static function slots( int $post ): array {
		$c     = Excursion::config( $post );
		$tz    = wp_timezone();
		$open  = new \DateTimeImmutable( '+' . (int) $c['cutoff'] . ' hours', $tz );
		$taken = self::taken( $post );
		$days  = array_map( 'intval', (array) $c['days'] );
		$times = $c['times'] ? (array) $c['times'] : array( '09:00' );
		$out   = array();
		for ( $i = 0; $i <= self::DAYS_AHEAD; $i++ ) {
			$day = new \DateTimeImmutable( "today +{$i} days", $tz );
			$ymd = $day->format( 'Y-m-d' );
			if ( ! in_array( (int) $day->format( 'N' ), $days, true ) ) {
				continue;
			}
			foreach ( (array) $c['blocked'] as $b ) {
				if ( $ymd >= $b['from'] && $ymd <= ( '' !== $b['to'] ? $b['to'] : $b['from'] ) ) {
					continue 2;
				}
			}
			$left = $c['seats'] ? (int) $c['seats'] - ( $taken[ $ymd ] ?? 0 ) : null;
			if ( null !== $left && $left <= 0 ) {
				continue;
			}
			foreach ( $times as $t ) {
				$at = new \DateTimeImmutable( "{$ymd} {$t}", $tz );
				if ( $at < $open ) {
					continue;
				}
				$label = wp_date( 'D j M', $at->getTimestamp() ) . ( count( $times ) > 1 ? ', ' . wp_date( get_option( 'time_format' ), $at->getTimestamp() ) : '' );
				if ( null !== $left && $left <= 10 ) {
					/* translators: %d: seats left. */
					$label .= ' · ' . sprintf( _n( '%d seat left', '%d seats left', $left, 'itour-excursions' ), $left );
				}
				$out[ $at->format( 'Y-m-d H:i' ) ] = array( $label, $left );
			}
		}
		return $out;
	}

	/**
	 * Hotels the excursion picks up from: place id => "Hotel (zone)", with zone and pick-up time.
	 *
	 * @param int $post Post id.
	 * @return array<int,array{label:string,zone:int,time:string}>
	 */
	private static function hotels( int $post ): array {
		global $wpdb;
		$zones = array_column( Excursion::pickups( $post ), null, 'zone_id' );
		if ( ! $zones ) {
			return array();
		}
		$ids  = implode( ',', array_map( 'intval', array_keys( $zones ) ) );
		$rows = (array) $wpdb->get_results( "SELECT h.id, h.name, h.parent_id, z.name zone FROM {$wpdb->prefix}itour_locations h JOIN {$wpdb->prefix}itour_locations z ON z.id = h.parent_id WHERE h.active = 1 AND h.type IN ('hotel','port') AND h.parent_id IN ({$ids}) ORDER BY h.name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are ints.
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r->id ] = array(
				'label' => $r->name . ' (' . $r->zone . ')',
				'zone'  => (int) $r->parent_id,
				'time'  => substr( (string) $zones[ $r->parent_id ]['pickup_time'], 0, 5 ),
			);
		}
		return $out;
	}

	/**
	 * The booking form (on the excursion page).
	 *
	 * @param int $post Post id.
	 */
	public static function form( int $post ): string {
		$c        = Excursion::config( $post );
		$slots    = self::slots( $post );
		$variants = array_intersect_key( Excursion::variants(), array_flip( array_column( Excursion::rates( $post ), 'variant' ) ) );
		if ( ! $variants ) {
			return '';
		}
		$h = '<h2 class="itour-exc__title" id="book">' . esc_html__( 'Book this trip', 'itour-excursions' ) . '</h2>';
		if ( ! Checkout::available() ) {
			return current_user_can( 'activate_plugins' ) ? $h . '<p class="itour-exc__note">' . esc_html__( 'Online booking needs WooCommerce.', 'itour-excursions' ) . '</p>' : '';
		}
		if ( ! $slots ) {
			return $h . '<p class="itour-exc__note">' . esc_html__( 'No dates are open for booking right now. Contact us and we’ll find one.', 'itour-excursions' ) . '</p>';
		}
		$p   = isset( $_POST[ self::KEY ] ) && is_array( $_POST[ self::KEY ] ) ? wp_unslash( $_POST[ self::KEY ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- refill only (nonce checked in submit()); values escaped below.
		$val = static fn( string $k, $d = '' ) => (string) ( is_scalar( $p[ $k ] ?? null ) ? $p[ $k ] : $d );
		$err = static fn( string $k ): string => isset( self::$errors[ $k ] ) ? '<span class="itour-tb__error" id="itour-eb-' . esc_attr( $k ) . '-error">' . esc_html( self::$errors[ $k ] ) . '</span>' : '';
		$inv = static fn( string $k ): string => isset( self::$errors[ $k ] ) ? ' aria-invalid="true" aria-describedby="itour-eb-' . esc_attr( $k ) . '-error"' : '';
		$n   = static fn( string $k ): string => self::KEY . '[' . $k . ']';

		$h .= '<form method="post" action="#book" class="itour-tb" novalidate>' . wp_nonce_field( 'itour_exc_book', '_itour_eb', true, false );
		$h .= '<input type="hidden" name="' . esc_attr( $n( 'post' ) ) . '" value="' . (int) $post . '">';
		if ( self::$errors ) {
			$h .= '<p class="itour-tb__alert" role="alert">' . esc_html__( 'Please check the highlighted fields.', 'itour-excursions' ) . '</p>';
		}
		if ( count( $variants ) > 1 ) {
			$h .= '<fieldset class="itour-tb__trip"><legend>' . esc_html__( 'Trip type', 'itour-excursions' ) . '</legend>';
			foreach ( $variants as $k => $label ) {
				$h .= '<label><input type="radio" name="' . esc_attr( $n( 'variant' ) ) . '" value="' . esc_attr( $k ) . '"' . checked( $val( 'variant', (string) array_key_first( $variants ) ), $k, false ) . '> ' . esc_html( $label ) . '</label>';
			}
			$h .= '</fieldset>';
		} else {
			$h .= '<input type="hidden" name="' . esc_attr( $n( 'variant' ) ) . '" value="' . esc_attr( (string) array_key_first( $variants ) ) . '">';
		}

		$h .= '<div class="itour-tb__grid"><div class="itour-tb__field itour-tb__field--wide"><label for="itour-eb-slot">' . esc_html__( 'Date', 'itour-excursions' ) . '</label><select id="itour-eb-slot" name="' . esc_attr( $n( 'slot' ) ) . '"' . $inv( 'slot' ) . '>';
		foreach ( $slots as $k => $s ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $val( 'slot' ), $k, false ) . '>' . esc_html( $s[0] ) . '</option>';
		}
		$h   .= '</select>' . $err( 'slot' ) . '</div>';
		$ages = array(
			'adults'   => array( __( 'Adults', 'itour-excursions' ), 2, 1 ),
			'children' => array( __( 'Children (2–11)', 'itour-excursions' ), 0, 0 ),
			'infants'  => array( __( 'Infants (0–1)', 'itour-excursions' ), 0, 0 ),
		);
		foreach ( $ages as $k => list( $label, $default, $min ) ) {
			$h .= '<div class="itour-tb__field"><label for="itour-eb-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label><input type="number" id="itour-eb-' . esc_attr( $k ) . '" name="' . esc_attr( $n( $k ) ) . '" min="' . (int) $min . '" max="99" inputmode="numeric" value="' . esc_attr( $val( $k, (string) $default ) ) . '"' . $inv( $k ) . '>' . $err( $k ) . '</div>';
		}
		$h .= '</div>';

		// Pick-up.
		$modes  = (array) $c['pickup_modes'];
		$hotels = in_array( 'hotel', $modes, true ) ? self::hotels( $post ) : array();
		$opts   = array();
		if ( $hotels ) {
			$opts['hotel'] = __( 'Pick me up from my hotel', 'itour-excursions' );
		}
		if ( in_array( 'typed', $modes, true ) ) {
			$opts['typed'] = $hotels ? __( 'My hotel isn’t listed', 'itour-excursions' ) : __( 'Pick me up from my hotel', 'itour-excursions' );
		}
		if ( in_array( 'meeting', $modes, true ) && '' !== $c['meeting_point'] ) {
			/* translators: %s: meeting point. */
			$opts['meeting'] = sprintf( __( 'I’ll meet you at %s', 'itour-excursions' ), $c['meeting_point'] );
		}
		if ( $opts ) {
			$mode = $val( 'pickup', (string) array_key_first( $opts ) );
			$h   .= '<fieldset class="itour-tb__trip itour-eb__pickup"><legend>' . esc_html__( 'Pick-up', 'itour-excursions' ) . '</legend>';
			foreach ( $opts as $k => $label ) {
				$h .= '<label><input type="radio" name="' . esc_attr( $n( 'pickup' ) ) . '" value="' . esc_attr( $k ) . '"' . checked( $mode, $k, false ) . '> ' . esc_html( $label ) . '</label>';
			}
			$h .= '</fieldset><div class="itour-tb__grid">';
			if ( $hotels ) {
				$h .= '<div class="itour-tb__field itour-eb__hotel"><label for="itour-eb-hotel">' . esc_html__( 'Hotel', 'itour-excursions' ) . '</label><select id="itour-eb-hotel" name="' . esc_attr( $n( 'hotel' ) ) . '"' . $inv( 'hotel' ) . '><option value="">' . esc_html__( '— Choose your hotel —', 'itour-excursions' ) . '</option>';
				foreach ( $hotels as $id => $ho ) {
					$h .= '<option value="' . (int) $id . '"' . selected( $val( 'hotel' ), (string) $id, false ) . '>' . esc_html( $ho['label'] ) . '</option>';
				}
				$h .= '</select>' . $err( 'hotel' ) . '</div>';
			}
			if ( isset( $opts['typed'] ) ) {
				$h .= '<div class="itour-tb__field itour-eb__typed"><label for="itour-eb-address">' . esc_html__( 'Hotel name or address', 'itour-excursions' ) . '</label><input type="text" id="itour-eb-address" name="' . esc_attr( $n( 'address' ) ) . '" maxlength="190" value="' . esc_attr( $val( 'address' ) ) . '"' . $inv( 'address' ) . '>' . $err( 'address' ) . '</div>';
			}
			$h .= '</div>';
		}

		if ( $c['addons'] ) {
			$h .= '<fieldset class="itour-tb__trip itour-eb__addons"><legend>' . esc_html__( 'Add-ons', 'itour-excursions' ) . '</legend>';
			$on = array_map( 'intval', (array) ( $p['addons'] ?? array() ) );
			foreach ( (array) $c['addons'] as $i => $a ) {
				/* translators: 1: add-on, 2: price, 3: per person / per booking. */
				$label = sprintf( __( '%1$s (+%2$s %3$s)', 'itour-excursions' ), $a['name'], itour()->currencies()->format( (float) $a['price'] ), 'booking' === $a['per'] ? __( 'per booking', 'itour-excursions' ) : __( 'per person', 'itour-excursions' ) );
				$h    .= '<label><input type="checkbox" name="' . esc_attr( self::KEY . '[addons][]' ) . '" value="' . (int) $i . '"' . checked( in_array( (int) $i, $on, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
			}
			$h .= '</fieldset>';
		}
		$h .= '<div class="itour-tb__grid"><div class="itour-tb__field itour-tb__field--wide"><label for="itour-eb-notes">' . esc_html__( 'Notes', 'itour-excursions' ) . '</label><textarea id="itour-eb-notes" name="' . esc_attr( $n( 'notes' ) ) . '" rows="2" maxlength="500" placeholder="' . esc_attr__( 'Room number, language, dietary needs…', 'itour-excursions' ) . '">' . esc_textarea( $val( 'notes' ) ) . '</textarea></div></div>';
		if ( (int) $c['deposit'] > 0 ) {
			$dep = 'deposit' === $val( 'pay', 'full' );
			$h  .= '<fieldset class="itour-tb__trip"><legend>' . esc_html__( 'Pay now', 'itour-excursions' ) . '</legend>';
			$h  .= '<label><input type="radio" name="' . esc_attr( $n( 'pay' ) ) . '" value="full"' . checked( ! $dep, true, false ) . '> ' . esc_html__( 'Full price', 'itour-excursions' ) . '</label>';
			/* translators: %d: deposit percentage. */
			$h .= '<label><input type="radio" name="' . esc_attr( $n( 'pay' ) ) . '" value="deposit"' . checked( $dep, true, false ) . '> ' . esc_html( sprintf( __( 'Deposit (%d%%), the rest on the day', 'itour-excursions' ), (int) $c['deposit'] ) ) . '</label></fieldset>';
		}
		$h .= '<button type="submit" class="itour-tb__submit wp-element-button">' . esc_html__( 'Continue to checkout', 'itour-excursions' ) . '</button>';
		$h .= '<p class="itour-tb__small">' . esc_html__( 'The price for your group, with pick-up, add-ons and any supplements, is shown at checkout.', 'itour-excursions' ) . '</p>';
		return $h . '</form>';
	}

	/**
	 * Handle a booking submit: check, price on the server, add to the cart, go to checkout.
	 */
	public function submit(): void {
		if ( ! isset( $_POST[ self::KEY ], $_POST['_itour_eb'] ) || ! Checkout::available() ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_itour_eb'] ) ), 'itour_exc_book' ) ) {
			self::$errors['slot'] = __( 'This page expired. Please try again.', 'itour-excursions' );
			return;
		}
		$p    = wp_unslash( (array) $_POST[ self::KEY ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value checked below.
		$post = absint( $p['post'] ?? 0 );
		if ( Excursion::TYPE !== get_post_type( $post ) || 'publish' !== get_post_status( $post ) ) {
			return;
		}
		$c        = Excursion::config( $post );
		$slot     = (string) ( $p['slot'] ?? '' );
		$variant  = (string) ( $p['variant'] ?? 'shared' );
		$adults   = absint( $p['adults'] ?? 0 );
		$children = absint( $p['children'] ?? 0 );
		$infants  = absint( $p['infants'] ?? 0 );
		$pax      = $adults + $children + $infants;
		$slots    = self::slots( $post );
		$e        = array();
		if ( ! isset( $slots[ $slot ] ) ) {
			$e['slot'] = __( 'This date is no longer available. Please pick another.', 'itour-excursions' );
		} elseif ( null !== $slots[ $slot ][1] && $pax > $slots[ $slot ][1] ) {
			/* translators: %d: seats left. */
			$e['slot'] = sprintf( _n( 'Only %d seat is left on this date.', 'Only %d seats are left on this date.', (int) $slots[ $slot ][1], 'itour-excursions' ), (int) $slots[ $slot ][1] );
		}
		if ( $adults < 1 ) {
			$e['adults'] = __( 'At least one adult must travel.', 'itour-excursions' );
		}
		if ( $pax > 99 ) {
			$e['adults'] = __( 'For groups this large, please contact us.', 'itour-excursions' );
		}

		// Pick-up.
		$mode    = (string) ( $p['pickup'] ?? '' );
		$zone    = 0;
		$pickup  = '';
		$p_time  = '';
		$modes   = (array) $c['pickup_modes'];
		$hotels  = in_array( 'hotel', $modes, true ) ? self::hotels( $post ) : array();
		$address = sanitize_text_field( (string) ( $p['address'] ?? '' ) );
		if ( 'hotel' === $mode && $hotels ) {
			$ho = $hotels[ absint( $p['hotel'] ?? 0 ) ] ?? null;
			if ( ! $ho ) {
				$e['hotel'] = __( 'Choose your hotel.', 'itour-excursions' );
			} else {
				$zone   = $ho['zone'];
				$pickup = $ho['label'];
				$p_time = $ho['time'];
			}
		} elseif ( 'typed' === $mode && in_array( 'typed', $modes, true ) ) {
			if ( '' === $address ) {
				$e['address'] = __( 'Enter your hotel name or address.', 'itour-excursions' );
			}
			$pickup = $address;
		} elseif ( 'meeting' === $mode && in_array( 'meeting', $modes, true ) ) {
			$pickup = (string) $c['meeting_point'];
		} elseif ( $modes ) {
			$e['hotel'] = __( 'Choose how we pick you up.', 'itour-excursions' );
		}

		$addons = array_values( array_intersect( array_map( 'intval', (array) ( $p['addons'] ?? array() ) ), array_keys( (array) $c['addons'] ) ) );
		$price  = $e ? null : Excursion::price( $post, $variant, substr( $slot, 0, 10 ), $adults, $children, $infants, $zone, $addons );
		if ( null === $price ) {
			self::$errors = $e ? $e : array( 'adults' => __( 'We have no price for this group size and type. Contact us for a quote.', 'itour-excursions' ) );
			return;
		}

		$deposit = (int) $c['deposit'] > 0 && 'deposit' === ( $p['pay'] ?? 'full' );
		$at      = strtotime( $slot . ' ' . wp_timezone_string() );
		$lines   = array(
			__( 'Trip type', 'itour-excursions' ) => Excursion::variants()[ $variant ] ?? $variant,
			__( 'Date', 'itour-excursions' )      => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $at ),
		);
		foreach ( $price['lines'] as $l ) {
			$lines[ $l[0] ] = html_entity_decode( wp_strip_all_tags( wc_price( $l[1] ) ) );
		}
		if ( '' !== $pickup ) {
			$lines[ __( 'Pick-up', 'itour-excursions' ) ] = $pickup . ( '' !== $p_time ? ' · ' . wp_date( get_option( 'time_format' ), strtotime( substr( $slot, 0, 10 ) . ' ' . $p_time . ' ' . wp_timezone_string() ) ) : '' );
		}
		$lines[ __( 'Cancellation', 'itour-excursions' ) ] = \ITour\Core\Policies::summary( \ITour\Core\Policies::of( $post ), substr( $slot, 0, 10 ) );
		$notes = mb_substr( sanitize_textarea_field( (string) ( $p['notes'] ?? '' ) ), 0, 500 );
		if ( '' !== $notes ) {
			$lines[ __( 'Notes', 'itour-excursions' ) ] = $notes;
		}
		Checkout::add(
			array(
				'type'        => 'excursion',
				'title'       => get_the_title( $post ),
				'lines'       => $lines,
				'total'       => $price['total'],
				'now'         => $deposit ? round( $price['total'] * (int) $c['deposit'] / 100, 2 ) : $price['total'],
				'post'        => $post,
				'variant'     => $variant,
				'start_at'    => $slot,
				'adults'      => $adults,
				'children'    => $children,
				'infants'     => $infants,
				'pickup'      => $pickup,
				'pickup_time' => $p_time,
				'addons'      => implode( ', ', array_map( static fn( $i ) => $c['addons'][ $i ]['name'], $addons ) ),
				'notes'       => $notes,
			)
		);
	}

	/**
	 * Write the booking row once the order exists (via Checkout).
	 *
	 * @param string              $ref   Reference so far.
	 * @param array<string,mixed> $b     Booking.
	 * @param \WC_Order           $order Order.
	 */
	public function record( $ref, $b, $order ): string {
		global $wpdb;
		$ref = itour()->sequences()->next( 'excursion', 'EX' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'itour_excursion_bookings',
			array(
				'ref'         => $ref,
				'order_id'    => $order->get_id(),
				'post_id'     => $b['post'],
				'title'       => $b['title'],
				'variant'     => $b['variant'],
				'start_at'    => $b['start_at'],
				'adults'      => $b['adults'],
				'children'    => $b['children'],
				'infants'     => $b['infants'],
				'pickup'      => $b['pickup'],
				'pickup_time' => '' !== $b['pickup_time'] ? $b['pickup_time'] : null,
				'addons'      => $b['addons'],
				'notes'       => $b['notes'],
				'total'       => $b['total'],
				'paid_now'    => $b['now'],
				'currency'    => $order->get_currency(),
				'created_at'  => current_time( 'mysql' ),
			)
		);
		return $ref;
	}

	/**
	 * Cancelled, refunded or failed orders free their seats; other statuses hold them.
	 *
	 * @param int    $order_id Order id.
	 * @param string $from     Old status.
	 * @param string $to       New status.
	 */
	public function order_status( $order_id, $from, $to ): void {
		global $wpdb;
		$status = in_array( $to, array( 'cancelled', 'refunded', 'failed' ), true ) ? 'cancelled' : 'active';
		$wpdb->update( $wpdb->prefix . 'itour_excursion_bookings', array( 'status' => $status ), array( 'order_id' => (int) $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Bookings screen (read-only; changes go through the WooCommerce order).
	 *
	 * @param string $cap Capability.
	 */
	public static function screen( string $cap ): Crud {
		$text = static fn( string $key, string $label ): array => array(
			'key'   => $key,
			'label' => $label,
			'type'  => 'text',
		);
		return new Crud(
			array(
				'table'      => 'excursion_bookings',
				'singular'   => __( 'excursion booking', 'itour-excursions' ),
				'plural'     => __( 'Excursion bookings', 'itour-excursions' ),
				'capability' => $cap,
				'order'      => 'start_at',
				'order_desc' => true,
				'readonly'   => true,
				'can_add'    => false,
				'can_delete' => false,
				'search'     => array( 'ref', 'title', 'pickup' ),
				'empty'      => __( 'No excursion bookings yet. They appear here when a customer checks out.', 'itour-excursions' ),
				'columns'    => array(
					'ref'      => __( 'Booking', 'itour-excursions' ),
					'start_at' => __( 'Date', 'itour-excursions' ),
					'title'    => __( 'Excursion', 'itour-excursions' ),
					'pax'      => __( 'Travellers', 'itour-excursions' ),
					'pickup'   => __( 'Pick-up', 'itour-excursions' ),
					'status'   => __( 'Order status', 'itour-excursions' ),
				),
				'cell'       => static function ( string $col, array $row ): ?string {
					if ( 'pax' === $col ) {
						return $row['adults'] . ' + ' . $row['children'] . ' + ' . $row['infants'];
					}
					if ( 'status' === $col ) {
						$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $row['order_id'] ) : null;
						return $order ? wc_get_order_status_name( $order->get_status() ) . ' (#' . $order->get_order_number() . ')' : '—';
					}
					return null;
				},
				'on_view'    => static function ( string $id ): void {
					global $wpdb;
					$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM %i WHERE id = %d', $wpdb->prefix . 'itour_excursion_bookings', $id ) ) ) : null; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					if ( $order ) {
						echo '<p><a class="button" href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Open the order (customer, payment, status)', 'itour-excursions' ) . '</a></p>';
					}
				},
				'fields'     => array(
					$text( 'ref', __( 'Booking', 'itour-excursions' ) ),
					$text( 'title', __( 'Excursion', 'itour-excursions' ) ),
					$text( 'variant', __( 'Trip type', 'itour-excursions' ) ),
					$text( 'start_at', __( 'Date', 'itour-excursions' ) ),
					$text( 'adults', __( 'Adults', 'itour-excursions' ) ),
					$text( 'children', __( 'Children (2–11)', 'itour-excursions' ) ),
					$text( 'infants', __( 'Infants (0–1)', 'itour-excursions' ) ),
					$text( 'pickup', __( 'Pick-up', 'itour-excursions' ) ),
					$text( 'pickup_time', __( 'Pick-up time', 'itour-excursions' ) ),
					$text( 'addons', __( 'Add-ons', 'itour-excursions' ) ),
					array(
						'key'   => 'notes',
						'label' => __( 'Notes', 'itour-excursions' ),
						'type'  => 'textarea',
					),
					$text( 'total', __( 'Total', 'itour-excursions' ) ),
					$text( 'paid_now', __( 'Paid at checkout', 'itour-excursions' ) ),
					$text( 'status', __( 'Seats', 'itour-excursions' ) ),
				),
			)
		);
	}
}
