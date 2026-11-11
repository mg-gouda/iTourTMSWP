<?php
/**
 * Where the documents appear: a download link (?itour_doc=voucher|service&ref=…&key=…), the order emails
 * (voucher to the guest, service orders to the shop's "New order" email), the order page in My account and
 * on the thank-you page, a box on the admin order screen, and the Booking report screen.
 *
 * @package ITour\Documents
 */

namespace ITour\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Routes and hooks.
 */
final class Routes {

	/**
	 * Staff capability (module capability, granted to administrators).
	 */
	private const CAP = 'itour_manage_documents';

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'template_redirect', array( $this, 'download' ), 1 );
		add_action( 'admin_post_itour_documents_report', array( $this, 'report' ) );
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach' ), 10, 3 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'order_links' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
	}

	/**
	 * Link to a document. The order key lets the guest open their own voucher without signing in.
	 *
	 * @param string              $doc 'voucher' | 'service'.
	 * @param array<string,mixed> $b   Booking.
	 */
	public static function url( string $doc, array $b ): string {
		return add_query_arg(
			array_filter(
				array(
					'itour_doc' => $doc,
					'ref'       => $b['ref'],
					'key'       => 'voucher' === $doc && $b['order'] ? $b['order']->get_order_key() : '',
				)
			),
			home_url( '/' )
		);
	}

	/**
	 * File name: "Travel-Voucher-EX-2026-00012.pdf".
	 *
	 * @param string $doc 'voucher' | 'service'.
	 * @param string $ref Reference.
	 */
	private static function file_name( string $doc, string $ref ): string {
		return sanitize_file_name( ( 'voucher' === $doc ? 'Travel-Voucher-' : 'Service-Order-' ) . $ref . '.pdf' );
	}

	/**
	 * PDF bytes of one document.
	 *
	 * @param string              $doc 'voucher' | 'service'.
	 * @param array<string,mixed> $b   Booking.
	 */
	private static function pdf( string $doc, array $b ): string {
		return Pdf::render( 'voucher' === $doc ? Pdf::voucher( $b ) : Pdf::service_order( $b ) );
	}

	/**
	 * Send a PDF to the browser and stop.
	 *
	 * @param string $bytes PDF.
	 * @param string $name  File name.
	 */
	private static function send( string $bytes, string $name ): void {
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . $name . '"' );
		header( 'X-Robots-Tag: noindex' );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF.
		exit;
	}

	/**
	 * ?itour_doc=voucher|service&ref=…: guests with the order key (or signed in as the customer) get their
	 * voucher; staff get any document.
	 */
	public function download(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the order key is the proof, like WooCommerce's order links.
		$doc = isset( $_GET['itour_doc'] ) ? sanitize_key( wp_unslash( $_GET['itour_doc'] ) ) : '';
		if ( ! in_array( $doc, array( 'voucher', 'service' ), true ) ) {
			return;
		}
		$b   = Data::booking( sanitize_text_field( wp_unslash( $_GET['ref'] ?? '' ) ) );
		$key = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
		// phpcs:enable
		$staff = current_user_can( self::CAP );
		$guest = $b && $b['order'] && 'voucher' === $doc && ( ( '' !== $key && hash_equals( $b['order']->get_order_key(), $key ) ) || ( is_user_logged_in() && (int) $b['order']->get_customer_id() === get_current_user_id() ) );
		if ( ! $b || ! ( $staff || $guest ) ) {
			wp_die( esc_html__( 'This document link is not valid. Open it from your booking email or your account.', 'itour-documents' ), '', array( 'response' => 403 ) );
		}
		self::send( self::pdf( $doc, $b ), self::file_name( $doc, $b['ref'] ) );
	}

	/**
	 * Attach vouchers to the guest's order emails and service orders to the shop's "New order" email.
	 *
	 * @param array<int,string> $files    Attachments.
	 * @param string            $email_id WooCommerce email id.
	 * @param mixed             $order    Object of the email.
	 * @return array<int,string>
	 */
	public function attach( $files, $email_id, $order ): array {
		$files = (array) $files;
		$doc   = in_array( $email_id, array( 'customer_processing_order', 'customer_completed_order', 'customer_on_hold_order' ), true ) ? 'voucher' : ( 'new_order' === $email_id ? 'service' : '' );
		if ( '' === $doc || ! $order instanceof \WC_Order ) {
			return $files;
		}
		foreach ( Data::of_order( $order->get_id() ) as $b ) {
			if ( 'cancelled' === $b['status'] ) {
				continue;
			}
			try {
				$path = Pdf::temp_dir() . '/' . self::file_name( $doc, $b['ref'] );
				file_put_contents( $path, self::pdf( $doc, $b ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$files[] = $path;
			} catch ( \Throwable $e ) {
				// A document must never stop the order email.
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->error( 'Travel document ' . $b['ref'] . ': ' . $e->getMessage(), array( 'source' => 'itour-documents' ) );
				}
			}
		}
		return $files;
	}

	/**
	 * "Travel vouchers" on the thank-you page and the order in My account.
	 *
	 * @param mixed $order Order.
	 */
	public function order_links( $order ): void {
		$rows = $order instanceof \WC_Order ? array_filter( Data::of_order( $order->get_id() ), static fn( $b ) => 'cancelled' !== $b['status'] ) : array();
		if ( ! $rows ) {
			return;
		}
		echo '<section class="itour-docs"><h2>' . esc_html__( 'Travel vouchers', 'itour-documents' ) . '</h2><ul>';
		foreach ( $rows as $b ) {
			echo '<li><a href="' . esc_url( self::url( 'voucher', $b ) ) . '" target="_blank" rel="noopener">' . esc_html( $b['title'] ) . ' · ' . esc_html( $b['ref'] ) . ' (PDF)</a></li>';
		}
		echo '</ul></section>';
	}

	/**
	 * Box on the admin order screen (classic and HPOS).
	 */
	public function meta_box(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		foreach ( array( 'shop_order', 'woocommerce_page_wc-orders' ) as $screen ) {
			add_meta_box(
				'itour-documents',
				__( 'Travel documents', 'itour-documents' ),
				static function ( $post_or_order ): void {
					$id   = $post_or_order instanceof \WP_Post ? $post_or_order->ID : ( $post_or_order instanceof \WC_Order ? $post_or_order->get_id() : 0 );
					$rows = Data::of_order( $id );
					if ( ! $rows ) {
						echo '<p>' . esc_html__( 'No excursion or transfer bookings in this order.', 'itour-documents' ) . '</p>';
						return;
					}
					foreach ( $rows as $b ) {
						echo '<p><strong>' . esc_html( $b['ref'] ) . '</strong><br>' . esc_html( $b['title'] ) . '<br><a href="' . esc_url( self::url( 'voucher', $b ) ) . '" target="_blank">' . esc_html__( 'Travel voucher', 'itour-documents' ) . '</a> · <a href="' . esc_url( self::url( 'service', $b ) ) . '" target="_blank">' . esc_html__( 'Service order', 'itour-documents' ) . '</a></p>';
					}
				},
				$screen,
				'side'
			);
		}
	}

	/**
	 * iTour → Booking report: pick the dates, get the PDF.
	 */
	public static function report_screen(): void {
		$from = wp_date( 'Y-m-01' );
		$to   = wp_date( 'Y-m-t' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Booking report', 'itour-documents' ) . '</h1><p>' . esc_html__( 'Every excursion and transfer booking with a service date in this range: guest, people, price, paid and still to collect, with totals per currency. Opens as a PDF (A4 landscape).', 'itour-documents' ) . '</p>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" target="_blank"><input type="hidden" name="action" value="itour_documents_report">';
		wp_nonce_field( 'itour_documents_report', '_wpnonce', false );
		echo '<p><label>' . esc_html__( 'From', 'itour-documents' ) . ' <input type="date" name="from" value="' . esc_attr( $from ) . '" required></label> <label>' . esc_html__( 'To', 'itour-documents' ) . ' <input type="date" name="to" value="' . esc_attr( $to ) . '" required></label> ';
		submit_button( __( 'Open report', 'itour-documents' ), 'primary', 'submit', false );
		echo '</p></form></div>';
	}

	/**
	 * Report PDF.
	 */
	public function report(): void {
		check_admin_referer( 'itour_documents_report' ); // Dies on a bad nonce.
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'itour-documents' ), '', array( 'response' => 403 ) );
		}
		$day  = static fn( string $k, string $def ): string => preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET[ $k ] ?? '' ) ) ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : $def;
		$from = $day( 'from', wp_date( 'Y-m-01' ) );
		$to   = max( $from, $day( 'to', wp_date( 'Y-m-t' ) ) );
		self::send( Pdf::render( Pdf::report( Data::range( $from, $to ), $from, $to ), 'A4-L' ), sanitize_file_name( "Booking-Report-$from-$to.pdf" ) );
	}
}
