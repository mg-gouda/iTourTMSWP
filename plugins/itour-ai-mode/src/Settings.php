<?php
/**
 * iTour → AI Mode: one tabbed screen (core Panel, saves as you go). Tabs: Assistant (on/off, greeting,
 * example questions), Gemini (key, models), Limits (per visitor, per day, log retention), Conversations.
 * The API key is never printed back: an empty field keeps the saved key.
 *
 * @package ITour\AiMode
 */

namespace ITour\AiMode;

use ITour\Core\Admin\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
final class Settings {

	/**
	 * Option name.
	 */
	public const OPTION = 'itour_ai_settings';

	/**
	 * Settings with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		return array_merge(
			array(
				'enabled'        => 0,
				'api_key'        => '',
				'model'          => 'gemini-3.8-flash',
				'fallback_model' => 'gemini-3.1-flash-lite', // When the main model is busy or slow.
				'name'           => '',                     // Empty: site name + "assistant".
				'chips'          => '',                     // Example requests, one per line.
				'auto_open'      => 1,                      // Open by itself once per visit on the home page.
				'per_minute'     => 20,
				'daily_cap'      => 1500,
				'retention_days' => 90,
			),
			(array) get_option( self::OPTION, array() )
		);
	}

	/**
	 * Shown on the site? (switched on and a key saved).
	 */
	public static function active(): bool {
		$s = self::get();
		return $s['enabled'] && '' !== $s['api_key'];
	}

	/**
	 * Merge values.
	 *
	 * @param array<string,mixed> $values Values.
	 */
	private static function put( array $values ): void {
		update_option( self::OPTION, array_merge( self::get(), $values ), false );
	}

	/**
	 * Chat models the saved key can use (from Google's list; cached 12 hours).
	 *
	 * @return array<string,string>
	 */
	public static function models(): array {
		$key = (string) self::get()['api_key'];
		if ( '' === $key ) {
			return array();
		}
		$cache = 'itour_ai_models_' . substr( md5( $key ), 0, 8 );
		$list  = get_transient( $cache );
		if ( is_array( $list ) ) {
			return $list;
		}
		$r    = wp_remote_get(
			'https://generativelanguage.googleapis.com/v1beta/models?pageSize=200',
			array(
				'timeout' => 20,
				'headers' => array( 'x-goog-api-key' => $key ),
			)
		);
		$j    = is_wp_error( $r ) ? array() : json_decode( (string) wp_remote_retrieve_body( $r ), true );
		$list = array();
		foreach ( (array) ( $j['models'] ?? array() ) as $m ) {
			$id = str_replace( 'models/', '', (string) ( $m['name'] ?? '' ) );
			if ( str_starts_with( $id, 'gemini-' ) && in_array( 'generateContent', (array) ( $m['supportedGenerationMethods'] ?? array() ), true )
				&& ! preg_match( '/tts|image|banana|robotics|computer-use|transcribe|embedding|omni|customtools|audio|live/', $id ) ) {
				$list[ $id ] = (string) ( $m['displayName'] ?? $id );
			}
		}
		if ( $list ) {
			set_transient( $cache, $list, 12 * HOUR_IN_SECONDS );
		}
		return $list;
	}

