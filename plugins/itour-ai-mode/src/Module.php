<?php
/**
 * AI Mode module: settings + conversations screen, the chat endpoint, the front-end assistant, log clean-up.
 *
 * @package ITour\AiMode
 */

namespace ITour\AiMode;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'ai';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'AI Mode', 'itour-ai-mode' );
	}

	/**
	 * Module version.
	 */
	public function version(): string {
		return '0.1.0';
	}

	/**
	 * Conversation log: one row per guest message and reply.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array(
			'ai_logs' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session varchar(40) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  locale varchar(10) NOT NULL DEFAULT '',
  ip_hash char(16) NOT NULL DEFAULT '',
  message text NOT NULL,
  reply text NOT NULL,
  intent varchar(20) NOT NULL DEFAULT '',
  product varchar(20) NOT NULL DEFAULT '',
  item_id bigint(20) unsigned NOT NULL DEFAULT 0,
  booked tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY session (session),
  KEY created_at (created_at)",
		);
	}

	/**
	 * Hooks.
	 */
	public function boot(): void {
		( new Agent() )->hooks();
		( new Front() )->hooks();
		$cap = $this->capability();
		add_action(
			'init',
			static function () use ( $cap ): void {
				Settings::register( $cap );
				if ( ! wp_next_scheduled( 'itour_ai_purge' ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'itour_ai_purge' );
				}
			},
			20
		);
		add_action( 'itour_ai_purge', array( Agent::class, 'purge' ) );
	}

	/**
	 * Under the iTour menu.
	 *
	 * @return array<string,mixed>
	 */
	public function menu(): array {
		return array();
	}

	/**
	 * Screens.
	 *
	 * @return array<string,array{title:string,callback:callable,load?:callable}>
	 */
	public function admin_pages(): array {
		return array(
			'settings' => array(
				'title'    => __( 'AI Mode', 'itour-ai-mode' ),
				'callback' => array( Settings::class, 'render' ),
			),
		);
	}
}
