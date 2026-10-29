<?php
/**
 * The iTour admin menu: an overview page plus each module's screens.
 *
 * @package ITour\Core
 */

namespace ITour\Core\Admin;

use ITour\Core\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level menu and module submenus.
 */
final class Menu {

	/**
	 * The module registry.
	 *
	 * @var Modules
	 */
	private Modules $modules;

	/**
	 * Constructor.
	 *
	 * @param Modules $modules The module registry.
	 */
	public function __construct( Modules $modules ) {
		$this->modules = $modules;
	}

	/**
	 * Add the menu pages.
	 */
	public function register(): void {
		add_menu_page( 'iTour', 'iTour', 'manage_options', 'itour', array( $this, 'render_overview' ), 'dashicons-palmtree', 3 );
		add_submenu_page( 'itour', __( 'Overview', 'itour-core' ), __( 'Overview', 'itour-core' ), 'manage_options', 'itour', array( $this, 'render_overview' ) );
		foreach ( $this->modules->all() as $slug => $module ) {
			foreach ( $module->admin_pages() as $page => $screen ) {
				$hook = add_submenu_page( 'itour', $screen['title'], $screen['title'], $module->capability(), "itour-{$slug}-{$page}", $screen['callback'] );
				if ( $hook && isset( $screen['load'] ) ) {
					add_action( "load-{$hook}", $screen['load'] );
				}
			}
		}
	}

	/**
	 * List installed modules.
	 */
	public function render_overview(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'iTour', 'itour-core' ) . '</h1>';
		$modules = array_diff_key( $this->modules->all(), array( 'core' => 1 ) );
		if ( ! $modules ) {
			echo '<p>' . esc_html__( 'No modules are installed yet. Add a module plugin, such as iTour CRM, to get started.', 'itour-core' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Module', 'itour-core' ) . '</th><th>' . esc_html__( 'Version', 'itour-core' ) . '</th></tr></thead><tbody>';
		foreach ( $modules as $module ) {
			echo '<tr><td>' . esc_html( $module->name() ) . '</td><td>' . esc_html( $module->version() ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
