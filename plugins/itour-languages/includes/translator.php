<?php
/**
 * Machine translation with Google Gemini (free tier friendly: one request per post and language, with backoff),
 * plus the background queue that keeps translations up to date when English content changes.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Translate a set of English strings (key => text) into $lang. Returns key => text or WP_Error.
 * With $from = 'auto' the source language is detected and returned as the extra key __source_lang.
 * HTML tags, [shortcodes], URLs, %%placeholders%%, numbers and prices must come back unchanged.
 */
function itour_lang_gemini( array $fields, $lang, $attempts = 4, $timeout = 120, $from = 'en' ) {
	$s = itour_lang_settings();
	if ( empty( $s['api_key'] ) ) {
		$s['api_key'] = (string) ( get_option( 'itour_ai_settings', array() )['api_key'] ?? '' ); // Same key as iTour AI Mode.
	}
	if ( empty( $s['api_key'] ) ) {
		return new WP_Error( 'rl_no_key', 'Gemini API key is not set (Settings › AI Translations or iTour › AI Mode).' );
	}
	$all    = itour_lang_all();
	$target = $all[ $lang ][0] . ' (' . $all[ $lang ][1] . ')';
	$source = 'auto' === $from ? 'whatever language it is written in' : $all[ $from ][0];
	$system = "You are a professional travel-industry translator for " . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ", a travel company. Translate every JSON value from $source into $target.\n"
		. "Rules:\n"
		. "- Return ONLY a JSON object with exactly the same keys.\n"
		. "- Keep all HTML tags and attributes, WordPress shortcodes in [square brackets], URLs, email addresses, %%placeholders%%, printf placeholders like %s or %1\$s, numbers, prices, currency codes and dates exactly as they are; translate only the human-readable text between them.\n"
		. "- Inside shortcodes, translate ONLY the values of these visible-text attributes: title, subtitle, heading, description, more_text, button_text, text, label, placeholder. Leave every other attribute name and value unchanged.\n"
		. "- Some values are short website interface labels (buttons, form labels, menu items); translate them as a native website would, concise and natural.\n"
		. "- Use the established $target names for places, monuments and pharaohs where they exist (e.g. Giza, Luxor, Karnak, Ramses II, Tutankhamun); keep the company name " . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . " unchanged.\n"
		. "- Natural, persuasive marketing tone for travellers; keep meaning, structure and length similar. Do not add or remove information.\n"
		. "- Write grammatically perfect, native-speaker $target (correct cases, genitives, compound words and spelling). Proofread every value before answering.\n"
		. '- If a value is empty, return it empty.';
	if ( 'auto' === $from ) {
		$system .= "\n- Values already in $target stay as they are. Also add the key \"__source_lang\" with the ISO 639-1 code of the language most of the text was written in (en if it is already English).";
	}
	$body   = array(
		'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
		'contents'          => array( array( 'role' => 'user', 'parts' => array( array( 'text' => wp_json_encode( $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) ) ),
		'generationConfig'  => array( 'responseMimeType' => 'application/json', 'temperature' => 0.2 ),
	);
	// Main model first; if Google reports it overloaded or it times out, the fast Flash-Lite model takes over.
	$models   = array_values( array_unique( array( $s['model'], 'gemini-flash-lite-latest' ) ) );
	$model_ix = 0;

	for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
		$model = $models[ $model_ix ];
		$url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';
		$body['generationConfig'] = array( 'responseMimeType' => 'application/json', 'temperature' => 0.2 );
		if ( preg_match( '/^gemini-3/', $model ) ) {
			$body['generationConfig']['thinkingConfig'] = array( 'thinkingLevel' => 'low' );
		}
		$res = wp_remote_post( $url, array(
			'timeout' => $timeout,
			'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => $s['api_key'] ),
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $res ) ) {
			$err = $res;
			if ( $model_ix < count( $models ) - 1 ) {
				$model_ix++; // timed out: switch to the fallback model straight away
				$attempts++;
				continue;
			}
			if ( $attempt < $attempts ) {
				sleep( 5 * $attempt );
			}
			continue;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ( 503 === $code || 500 === $code ) && $model_ix < count( $models ) - 1 ) {
			$model_ix++; // overloaded: switch to the fallback model straight away
			$attempts++;
			continue;
		}
		if ( 429 === $code || 503 === $code || 500 === $code ) {
			// Free tier rate limit / overload: wait as long as Google asks (default 30 s), then retry.
			$wait = 30;
			foreach ( (array) ( $data['error']['details'] ?? array() ) as $d ) {
				if ( isset( $d['retryDelay'] ) ) {
					$wait = max( 5, (int) $d['retryDelay'] + 2 );
				}
			}
			// A wait of many minutes means the daily free quota of this model is used up.
			if ( 429 === $code && $wait > 300 ) {
				if ( $model_ix < count( $models ) - 1 ) {
					$model_ix++;
					$attempts++;
					continue;
				}
				return new WP_Error( 'rl_quota', 'Daily free-tier quota of ' . $model . ' reached (resets in ~' . round( $wait / 3600, 1 ) . ' h).' );
			}
			$err = new WP_Error( 'rl_rate', 'Gemini busy/rate limited (HTTP ' . $code . ')' );
			if ( $attempt >= $attempts ) {
				break;
			}
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::log( "  rate limited, waiting {$wait}s…" );
			}
			sleep( min( $wait, 90 ) );
			continue;
		}
		if ( 200 !== $code ) {
			return new WP_Error( 'rl_http', 'Gemini HTTP ' . $code . ': ' . ( $data['error']['message'] ?? 'unknown error' ) );
		}
		$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		$out  = json_decode( preg_replace( '/^```(?:json)?\s*|\s*```$/', '', trim( $text ) ), true );
		if ( ! is_array( $out ) ) {
			$err = new WP_Error( 'rl_json', 'Gemini returned no valid JSON' );
			continue;
		}
		return $out;
	}
	return $err ?? new WP_Error( 'rl_fail', 'Gemini request failed' );
}

