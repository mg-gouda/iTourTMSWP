<?php
/**
 * Bookings as one shape for every document, read from the module tables (excursion_bookings,
 * transfer_bookings) and their WooCommerce order.
 *
 * @package ITour\Documents
 */

namespace ITour\Documents;

use ITour\Core\Policies;
use ITour\Core\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Booking reader.
 */
final class Data {

	/**
	 * Module tables: kind => table suffix. A missing module's table is skipped.
	 *
	 * @return array<string,string>
	 */
	private static function tables(): array {
		global $wpdb;
		$out = array();
		foreach ( array(
			'excursion' => 'itour_excursion_bookings',
			'transfer'  => 'itour_transfer_bookings',
		) as $kind => $t ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $t ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$out[ $kind ] = $wpdb->prefix . $t;
			}
		}
		return $out;
	}

	/**
	 * Raw rows: by reference, by order, or by service date range.
	 *
	 * @param string $field 'ref' | 'order_id' | 'range'.
	 * @param mixed  $value Value; for 'range' [ from Y-m-d, to Y-m-d ].
	 * @return array<int,array<string,mixed>>
	 */
	private static function rows( string $field, $value ): array {
		global $wpdb;
		$out = array();
		foreach ( self::tables() as $kind => $t ) {
			$date = 'excursion' === $kind ? 'start_at' : 'pickup_at';
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			if ( 'range' === $field ) {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE %i BETWEEN %s AND %s ORDER BY %i', $t, $date, $value[0] . ' 00:00:00', $value[1] . ' 23:59:59', $date ), ARRAY_A );
			} else {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE %i = %s', $t, $field, (string) $value ), ARRAY_A );
			}
			// phpcs:enable
			foreach ( (array) $rows as $r ) {
				$out[] = self::shape( $kind, $r );
			}
		}
		usort( $out, static fn( $a, $b ) => $a['start'] <=> $b['start'] );
		return $out;
	}

	/**
	 * One booking by reference (null if unknown).
	 *
	 * @param string $ref Reference.
	 * @return array<string,mixed>|null
	 */
	public static function booking( string $ref ): ?array {
		return self::rows( 'ref', $ref )[0] ?? null;
	}

	/**
	 * Bookings of an order.
	 *
	 * @param int $order Order id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function of_order( int $order ): array {
		return self::rows( 'order_id', $order );
	}

	/**
	 * Bookings with a service date in a range.
	 *
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return array<int,array<string,mixed>>
	 */
	public static function range( string $from, string $to ): array {
		return self::rows( 'range', array( $from, $to ) );
	}

	/**
	 * Common shape.
	 *
	 * @param string              $kind 'excursion' | 'transfer'.
	 * @param array<string,mixed> $r    Row.
	 * @return array<string,mixed>
	 */
	private static function shape( string $kind, array $r ): array {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $r['order_id'] ) : null;
		$order = $order instanceof \WC_Order ? $order : null;
		$b     = array(
			'kind'     => $kind,
			'ref'      => (string) $r['ref'],
			'order'    => $order,
			'status'   => (string) ( $r['status'] ?? 'active' ),
			'created'  => (string) $r['created_at'],
			'total'    => (float) $r['total'],
			'paid'     => (float) $r['paid_now'],
			'currency' => (string) $r['currency'],
			'notes'    => (string) ( $r['notes'] ?? '' ),
			'lead'     => array(
				'name'  => $order ? trim( $order->get_formatted_billing_full_name() ) : '',
				'phone' => $order ? (string) $order->get_billing_phone() : '',
				'email' => $order ? (string) $order->get_billing_email() : '',
			),
			'details'  => array(),
			'includes' => array(),
			'excludes' => array(),
			'cancel'   => array(),
			'image'    => '',
		);
		if ( $order && 'cancelled' === $order->get_status() ) {
			$b['status'] = 'cancelled';
		}
		$b['due'] = max( 0, $b['total'] - $b['paid'] );

		if ( 'excursion' === $kind ) {
			$post          = (int) $r['post_id'];
			$cfg           = class_exists( '\ITour\Excursions\Excursion' ) ? \ITour\Excursions\Excursion::config( $post ) : array();
			$info          = Products::info( $post );
			$b['type']     = __( 'Excursion', 'itour-documents' );
			$b['title']    = (string) $r['title'];
			$b['start']    = (string) $r['start_at'];
			$b['pax']      = (int) $r['adults'] + (int) $r['children'] + (int) $r['infants'];
			$b['details']  = array_filter(
				array(
					__( 'Date', 'itour-documents' )       => wp_date( 'D j M Y', strtotime( $r['start_at'] ) ),
					__( 'Start time', 'itour-documents' ) => wp_date( get_option( 'time_format' ), strtotime( $r['start_at'] ) ),
					__( 'Duration', 'itour-documents' )   => (string) ( $cfg['duration'] ?? '' ),
					__( 'Pick-up', 'itour-documents' )    => trim( (string) $r['pickup'] . ( ! empty( $r['pickup_time'] ) ? ' · ' . wp_date( get_option( 'time_format' ), strtotime( 'today ' . $r['pickup_time'] ) ) : '' ), ' ·' ),
					__( 'Meeting point', 'itour-documents' ) => '' === (string) $r['pickup'] ? (string) ( $cfg['meeting_point'] ?? '' ) : '',
					__( 'Travellers', 'itour-documents' ) => self::pax( (int) $r['adults'], (int) $r['children'], (int) $r['infants'] ),
					__( 'Option', 'itour-documents' )     => 'private' === $r['variant'] ? __( 'Private', 'itour-documents' ) : __( 'Shared group', 'itour-documents' ),
					__( 'Extras', 'itour-documents' )     => (string) $r['addons'],
				)
			);
			$b['includes'] = self::lines( $info['includes'] );
			$b['excludes'] = self::lines( $info['excludes'] );
			$b['cancel']   = Policies::of( $post );
			$thumb         = (int) get_post_thumbnail_id( $post );
			$file          = $thumb ? (string) get_attached_file( $thumb ) : '';
			$b['image']    = preg_match( '/\.(jpe?g|png)$/i', $file ) && is_readable( $file ) ? $file : ''; // mPDF reads it from disk, not over HTTP.
		} else {
			$b['type']    = __( 'Private transfer', 'itour-documents' );
			$b['title']   = $r['from_place'] . ' → ' . $r['to_place'];
			$b['start']   = (string) $r['pickup_at'];
			$b['pax']     = (int) $r['pax'];
			$b['details'] = array_filter(
				array(
					__( 'Pick-up', 'itour-documents' )     => wp_date( 'D j M Y · ' . get_option( 'time_format' ), strtotime( $r['pickup_at'] ) ),
					__( 'From', 'itour-documents' )        => (string) $r['from_place'],
					__( 'To', 'itour-documents' )          => (string) $r['to_place'],
					__( 'Flight', 'itour-documents' )      => (string) $r['flight'],
					__( 'Return', 'itour-documents' )      => $r['return_at'] ? wp_date( 'D j M Y · ' . get_option( 'time_format' ), strtotime( $r['return_at'] ) ) : '',
					__( 'Return flight', 'itour-documents' ) => (string) $r['return_flight'],
					__( 'Vehicle', 'itour-documents' )     => (string) $r['vehicle'],
					__( 'Passengers', 'itour-documents' )  => (string) (int) $r['pax'],
					__( 'Child seats', 'itour-documents' ) => (int) $r['child_seats'] ? (string) (int) $r['child_seats'] : '',
					__( 'Bags', 'itour-documents' )        => (int) $r['bags'] ? (string) (int) $r['bags'] : '',
				)
			);
			$vehicle      = (string) $r['vehicle_id'];
			$b['cancel']  = array_values( array_filter( (array) get_option( 'itour_transfers_cancel', array() ), static fn( $t ) => is_array( $t ) && ( '' === (string) ( $t['vehicle'] ?? '' ) || $vehicle === (string) $t['vehicle'] ) ) );
		}
		return $b;
	}

	/**
	 * "2 adults, 1 child".
	 *
	 * @param int $a Adults.
	 * @param int $c Children.
	 * @param int $i Infants.
	 */
	private static function pax( int $a, int $c, int $i ): string {
		/* translators: %d: number of adults. */
		$out = array( sprintf( _n( '%d adult', '%d adults', $a, 'itour-documents' ), $a ) );
		if ( $c ) {
			/* translators: %d: number of children. */
			$out[] = sprintf( _n( '%d child', '%d children', $c, 'itour-documents' ), $c );
		}
		if ( $i ) {
			/* translators: %d: number of infants. */
			$out[] = sprintf( _n( '%d infant', '%d infants', $i, 'itour-documents' ), $i );
		}
		return implode( ', ', $out );
	}

	/**
	 * Non-empty lines.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private static function lines( string $text ): array {
		return array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ) ) );
	}
}
