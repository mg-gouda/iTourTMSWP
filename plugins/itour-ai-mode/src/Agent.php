<?php
/**
 * The booking assistant. Stateless: each turn the browser sends the recent messages and the "draft" (what is
 * known about the booking so far); the server adds live site data (open dates, prices, vehicles) to Gemini's
 * instructions, and when the guest confirms, books through the modules' own Booking::book(), so prices, seats
 * and rules always come from the site, never from the AI.
 *
 * @package ITour\AiMode
 */

namespace ITour\AiMode;

defined( 'ABSPATH' ) || exit;

/**
 * Chat endpoint.
 */
final class Agent {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'wp_ajax_itour_ai_chat', array( $this, 'chat' ) );
		add_action( 'wp_ajax_nopriv_itour_ai_chat', array( $this, 'chat' ) );
		add_action(
			'save_post',
			static function ( $id, $post ): void {
				if ( in_array( $post->post_type, array( 'itour_tour', 'itour_excursion' ), true ) ) {
					delete_transient( 'itour_ai_products' );
				}
			},
			10,
			2
		);
	}

	/**
	 * Delete conversations older than the retention period (daily).
	 */
	public static function purge(): void {
		global $wpdb;
		$days = max( 1, (int) Settings::get()['retention_days'] );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $wpdb->prefix . 'itour_ai_logs', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Visitor key (hashed IP, never stored raw).
	 */
	private static function ip_hash(): string {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		return substr( hash( 'sha256', $ip . wp_salt( 'nonce' ) ), 0, 16 );
	}

	/**
	 * Requests must come from this site's pages (the chat has no login and its page is cached, so no nonce).
	 */
	private static function same_origin(): bool {
		$ref  = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ) ) );
		$host = wp_parse_url( $ref, PHP_URL_HOST );
		return $host && strtolower( (string) $host ) === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/**
	 * Per-visitor and site-wide limits: an error message, or ''.
	 */
	private static function limited(): string {
		$s   = Settings::get();
		$key = 'itour_ai_rl_' . self::ip_hash();
		$n   = (int) get_transient( $key );
		if ( $n >= (int) $s['per_minute'] ) {
			return __( 'You’re sending messages very quickly. Please wait a moment and try again.', 'itour-ai-mode' );
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		$day = 'itour_ai_day_' . wp_date( 'Ymd' );
		$d   = (int) get_transient( $day );
		if ( $d >= (int) $s['daily_cap'] ) {
			return __( 'The assistant is very busy today. Please use the search on the page or contact us.', 'itour-ai-mode' );
		}
		set_transient( $day, $d + 1, DAY_IN_SECONDS );
		return '';
	}

	/**
	 * The chat turn.
	 */
	public function chat(): void {
		if ( ! Settings::active() ) {
			wp_send_json(
				array(
					'intent' => 'error',
					'reply'  => __( 'The assistant isn’t available right now. Please use the search on the page.', 'itour-ai-mode' ),
				)
			);
		}
		if ( ! self::same_origin() ) {
			wp_send_json(
				array(
					'intent' => 'error',
					'reply'  => 'Forbidden',
				),
				403
			);
		}
		$limit = self::limited();
		if ( '' !== $limit ) {
			wp_send_json(
				array(
					'intent' => 'error',
					'reply'  => $limit,
				)
			);
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- same-origin check above; every field is checked below.
		$in       = json_decode( (string) wp_unslash( $_POST['payload'] ?? '' ), true );
		$in       = is_array( $in ) ? $in : array();
		$messages = array();
		foreach ( array_slice( (array) ( $in['messages'] ?? array() ), -16 ) as $m ) {
			if ( is_array( $m ) && in_array( $m['role'] ?? '', array( 'user', 'assistant' ), true ) ) {
				$text = trim( mb_substr( (string) ( $m['content'] ?? '' ), 0, 4000 ) );
				if ( '' !== $text ) {
					$messages[] = array(
						'role'    => $m['role'],
						'content' => $text,
					);
				}
			}
		}
		if ( ! $messages || 'user' !== end( $messages )['role'] ) {
			wp_send_json(
				array(
					'intent' => 'error',
					'reply'  => __( 'Please type your question.', 'itour-ai-mode' ),
				)
			);
		}
		$draft   = self::draft( is_array( $in['draft'] ?? null ) ? $in['draft'] : array() );
		$locale  = (string) preg_replace( '/[^a-zA-Z\-_]/', '', mb_substr( (string) ( $in['locale'] ?? '' ), 0, 10 ) );
		$session = (string) preg_replace( '/[^a-zA-Z0-9\-]/', '', mb_substr( (string) ( $in['session'] ?? '' ), 0, 40 ) );
		$last    = end( $messages )['content'];

		$res = self::gemini( Prompt::system( $draft, $locale ), $messages );
		if ( ! $res['ok'] ) {
			$reply = 'busy' === $res['error'] ? __( 'The assistant is busy right now. Please try again in a moment.', 'itour-ai-mode' ) : __( 'Sorry, I couldn’t process that. Please rephrase it or use the search on the page.', 'itour-ai-mode' );
			self::log( $session, $locale, $last, $reply, 'error', $draft );
			wp_send_json(
				array(
					'intent' => 'error',
					'reply'  => $reply,
					'draft'  => $draft,
				)
			);
		}
		$out    = (array) $res['data'];
		$draft  = self::draft( is_array( $out['draft'] ?? null ) ? $out['draft'] : $draft );
		$reply  = trim( (string) ( $out['reply'] ?? '' ) );
		$status = in_array( $out['status'] ?? '', array( 'collecting', 'ready', 'off_topic' ), true ) ? (string) $out['status'] : 'collecting';
		$lang   = preg_match( '/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,4})?$/', (string) ( $out['lang'] ?? '' ) ) ? (string) $out['lang'] : '';

		if ( 'ready' === $status && $draft['confirmed'] ) {
			$error = self::book( $draft );
			if ( '' === $error ) {
				$reply = '' !== $reply ? $reply : __( 'All set! Taking you to checkout to finish your booking.', 'itour-ai-mode' );
				self::log( $session, $locale, $last, $reply, 'complete', $draft, true );
				wp_send_json(
					array(
						'intent'   => 'complete',
						'reply'    => $reply,
						'lang'     => $lang,
						'redirect' => wc_get_checkout_url(),
						'draft'    => new \stdClass(),
					)
				);
			}
			$draft['confirmed'] = false;
			$reply              = $error;
			$status             = 'collecting';
		}
		if ( '' === $reply ) {
			$reply = __( 'Could you tell me a bit more?', 'itour-ai-mode' );
		}
		self::log( $session, $locale, $last, $reply, $status, $draft );
		wp_send_json(
			array(
				'intent' => $status,
				'reply'  => $reply,
				'lang'   => $lang,
				'cards'  => self::cards( (array) ( $out['suggest'] ?? array() ) ),
				'draft'  => $draft,
			)
		);
	}

	/**
	 * Keep only known draft fields, typed and trimmed.
	 *
	 * @param array<string,mixed> $d Draft from the browser or the model.
	 * @return array<string,mixed>
	 */
	public static function draft( array $d ): array {
		$str  = static fn( string $k, int $len = 120 ): string => isset( $d[ $k ] ) && is_scalar( $d[ $k ] ) ? mb_substr( sanitize_text_field( (string) $d[ $k ] ), 0, $len ) : '';
		$int  = static fn( string $k, int $max = 99 ): int => isset( $d[ $k ] ) && is_numeric( $d[ $k ] ) ? max( 0, min( $max, (int) $d[ $k ] ) ) : 0;
		$date = static fn( string $k ): string => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $str( $k, 10 ) ) ? $str( $k, 10 ) : '';
		$time = static fn( string $k ): string => preg_match( '/^\d{2}:\d{2}$/', $str( $k, 5 ) ) ? $str( $k, 5 ) : '';
		return array(
			'product'       => in_array( $d['product'] ?? '', array( 'tour', 'excursion', 'transfer' ), true ) ? $d['product'] : '',
			'item_id'       => $int( 'item_id', PHP_INT_MAX ),
			'date'          => $date( 'date' ),
			'time'          => $time( 'time' ),
			'adults'        => $int( 'adults' ),
			'children'      => $int( 'children' ),
			'infants'       => $int( 'infants' ),
			'variant'       => in_array( $d['variant'] ?? '', array( 'shared', 'private' ), true ) ? $d['variant'] : '',
			'pickup'        => in_array( $d['pickup'] ?? '', array( 'hotel', 'typed', 'meeting' ), true ) ? $d['pickup'] : '',
			'hotel'         => $str( 'hotel' ),
			'addons'        => array_values( array_filter( array_map( static fn( $a ) => is_scalar( $a ) ? mb_substr( sanitize_text_field( (string) $a ), 0, 60 ) : '', array_slice( (array) ( $d['addons'] ?? array() ), 0, 10 ) ) ) ),
			'from'          => $str( 'from' ),
			'to'            => $str( 'to' ),
			'pax'           => $int( 'pax' ),
			'trip'          => 'return' === ( $d['trip'] ?? '' ) ? 'return' : 'one',
			'flight'        => $str( 'flight', 12 ),
			'return_date'   => $date( 'return_date' ),
			'return_time'   => $time( 'return_time' ),
			'return_flight' => $str( 'return_flight', 12 ),
			'vehicle'       => $str( 'vehicle', 80 ),
			'seats'         => $int( 'seats', 9 ),
			'bags'          => $int( 'bags', 30 ),
			'notes'         => $str( 'notes', 300 ),
			'confirmed'     => ! empty( $d['confirmed'] ),
		);
	}

	/**
	 * Book the confirmed draft through the module's own booking code. Returns '' or the problem to tell the guest.
	 *
	 * @param array<string,mixed> $d Draft.
	 */
	private static function book( array $d ): string {
		$note = trim( $d['notes'] . ' (booked with the AI assistant)' );
		if ( 'excursion' === $d['product'] && class_exists( '\ITour\Excursions\Booking' ) ) {
			$post  = (int) $d['item_id'];
			$hotel = 0;
			foreach ( \ITour\Excursions\Booking::hotels( $post ) as $id => $h ) {
				if ( '' !== $d['hotel'] && false !== mb_stripos( $h['label'], $d['hotel'] ) ) {
					$hotel = $id;
					break;
				}
			}
			$mode   = '' !== $d['pickup'] ? $d['pickup'] : ( $hotel ? 'hotel' : ( '' !== $d['hotel'] ? 'typed' : 'meeting' ) );
			$errors = \ITour\Excursions\Booking::book(
				array(
					'post'     => $post,
					'slot'     => Catalog::slot( $post, $d ),
					'variant'  => '' !== $d['variant'] ? $d['variant'] : 'shared',
					'adults'   => $d['adults'],
					'children' => $d['children'],
					'infants'  => $d['infants'],
					'pickup'   => 'hotel' === $mode && ! $hotel ? 'typed' : $mode,
					'hotel'    => $hotel,
					'address'  => $d['hotel'],
					'addons'   => Catalog::addon_ids( $post, $d['addons'] ),
					'notes'    => $note,
				),
				false
			);
		} elseif ( 'transfer' === $d['product'] && class_exists( '\ITour\Transfers\Booking' ) ) {
			$vehicle = 0;
			foreach ( (array) ( Catalog::transfer_quote( $d )['vehicles'] ?? array() ) as $v ) {
				if ( 0 === strcasecmp( $v['name'], $d['vehicle'] ) ) {
					$vehicle = $v['id'];
				}
			}
			$errors = $vehicle ? \ITour\Transfers\Booking::book(
				array(
					'from'          => $d['from'],
					'to'            => $d['to'],
					'pax'           => max( 1, $d['pax'] ),
					'trip'          => $d['trip'],
					'date'          => $d['date'],
					'time'          => $d['time'],
					'flight'        => $d['flight'],
					'return_date'   => $d['return_date'],
					'return_time'   => $d['return_time'],
					'return_flight' => $d['return_flight'],
					'vehicle'       => $vehicle,
					'seats'         => $d['seats'],
					'bags'          => $d['bags'],
					'notes'         => $note,
				),
				false
			) : array( 'vehicle' => __( 'Please choose one of the vehicles I listed.', 'itour-ai-mode' ) );
		} else {
			return __( 'I can book day trips and transfers here. For tour packages, send an enquiry from the tour’s page.', 'itour-ai-mode' );
		}
		return $errors ? implode( ' ', array_map( 'strval', $errors ) ) : '';
	}

	/**
	 * Trip cards for suggested ids.
	 *
	 * @param array<int,mixed> $ids Ids.
	 * @return array<int,array<string,string>>
	 */
	private static function cards( array $ids ): array {
		$all = Catalog::products();
		$out = array();
		foreach ( array_slice( array_unique( array_map( 'intval', $ids ) ), 0, 4 ) as $id ) {
			if ( isset( $all[ $id ] ) ) {
				$p     = $all[ $id ];
				$out[] = array(
					'title' => $p['title'],
					'url'   => $p['url'],
					'image' => $p['image'],
					'meta'  => trim( $p['length'] . ( '' !== $p['place'] ? ' · ' . $p['place'] : '' ), ' ·' ),
					'price' => $p['from'] > 0 ? Catalog::money( (float) $p['from'] ) : '',
					'book'  => 'chat' === $p['booking'] ? '1' : '',
				);
			}
		}
		return $out;
	}

	/**
	 * Log one turn (no names, emails or IP addresses).
	 *
	 * @param string              $session Session id.
	 * @param string              $locale  Locale.
	 * @param string              $message Guest message.
	 * @param string              $reply   Reply.
	 * @param string              $intent  Status.
	 * @param array<string,mixed> $draft   Draft.
	 * @param bool                $booked  Booked.
	 */
	private static function log( string $session, string $locale, string $message, string $reply, string $intent, array $draft, bool $booked = false ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'itour_ai_logs',
			array(
				'session'    => '' !== $session ? $session : 'anon',
				'created_at' => current_time( 'mysql', true ),
				'locale'     => $locale,
				'ip_hash'    => self::ip_hash(),
				'message'    => mb_substr( $message, 0, 4000 ),
				'reply'      => mb_substr( $reply, 0, 4000 ),
				'intent'     => $intent,
				'product'    => (string) $draft['product'],
				'item_id'    => (int) $draft['item_id'],
				'booked'     => $booked ? 1 : 0,
			)
		);
	}

	/**
	 * Ask Gemini (main model, then the backup when busy or slow). JSON answers.
	 *
	 * @param string                          $system   Instructions.
	 * @param array<int,array<string,string>> $messages Conversation.
	 * @return array<string,mixed> [ ok, data | error ]
	 */
	private static function gemini( string $system, array $messages ): array {
		$s        = Settings::get();
		$contents = array_map(
			static fn( $m ) => array(
				'role'  => 'assistant' === $m['role'] ? 'model' : 'user',
				'parts' => array( array( 'text' => $m['content'] ) ),
			),
			$messages
		);
		$models   = array_values( array_unique( array_filter( array( (string) $s['model'], (string) $s['fallback_model'] ) ) ) );
		$result   = array(
			'ok'    => false,
			'error' => 'api',
		);
		foreach ( $models as $i => $model ) {
			$result = self::call( $model, (string) $s['api_key'], $system, $contents, count( $models ) > 1 && 0 === $i ? 15 : 45 );
			if ( $result['ok'] || ! in_array( $result['error'], array( 'busy', 'network' ), true ) ) {
				break;
			}
		}
		return $result;
	}

	/**
	 * One request to one model.
	 *
	 * @param string                         $model    Model id.
	 * @param string                         $key      API key.
	 * @param string                         $system   Instructions.
	 * @param array<int,array<string,mixed>> $contents Messages.
	 * @param int                            $timeout  Seconds.
	 * @return array<string,mixed>
	 */
	private static function call( string $model, string $key, string $system, array $contents, int $timeout ): array {
		$r = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent',
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $key,
				),
				'body'    => wp_json_encode(
					array(
						'system_instruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
						'contents'           => $contents,
						'generationConfig'   => array( 'responseMimeType' => 'application/json' ),
					)
				),
			)
		);
		if ( is_wp_error( $r ) ) {
			return array(
				'ok'    => false,
				'error' => 'network',
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$res  = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( 200 !== $code ) {
			return array(
				'ok'    => false,
				'error' => in_array( $code, array( 429, 500, 503, 504 ), true ) ? 'busy' : 'api',
			);
		}
		$text = '';
		foreach ( (array) ( $res['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			if ( empty( $part['thought'] ) && isset( $part['text'] ) ) {
				$text .= $part['text'];
			}
		}
		$data = json_decode( trim( (string) preg_replace( '/^```(?:json)?|```$/m', '', trim( $text ) ) ), true );
		return is_array( $data ) ? array(
			'ok'   => true,
			'data' => $data,
		) : array(
			'ok'    => false,
			'error' => 'parse',
		);
	}
}