/**
 * Structural check: shortcodes and layout tags (paragraphs, headings, lists, tables, links, images) must survive
 * translation. Inline formatting such as <br>, <strong>, <em> may legitimately move with the wording.
 */
function itour_lang_same_markup( $a, $b ) {
	$sig = function ( $s ) {
		preg_match_all( '/\[\/?[a-z0-9_\-]+|<\/?(?:p|h[1-6]|ul|ol|li|div|section|table|thead|tbody|tr|td|th|a|img|iframe|figure|blockquote)\b/i', (string) $s, $m );
		return implode( ',', array_map( 'strtolower', $m[0] ) );
	};
	return $sig( $a ) === $sig( $b );
}

/** Long pages are sent in chunks of ~12k characters to stay well inside model limits. */
function itour_lang_chunks( array $fields ) {
	$chunks = array( array() );
	$size   = 0;
	foreach ( $fields as $f => $v ) {
		if ( $size > 0 && $size + strlen( $v ) > 12000 ) {
			$chunks[] = array();
			$size     = 0;
		}
		$chunks[ count( $chunks ) - 1 ][ $f ] = $v;
		$size += strlen( $v );
	}
	return $chunks;
}

/**
 * Translate one post into one language. Only fields that are missing or whose English changed are sent.
 * Returns number of fields saved, or WP_Error.
 */
