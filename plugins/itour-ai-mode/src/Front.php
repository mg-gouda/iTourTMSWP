<?php
/**
 * The assistant on the site: a floating button and chat panel on every page except cart and checkout.
 *
 * @package ITour\AiMode
 */

namespace ITour\AiMode;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end assets.
 */
final class Front {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Example requests: the saved ones, else made from the catalogue.
	 *
	 * @return string[]
	 */
	private static function chips(): array {
		$saved = array_filter( explode( "\n", (string) Settings::get()['chips'] ) );
		if ( $saved ) {
			return array_values( $saved );
		}
		$out = array();
		foreach ( Catalog::products() as $p ) {
			if ( 'excursion' === $p['kind'] && count( $out ) < 2 ) {
				/* translators: %s: day trip name. */
				$out[] = sprintf( __( '%s for 2 adults', 'itour-ai-mode' ), $p['title'] );
			}
		}
		if ( class_exists( '\ITour\Transfers\Module' ) ) {
			$out[] = __( 'Airport transfer to my hotel', 'itour-ai-mode' );
		}
		$out[] = __( 'What can I do in 3 days?', 'itour-ai-mode' );
		return $out;
	}

	/**
	 * Script, styles and texts.
	 */
	public function assets(): void {
		if ( ! Settings::active() || ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) ) {
			return;
		}
		$url  = plugin_dir_url( ITOUR_AI_FILE ) . 'assets/';
		$s    = Settings::get();
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		/* translators: %s: site name. */
		$name = '' !== (string) $s['name'] ? (string) $s['name'] : sprintf( __( '%s assistant', 'itour-ai-mode' ), $site );
		wp_enqueue_style( 'itour-ai', $url . 'ai-mode.css', array(), '0.1.0' );
		wp_enqueue_script( 'itour-ai', $url . 'ai-mode.js', array(), '0.1.0', array( 'strategy' => 'defer' ) );
		wp_localize_script(
			'itour-ai',
			'itourAi',
			array(
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'autoOpen' => is_front_page() && $s['auto_open'] ? 1 : 0,
				'i18n'     => array(
					'title'       => $name,
					'subtitle'    => __( 'Finds and books your trips', 'itour-ai-mode' ),
					'intro'       => __( 'Hi! Tell me what you’re looking for in your own words, for example “a day trip to Luxor for 2 next Friday” or “transfer from the airport to my hotel tomorrow at 3pm”. I’ll find the best options and book them for you, all the way to checkout.', 'itour-ai-mode' ),
					'placeholder' => __( 'Describe your trip…', 'itour-ai-mode' ),
					'listening'   => __( 'Listening…', 'itour-ai-mode' ),
					'thinking'    => __( 'Thinking…', 'itour-ai-mode' ),
					'send'        => __( 'Send', 'itour-ai-mode' ),
					'mic'         => __( 'Speak', 'itour-ai-mode' ),
					'voiceOn'     => __( 'Read replies aloud: on', 'itour-ai-mode' ),
					'voiceOff'    => __( 'Read replies aloud: off', 'itour-ai-mode' ),
					'restart'     => __( 'Start over', 'itour-ai-mode' ),
					'close'       => __( 'Close', 'itour-ai-mode' ),
					'open'        => __( 'Ask our assistant', 'itour-ai-mode' ),
					'error'       => __( 'Sorry, something went wrong. Please try again or use the search on the page.', 'itour-ai-mode' ),
					'disclaimer'  => __( 'AI assistant for our trips and bookings only. Prices are confirmed at checkout.', 'itour-ai-mode' ),
					'book'        => __( 'Book this', 'itour-ai-mode' ),
					'view'        => __( 'View', 'itour-ai-mode' ),
					'from'        => __( 'from', 'itour-ai-mode' ),
					'checkout'    => __( 'Taking you to checkout…', 'itour-ai-mode' ),
					'chips'       => array_slice( self::chips(), 0, 4 ),
				),
			)
		);
	}
}
