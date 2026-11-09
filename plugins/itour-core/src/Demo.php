<?php
/**
 * Demo content: a home page with the booking widget, a few pages, a contact page and a main menu.
 * Added and removed from iTour → Overview. Every post it creates carries `_itour_demo`, so removal
 * only touches demo posts. No images (nothing third-party ships), so it works offline.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Adds and removes the demo.
 */
final class Demo {

	/**
	 * Option holding what the demo changed: menu id and the front page it replaced.
	 */
	private const STATE = 'itour_demo';

	/**
	 * Register the form handler.
	 */
	public function hooks(): void {
		add_action( 'admin_post_itour_demo', array( $this, 'handle' ) );
	}

	/**
	 * Add or remove, then back to the overview.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'itour-core' ), 403 );
		}
		check_admin_referer( 'itour_demo' );
		$add = 'add' === sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
		$add ? $this->add() : $this->remove();
		wp_safe_redirect( admin_url( 'admin.php?page=itour&demo=' . ( $add ? 'added' : 'removed' ) ) );
		exit;
	}

	/**
	 * Whether the demo is installed.
	 */
	public static function installed(): bool {
		return (bool) get_option( self::STATE );
	}

	/**
	 * The box on iTour → Overview.
	 */
	public static function render_box(): void {
		$done = sanitize_key( wp_unslash( $_GET['demo'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'added' === $done ) {
			printf( '<div class="notice notice-success"><p>%s <a href="%s">%s</a></p></div>', esc_html__( 'Demo content added.', 'itour-core' ), esc_url( home_url( '/' ) ), esc_html__( 'View site', 'itour-core' ) );
		} elseif ( 'removed' === $done ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Demo content moved to the trash.', 'itour-core' ) . '</p></div>';
		}
		$on = self::installed();
		echo '<div class="card" style="max-width:640px"><h2 class="title">' . esc_html__( 'Demo content', 'itour-core' ) . '</h2>';
		echo '<p>' . esc_html(
			$on
				? __( 'The demo pages are on your site. Remove them when your own pages are ready: they go to the trash, and your other pages and menus stay as they are.', 'itour-core' )
				: __( 'Adds a home page with the booking widget, Tours, Day trips, Transfers, About and Contact pages, and a main menu, so you can see the design with content. Your existing pages are not changed.', 'itour-core' )
		) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'itour_demo' );
		echo '<input type="hidden" name="action" value="itour_demo"><input type="hidden" name="do" value="' . ( $on ? 'remove' : 'add' ) . '">';
		submit_button( $on ? __( 'Remove demo content', 'itour-core' ) : __( 'Add demo content', 'itour-core' ), $on ? 'secondary' : 'primary', 'submit', false );
		echo '</form></div>';
	}

	/**
	 * Create the pages and menu. Does nothing if the demo is already in.
	 */
	public function add(): void {
		if ( self::installed() ) {
			return;
		}
		$state = array(
			'show_on_front' => get_option( 'show_on_front' ),
			'page_on_front' => (int) get_option( 'page_on_front' ),
			'primary'       => (int) ( get_theme_mod( 'nav_menu_locations', array() )['primary'] ?? 0 ),
		);
		$ids   = array();
		foreach ( $this->pages() as $title => $content ) {
			$ids[] = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => $content,
					'menu_order'   => count( $ids ),
					'meta_input'   => array( '_itour_demo' => 1 ),
				)
			);
		}
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids[0] );

		$menu = wp_create_nav_menu( __( 'Demo menu', 'itour-core' ) . ' ' . wp_rand( 100, 999 ) ); // Unique name: a re-add after removal can't clash.
		if ( ! is_wp_error( $menu ) ) {
			foreach ( array_slice( $ids, 1 ) as $id ) {
				wp_update_nav_menu_item(
					$menu,
					0,
					array(
						'menu-item-object-id' => $id,
						'menu-item-object'    => 'page',
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
					)
				);
			}
			$locations            = (array) get_theme_mod( 'nav_menu_locations', array() );
			$locations['primary'] = $menu;
			set_theme_mod( 'nav_menu_locations', $locations );
			$state['menu'] = $menu;
		}
		update_option( self::STATE, $state, false );
	}

	/**
	 * Trash the demo pages, delete its menu and put the front page and header menu back.
	 */
	public function remove(): void {
		$state = (array) get_option( self::STATE, array() );
		$ids   = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'any',
				'numberposts' => -1,
				'meta_key'    => '_itour_demo', // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'      => 'ids',
			)
		);
		// Put the old front page back only if a demo page is still the front page (the owner may have picked another).
		if ( isset( $state['show_on_front'] ) && in_array( (int) get_option( 'page_on_front' ), $ids, true ) ) {
			update_option( 'show_on_front', $state['show_on_front'] );
			update_option( 'page_on_front', $state['page_on_front'] );
		}
		array_map( 'wp_trash_post', $ids );
		if ( ! empty( $state['menu'] ) ) {
			$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
			if ( (int) ( $locations['primary'] ?? 0 ) === (int) $state['menu'] ) {
				$locations['primary'] = is_nav_menu( $state['primary'] ) ? $state['primary'] : 0;
				set_theme_mod( 'nav_menu_locations', $locations );
			}
			wp_delete_nav_menu( (int) $state['menu'] );
		}
		delete_option( self::STATE );
	}

	/**
	 * Page title → block content. The first one becomes the front page.
	 *
	 * @return array<string,string>
	 */
	private function pages(): array {
		$p    = static fn( string $html ): string => "<!-- wp:paragraph -->\n<p>" . $html . "</p>\n<!-- /wp:paragraph -->\n";
		$h    = static fn( string $text ): string => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $text ) . "</h2>\n<!-- /wp:heading -->\n";
		$pat  = static fn( string $slug ): string => '<!-- wp:pattern {"slug":"wp-itourtms/' . $slug . '"} /-->' . "\n";
		$list = static fn( array $items ): string => "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode( '', array_map( static fn( $i ) => "<!-- wp:list-item -->\n<li>" . esc_html( $i ) . "</li>\n<!-- /wp:list-item -->", $items ) ) . "</ul>\n<!-- /wp:list -->\n";
		$book = "<!-- wp:itour/booking-widget {\"align\":\"wide\"} /-->\n";

		return array(
			__( 'Home', 'itour-core' )      => $pat( 'hero' ) . $book . $pat( 'why-book' ) . $pat( 'call-to-action' ),
			__( 'Tours', 'itour-core' )     => $p( esc_html__( 'Multi-day packages with hotels, guides and transfers included. Nile cruises, Cairo and the pyramids, Luxor and Aswan, the Red Sea coast.', 'itour-core' ) )
				. $book
				. $list( array( __( 'Classic Egypt, 8 days: Cairo, Nile cruise, Luxor', 'itour-core' ), __( 'Red Sea and Cairo, 7 days', 'itour-core' ), __( 'Desert and oases, 5 days', 'itour-core' ) ) ),
			__( 'Day trips', 'itour-core' ) => $p( esc_html__( 'Excursions from your hotel, with pick-up and drop-off.', 'itour-core' ) )
				. $list( array( __( 'Pyramids of Giza and the Egyptian Museum', 'itour-core' ), __( 'Luxor from Hurghada', 'itour-core' ), __( 'Snorkelling trip to Giftun Island', 'itour-core' ) ) ),
			__( 'Transfers', 'itour-core' ) => $p( esc_html__( 'Private airport and hotel transfers with a fixed price per car, not per person. Your driver waits at arrivals with your name.', 'itour-core' ) )
				. "<!-- wp:itour/booking-widget {\"tabs\":[\"transfers\"]} /-->\n", // Hidden from visitors until iTour Transfers is active.
			__( 'About', 'itour-core' )     => $h( __( 'A local travel company', 'itour-core' ) )
				. $p( esc_html__( 'We are a destination management company: we plan, book and run every part of the trip ourselves, from the airport pick-up to the last excursion. Replace this text with your own story.', 'itour-core' ) ),
			__( 'Contact', 'itour-core' )   => $p( esc_html__( 'Tell us when you want to travel and what you would like to see. We reply within one working day.', 'itour-core' ) )
				. "<!-- wp:itour/contact-form /-->\n",
		);
	}
}