	/**
	 * Register the screen.
	 *
	 * @param string $cap Capability.
	 */
	public static function register( string $cap ): void {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Panel helpers escape.
		Panel::register_screen(
			'ai-settings',
			__( 'AI Mode', 'itour-ai-mode' ),
			$cap,
			array(
				'assistant'     => array(
					'label'  => __( 'Assistant', 'itour-ai-mode' ),
					'badge'  => static fn(): string => self::active() ? __( 'on', 'itour-ai-mode' ) : __( 'off', 'itour-ai-mode' ),
					'render' => static function (): void {
						$s = self::get();
						echo '<div class="itp-row itp-row--wide"><p class="itp-help">' . esc_html__( 'A booking assistant on every page (not on cart and checkout). Guests describe their trip in their own words, typed or spoken, in any language; it suggests your tours and day trips and books excursions and transfers up to checkout. It only knows your own trips, prices and rules, and every price and date is checked by the site before anything is booked.', 'itour-ai-mode' ) . '</p></div>';
						if ( '' === (string) $s['api_key'] ) {
							echo '<div class="itp-row itp-row--wide"><p class="itp-help" style="color:#8a5300;font-weight:500">' . esc_html__( 'Add your Gemini API key on the Gemini tab first.', 'itour-ai-mode' ) . '</p></div>';
						}
						echo Panel::row(
							__( 'Show the assistant', 'itour-ai-mode' ),
							Panel::choices(
								'assistant',
								'enabled',
								array(
									'1' => __( 'On', 'itour-ai-mode' ),
									'0' => __( 'Off', 'itour-ai-mode' ),
								),
								(string) (int) $s['enabled'],
								false
							)
						);
						echo Panel::row(
							__( 'Open by itself on the home page', 'itour-ai-mode' ),
							Panel::choices(
								'assistant',
								'auto_open',
								array(
									'1' => __( 'Yes, once per visit', 'itour-ai-mode' ),
									'0' => __( 'No', 'itour-ai-mode' ),
								),
								(string) (int) $s['auto_open'],
								false
							)
						);
						/* translators: %s: site name. */
						echo Panel::row( __( 'Name', 'itour-ai-mode' ), Panel::input( 'assistant', 'name', $s['name'], 'text', array( 'placeholder' => sprintf( __( '%s assistant', 'itour-ai-mode' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) ) ), __( 'Shown at the top of the chat. Empty = your site name + “assistant”.', 'itour-ai-mode' ), 'itp-assistant-name' );
						echo Panel::row( __( 'Example requests', 'itour-ai-mode' ), '<textarea class="itp-input" id="itp-assistant-chips" rows="4" name="' . esc_attr( Panel::name( 'assistant', 'chips' ) ) . '" placeholder="' . esc_attr__( "Airport transfer from Hurghada airport\nDay trip to Luxor for 2 adults", 'itour-ai-mode' ) . '">' . esc_textarea( (string) $s['chips'] ) . '</textarea>', __( 'One per line, up to 4. Shown as buttons before the first message. Empty = examples made from your trips.', 'itour-ai-mode' ), 'itp-assistant-chips' );
					},
					'save'   => static function ( int $unused, array $in ): void {
						self::put(
							array(
								'enabled'   => '1' === (string) ( $in['enabled'] ?? '0' ) ? 1 : 0,
								'auto_open' => '1' === (string) ( $in['auto_open'] ?? '1' ) ? 1 : 0,
								'name'      => sanitize_text_field( (string) ( $in['name'] ?? '' ) ),
								'chips'     => implode( "\n", array_slice( array_filter( array_map( 'sanitize_text_field', explode( "\n", (string) ( $in['chips'] ?? '' ) ) ) ), 0, 4 ) ),
							)
						);
					},
				),
				'gemini'        => array(
					'label'  => __( 'Gemini', 'itour-ai-mode' ),
					'badge'  => static fn(): string => '' !== (string) self::get()['api_key'] ? '✓' : '',
					'render' => static function (): void {
						$s      = self::get();
						$has    = '' !== (string) $s['api_key'];
						$models = self::models();
						echo '<div class="itp-row itp-row--wide"><p class="itp-help">' . esc_html__( 'The assistant runs on Google Gemini with your own API key: create one in Google AI Studio (aistudio.google.com → Get API key). Google bills your account for what guests send.', 'itour-ai-mode' ) . '</p></div>';
						echo Panel::row(
							__( 'API key', 'itour-ai-mode' ),
							'<input type="password" class="itp-input" id="itp-gemini-api_key" name="' . esc_attr( Panel::name( 'gemini', 'api_key' ) ) . '" value="" autocomplete="off" spellcheck="false" placeholder="' . esc_attr( $has ? __( 'Saved. Type a new key to replace it.', 'itour-ai-mode' ) : 'AIza…' ) . '">'
								. ( $has ? '<label class="itp-help" style="display:block;margin-top:6px"><input type="checkbox" name="' . esc_attr( Panel::name( 'gemini', 'remove_key' ) ) . '" value="1"> ' . esc_html__( 'Remove the saved key (switches the assistant off)', 'itour-ai-mode' ) . '</label>' : '' ),
							__( 'Kept in the database, never shown again on this page.', 'itour-ai-mode' ),
							'itp-gemini-api_key'
						);
						$pick = static function ( string $field, string $value ) use ( $models ): string {
							if ( ! $models ) {
								return Panel::input( 'gemini', $field, $value );
							}
							return Panel::select( 'gemini', $field, ( 'fallback_model' === $field ? array( '' => __( '— None —', 'itour-ai-mode' ) ) : array() ) + $models + ( '' !== $value && ! isset( $models[ $value ] ) ? array( $value => $value ) : array() ), $value );
						};
						echo Panel::row( __( 'Model', 'itour-ai-mode' ), $pick( 'model', (string) $s['model'] ), $models ? __( 'Models your key can use. A “flash” model is fast and cheap enough for chat.', 'itour-ai-mode' ) : __( 'Model id, e.g. gemini-3.8-flash. Save a key to choose from a list.', 'itour-ai-mode' ), 'itp-gemini-model' );
						echo Panel::row( __( 'Backup model', 'itour-ai-mode' ), $pick( 'fallback_model', (string) $s['fallback_model'] ), __( 'Used when the main model is busy or slow.', 'itour-ai-mode' ), 'itp-gemini-fallback_model' );
					},
					'save'   => static function ( int $unused, array $in ): void {
						$s   = self::get();
						$key = trim( (string) ( $in['api_key'] ?? '' ) );
						$id  = static fn( $v ): string => (string) preg_replace( '/[^a-z0-9.\-]/', '', strtolower( (string) $v ) );
						$new = array(
							'api_key'        => '' !== $key ? sanitize_text_field( $key ) : $s['api_key'],
							'model'          => '' !== $id( $in['model'] ?? '' ) ? $id( $in['model'] ) : $s['model'],
							'fallback_model' => $id( $in['fallback_model'] ?? '' ),
						);
						if ( ! empty( $in['remove_key'] ) ) {
							$new['api_key'] = '';
							$new['enabled'] = 0;
						}
						self::put( $new );
					},
				),
				'limits'        => array(
					'label'  => __( 'Limits', 'itour-ai-mode' ),
					'render' => static function (): void {
						$s = self::get();
						echo Panel::row(
							__( 'Messages per visitor per minute', 'itour-ai-mode' ),
							Panel::input(
								'limits',
								'per_minute',
								$s['per_minute'],
								'number',
								array(
									'min' => '1',
									'max' => '120',
								)
							),
							__( 'Stops one visitor or bot from running up your Gemini bill.', 'itour-ai-mode' ),
							'itp-limits-per_minute'
						);
						echo Panel::row(
							__( 'Messages per day, whole site', 'itour-ai-mode' ),
							Panel::input(
								'limits',
								'daily_cap',
								$s['daily_cap'],
								'number',
								array(
									'min' => '10',
									'max' => '100000',
								)
							),
							__( 'After this the assistant asks guests to use the normal search or contact you, until midnight.', 'itour-ai-mode' ),
							'itp-limits-daily_cap'
						);
						echo Panel::row(
							__( 'Keep conversations for (days)', 'itour-ai-mode' ),
							Panel::input(
								'limits',
								'retention_days',
								$s['retention_days'],
								'number',
								array(
									'min' => '1',
									'max' => '365',
								)
							),
							__( 'Older conversations are deleted every day.', 'itour-ai-mode' ),
							'itp-limits-retention_days'
						);
					},
					'save'   => static function ( int $unused, array $in ): void {
						self::put(
							array(
								'per_minute'     => max( 1, min( 120, (int) ( $in['per_minute'] ?? 20 ) ) ),
								'daily_cap'      => max( 10, min( 100000, (int) ( $in['daily_cap'] ?? 1500 ) ) ),
								'retention_days' => max( 1, min( 365, (int) ( $in['retention_days'] ?? 90 ) ) ),
							)
						);
					},
				),
				'conversations' => array(
					'label'  => __( 'Conversations', 'itour-ai-mode' ),
					'render' => array( self::class, 'conversations' ),
					'save'   => static function (): void {},
				),
			)
		);
		// phpcs:enable
	}

	/**
	 * Latest conversations, newest first: what guests asked and whether they booked.
	 */
	public static function conversations(): void {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT session, MIN(created_at) started, COUNT(*) n, MAX(booked) booked, MAX(product) product, SUBSTRING_INDEX(GROUP_CONCAT(message ORDER BY id SEPARATOR %s), %s, 1) first FROM %i GROUP BY session ORDER BY started DESC LIMIT 100', "\x1e", "\x1e", $wpdb->prefix . 'itour_ai_logs' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$days = (int) self::get()['retention_days'];
		/* translators: %d: days. */
		echo '<div class="itp-row itp-row--wide"><p class="itp-help">' . esc_html( sprintf( __( 'The last 100 conversations (kept %d days). Use them to see what guests ask for and what you don’t offer yet. No names or IP addresses are stored.', 'itour-ai-mode' ), $days ) ) . '</p></div>';
		if ( ! $rows ) {
			echo '<div class="itp-row itp-row--wide"><p>' . esc_html__( 'No conversations yet.', 'itour-ai-mode' ) . '</p></div>';
			return;
		}
		echo '<div class="itp-row itp-row--wide"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Started', 'itour-ai-mode' ) . '</th><th>' . esc_html__( 'First message', 'itour-ai-mode' ) . '</th><th>' . esc_html__( 'Messages', 'itour-ai-mode' ) . '</th><th>' . esc_html__( 'Booked', 'itour-ai-mode' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( wp_date( 'j M Y H:i', strtotime( $r['started'] . ' UTC' ) ) ) . '</td><td>' . esc_html( wp_html_excerpt( (string) $r['first'], 140, '…' ) ) . '</td><td>' . (int) $r['n'] . '</td><td>' . ( $r['booked'] ? '✓ ' . esc_html( (string) $r['product'] ) : '–' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * The screen.
	 */
	public static function render(): void {
		Panel::render_screen( 'ai-settings' );
	}
}
