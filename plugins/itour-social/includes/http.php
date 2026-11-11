<?php
/**
 * One HTTP helper for every channel: JSON in/out, errors become exceptions with the API's own message.
 */

defined( 'ABSPATH' ) || exit;

/** Thrown by a channel when the post cannot be made; the message is shown in the queue. */
class Itsoc_Error extends Exception {
	/** True when trying again later may help (rate limit, timeout, server error). */
	public $retry;

	public function __construct( $message, $retry = false ) {
		parent::__construct( $message );
		$this->retry = $retry;
	}
}

/**
 * @param string $method GET|POST|PUT|DELETE.
 * @param array  $o      json (array, sent as JSON), form (array, sent url-encoded), body (raw), headers, timeout.
 * @return array { code, json, raw, headers }
 */
function itsoc_http( $method, $url, array $o = array() ) {
	$headers = $o['headers'] ?? array();
	$body    = $o['body'] ?? null;
	if ( isset( $o['json'] ) ) {
		$headers['Content-Type'] = 'application/json';
		$body                    = wp_json_encode( $o['json'] );
	} elseif ( isset( $o['form'] ) ) {
		$body = $o['form'];
	}
	$r = wp_remote_request( $url, array(
		'method'  => $method,
		'headers' => $headers,
		'body'    => $body,
		'timeout' => $o['timeout'] ?? 45,
	) );
	if ( is_wp_error( $r ) ) {
		throw new Itsoc_Error( 'Connection failed: ' . $r->get_error_message(), true );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$raw  = (string) wp_remote_retrieve_body( $r );
	$out  = array(
		'code'    => $code,
		'json'    => json_decode( $raw, true ),
		'raw'     => $raw,
		'headers' => wp_remote_retrieve_headers( $r ),
	);
	if ( $code >= 400 && empty( $o['allow_error'] ) ) {
		throw new Itsoc_Error( 'HTTP ' . $code . ': ' . itsoc_api_message( $out ), 429 === $code || $code >= 500 );
	}
	return $out;
}

/** Pulls a readable message out of the different error shapes the APIs return. */
function itsoc_api_message( array $r ) {
	$j = $r['json'];
	if ( is_array( $j ) ) {
		foreach ( array( array( 'error', 'error_user_msg' ), array( 'error', 'message' ), array( 'error_description' ), array( 'message' ), array( 'detail' ), array( 'title' ), array( 'errors', 0, 'message' ), array( 'errors', 0, 'detail' ) ) as $path ) {
			$v = $j;
			foreach ( $path as $p ) {
				$v = is_array( $v ) && isset( $v[ $p ] ) ? $v[ $p ] : null;
			}
			if ( is_string( $v ) && '' !== $v ) {
				return $v;
			}
		}
		if ( isset( $j['error'] ) && is_string( $j['error'] ) ) {
			return $j['error'];
		}
	}
	return mb_substr( wp_strip_all_tags( $r['raw'] ), 0, 300 );
}
