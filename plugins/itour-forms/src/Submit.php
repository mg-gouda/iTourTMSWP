<?php
/**
 * Form submissions: spam checks, validation, storage, email. One handler for normal posts
 * (redirect back) and in-page submits (JSON).
 *
 * @package ITour\Forms
 */

namespace ITour\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Handles POST admin-post.php?action=itour_form.
 */
final class Submit {

	private const MIN_SECONDS = 3;  // Faster than this is a bot.
	private const PER_HOUR    = 10; // Submissions per IP per hour.

	/**
	 * The block's settings that matter on the server, signed so visitors can't change them
	 * (e.g. the recipient, which would turn the form into a spam relay).
	 *
	 * @param array<string,mixed> $a Block attributes.
	 * @return array{cfg:string,sig:string}
	 */
	public static function sign( array $a ): array {
		$cfg = wp_json_encode(
			array(
				'form'     => sanitize_key( (string) ( $a['formName'] ?? 'contact' ) ),
				'to'       => sanitize_email( (string) ( $a['recipient'] ?? '' ) ),
				'phone'    => ! empty( $a['requirePhone'] ),
				'fields'   => array_values( array_filter( array( ! empty( $a['showPhone'] ) ? 'phone' : '', ! empty( $a['showSubject'] ) ? 'subject' : '', ! empty( $a['showTravelDate'] ) ? 'travel_date' : '', ! empty( $a['showTravellers'] ) ? 'travellers' : '' ) ) ),
				'success'  => '' !== trim( (string) ( $a['successMessage'] ?? '' ) ) ? sanitize_text_field( (string) $a['successMessage'] ) : __( 'Thank you! We’ll reply within one working day.', 'itour-forms' ),
				'rendered' => time(),
			)
		);
		return array(
			'cfg' => (string) $cfg,
			'sig' => wp_hash( (string) $cfg, 'nonce' ),
		);
	}