function itour_lang_translate_post( $post_id, $lang, $force = false, $interactive = false ) {
	$src = itour_lang_post_source( $post_id );
	if ( ! $src ) {
		return 0;
	}
	$have = itour_lang_get( 'post', $post_id, $lang );
	$todo = array();
	foreach ( $src as $f => $v ) {
		// The author's own text (written in this language) is never replaced by a machine translation of itself.
		if ( isset( $have[ $f ] ) && 'original' === $have[ $f ]->status && $have[ $f ]->source_hash === md5( $v ) ) {
			continue;
		}
		if ( $force || ! isset( $have[ $f ] ) || $have[ $f ]->source_hash !== md5( $v ) ) {
			$todo[ $f ] = $v;
		}
	}
	if ( ! $todo ) {
		return 0;
	}
	$chunks = itour_lang_chunks( $todo );
	$saved  = 0;
	foreach ( $chunks as $chunk ) {
		// From the admin button: one quick attempt so the browser never hits the 60 s gateway limit.
		$out = $interactive ? itour_lang_gemini( $chunk, $lang, 1, 45 ) : itour_lang_gemini( $chunk, $lang );
		if ( is_wp_error( $out ) ) {
			return $saved ? $saved : $out;
		}
		foreach ( $chunk as $f => $v ) {
			$t = isset( $out[ $f ] ) && is_string( $out[ $f ] ) ? trim( $out[ $f ] ) : '';
			if ( '' === $t ) {
				continue;
			}
			if ( 'content' === $f && ! itour_lang_same_markup( $v, $t ) ) {
				// Layout changed: ask once more for this field alone; if it still differs keep English rather than break the page.
				$retry = itour_lang_gemini( array( 'content' => $v ), $lang, $interactive ? 1 : 3, $interactive ? 45 : 120 );
				$t     = ( ! is_wp_error( $retry ) && isset( $retry['content'] ) && is_string( $retry['content'] ) ) ? trim( $retry['content'] ) : '';
				if ( '' === $t || ! itour_lang_same_markup( $v, $t ) ) {
					continue;
				}
			}
			itour_lang_save( 'post', $post_id, $lang, $f, $t, md5( $v ) );
			$saved++;
		}
		if ( count( $chunks ) > 1 ) {
			sleep( 6 );
		}
	}
	return $saved;
}

/* ---------------- Background queue (WP-Cron) ---------------- */

function itour_lang_queue_add( $post_id ) {
	$q = get_option( 'itour_lang_queue', array() );
	foreach ( array_keys( array_diff_key( itour_lang_enabled(), array( 'en' => 1 ) ) ) as $l ) {
		$q[ $post_id . ':' . $l ] = time();
	}
	update_option( 'itour_lang_queue', $q, false );
}

// English edited → refresh its translations in the background.
add_action( 'save_post', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || 'publish' !== $post->post_status || ! in_array( $post->post_type, itour_lang_post_types(), true ) ) {
		return;
	}
	// Text written in another language is first turned into English (source-language.php), then translated.
	if ( itour_lang_settings()['api_key'] && ! itour_lang_src_queued( $post_id ) ) {
		itour_lang_queue_add( $post_id );
	}
}, 20, 2 );

add_filter( 'cron_schedules', function ( $s ) {
	$s['itour_lang_2min'] = array( 'interval' => 120, 'display' => 'Every 2 minutes (iTour AI Translations)' );
	return $s;
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'itour_lang_cron' ) ) {
		wp_schedule_event( time() + 60, 'itour_lang_2min', 'itour_lang_cron' );
	}
} );

// A few items per run keeps us well inside the Gemini free-tier rate limits.
add_action( 'itour_lang_cron', function () {
	$q = get_option( 'itour_lang_queue', array() );
	if ( ! $q || get_transient( 'itour_lang_cron_lock' ) ) {
		return;
	}
	set_transient( 'itour_lang_cron_lock', 1, 110 );
	$n = 0;
	foreach ( array_keys( $q ) as $key ) {
		list( $pid, $lang ) = explode( ':', $key );
		$r = itour_lang_translate_post( (int) $pid, $lang );
		if ( is_wp_error( $r ) && 'rl_http' !== $r->get_error_code() ) {
			break; // rate limited or no key: try again next run
		}
		unset( $q[ $key ] );
		if ( ++$n >= 3 ) {
			break;
		}
		sleep( 7 );
	}
	update_option( 'itour_lang_queue', $q, false );
	delete_transient( 'itour_lang_cron_lock' );
} );
