<?php
/**
 * Core plugin: boots the module registry, migrations and admin menu.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton holding the shared services.
 */
final class Plugin {

	/**
	 * The instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Installed modules.
	 *
	 * @var Modules
	 */
	private Modules $modules;

	/**
	 * Get the instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Currencies and rates.
	 *
	 * @var Currencies
	 */
	private Currencies $currencies;

	/**
	 * Document numbering.
	 *
	 * @var Sequences
	 */
	private Sequences $sequences;

	/**
	 * Set up services.
	 */
	private function __construct() {
		$this->modules    = new Modules();
		$this->currencies = new Currencies();
		$this->sequences  = new Sequences();
	}

	/**
	 * The module registry.
	 */
	public function modules(): Modules {
		return $this->modules;
	}

	/**
	 * Currencies and exchange rates.
	 */
	public function currencies(): Currencies {
		return $this->currencies;
	}

	/**
	 * Document numbering, e.g. `itour()->sequences()->next( 'booking', 'BK' )`.
	 */
	public function sequences(): Sequences {
		return $this->sequences;
	}

	/**
	 * Load modules and hook core services. Runs on `plugins_loaded` (priority 0).
	 */
	public function boot(): void {
		/**
		 * Modules register themselves here.
		 *
		 * @param Modules $modules The registry.
		 */
		$this->modules->add( new CoreModule() );
		do_action( 'itour/register_modules', $this->modules );
		$this->modules->boot();

		add_action( 'admin_init', array( new Migrator( $this->modules ), 'maybe_migrate' ) );
		add_action( 'admin_menu', array( new Admin\Menu( $this->modules ), 'register' ) );
		add_action( 'admin_notices', array( $this->modules, 'render_notices' ) );
		add_action(
			'admin_enqueue_scripts',
			static function ( string $hook ): void {
				if ( false !== strpos( $hook, 'itour' ) ) {
					wp_enqueue_style( 'itour-admin', plugins_url( 'assets/admin.css', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION );
				}
			}
		);
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'itour-core', false, dirname( plugin_basename( ITOUR_CORE_FILE ) ) . '/languages' );
	}
}