	/**
	 * Process a submission.
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form: signed config + honeypot + time trap + rate limit instead of a nonce (nonces break with page caching).
		$json = isset( $_SERVER['HTTP_ACCEPT'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ), 'application/json' );
		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$cfg  = isset( $_POST['itour_cfg'] ) ? (string) wp_unslash( $_POST['itour_cfg'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by signature below.
		$sig  = isset( $_POST['itour_sig'] ) ? sanitize_text_field( wp_unslash( $_POST['itour_sig'] ) ) : '';
		$c    = json_decode( $cfg, true );

		if ( ! is_array( $c ) || ! hash_equals( wp_hash( $cfg, 'nonce' ), $sig ) ) {
			self::reply( $json, $back, false, __( 'This form has expired. Please reload the page and try again.', 'itour-forms' ) );
		}
		// Honeypot filled, or sent faster than a person could type: pretend success, store nothing.
		if ( ! empty( $_POST['itour_website'] ) || time() - (int) $c['rendered'] < self::MIN_SECONDS ) {
			self::reply( $json, $back, true, (string) $c['success'] );
		}
		$ip   = substr( hash( 'sha256', ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) . wp_salt() ), 0, 16 );
		$rate = 'itour_form_rate_' . $ip;
		if ( (int) get_transient( $rate ) >= self::PER_HOUR ) {
			self::reply( $json, $back, false, __( 'Too many messages from your connection. Please try again later.', 'itour-forms' ) );
		}

		$fields = (array) $c['fields'];
		$row    = array(
			'form'        => (string) $c['form'],
			'created_at'  => current_time( 'mysql', true ),
			'status'      => 'new',
			'name'        => sanitize_text_field( wp_unslash( $_POST['itour_name'] ?? '' ) ),
			'email'       => sanitize_email( wp_unslash( $_POST['itour_email'] ?? '' ) ),
			'phone'       => in_array( 'phone', $fields, true ) ? sanitize_text_field( wp_unslash( $_POST['itour_phone'] ?? '' ) ) : null,
			'subject'     => in_array( 'subject', $fields, true ) ? sanitize_text_field( wp_unslash( $_POST['itour_subject'] ?? '' ) ) : null,
			'travel_date' => in_array( 'travel_date', $fields, true ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_POST['itour_travel_date'] ?? '' ) ) ? sanitize_text_field( wp_unslash( $_POST['itour_travel_date'] ) ) : null, // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- regex-checked.
			'travellers'  => in_array( 'travellers', $fields, true ) && ! empty( $_POST['itour_travellers'] ) ? min( 500, absint( $_POST['itour_travellers'] ) ) : null,
			'message'     => sanitize_textarea_field( wp_unslash( $_POST['itour_message'] ?? '' ) ),
			'page_url'    => esc_url_raw( $back ),
			'ip_hash'     => $ip,
		);
		// phpcs:enable

		$errors = array();
		if ( '' === $row['name'] ) {
			$errors['name'] = __( 'Please enter your name.', 'itour-forms' );
		}
		if ( ! is_email( $row['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'itour-forms' );
		}
		if ( ! empty( $c['phone'] ) && '' === (string) $row['phone'] ) {
			$errors['phone'] = __( 'Please enter your phone number.', 'itour-forms' );
		}
		if ( '' === $row['message'] ) {
			$errors['message'] = __( 'Please write a message.', 'itour-forms' );
		}
		if ( $errors ) {
			self::reply( $json, $back, false, __( 'Please check the highlighted fields.', 'itour-forms' ), $errors );
		}

		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'itour_form_entries', $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		set_transient( $rate, (int) get_transient( $rate ) + 1, HOUR_IN_SECONDS );
		self::notify( $row, (string) $c['to'] );
		self::reply( $json, $back, true, (string) $c['success'] );
	}

	/**
	 * Email the site team.
	 *
	 * @param array<string,mixed> $row Entry.
	 * @param string              $to  Recipient from the block (or the company / admin email).
	 */
	private static function notify( array $row, string $to ): void {
		if ( ! is_email( $to ) ) {
			$company = class_exists( '\ITour\Core\Company' ) ? (string) ( \ITour\Core\Company::get()['email'] ?? '' ) : '';
			$to      = is_email( $company ) ? $company : (string) get_option( 'admin_email' );
		}
		$lines = array(
			__( 'Name', 'itour-forms' )        => $row['name'],
			__( 'Email', 'itour-forms' )       => $row['email'],
			__( 'Phone', 'itour-forms' )       => $row['phone'],
			__( 'Subject', 'itour-forms' )     => $row['subject'],
			__( 'Travel date', 'itour-forms' ) => $row['travel_date'],
			__( 'Travellers', 'itour-forms' )  => $row['travellers'],
			__( 'Sent from', 'itour-forms' )   => $row['page_url'],
		);
		$body  = '';
		foreach ( $lines as $label => $value ) {
			if ( null !== $value && '' !== (string) $value ) {
				$body .= $label . ': ' . $value . "\n";
			}
		}
		$body .= "\n" . $row['message'] . "\n\n" . admin_url( 'admin.php?page=itour-forms-enquiries' );
		/* translators: 1: site name, 2: sender name. */
		$subject = sprintf( __( '[%1$s] New enquiry from %2$s', 'itour-forms' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $row['name'] );
		wp_mail( $to, $subject, $body, array( 'Reply-To: ' . $row['name'] . ' <' . $row['email'] . '>' ) );
	}

	/**
	 * Answer as JSON (in-page submit) or redirect back (plain form post), then stop.
	 *
	 * @param bool                 $json   Whether the browser asked for JSON.
	 * @param string               $back   Page to return to.
	 * @param bool                 $ok     Success.
	 * @param string               $msg    Message for the visitor.
	 * @param array<string,string> $errors Field errors.
	 */
	private static function reply( bool $json, string $back, bool $ok, string $msg, array $errors = array() ): void {
		if ( $json ) {
			wp_send_json(
				array(
					'ok'      => $ok,
					'message' => $msg,
					'errors'  => (object) $errors,
				),
				$ok ? 200 : 422
			);
		}
		wp_safe_redirect( add_query_arg( 'itour_form', $ok ? 'sent' : 'error', $back ) );
		exit;
	}
}
