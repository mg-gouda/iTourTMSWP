<?php
/**
 * Product cards for the front end: one card design shared by the "Trips" block (home page tabs and the
 * Tours / Day trips listing pages), related trips under a product page, and the "Destinations" block
 * (photo tiles built from the Location Tree).
 *
 * Modules plug in with filters:
 *   itour/card_types  slug => [ post type, tab label, listing page URL ]  (e.g. 'tours' => [ 'itour_tour', 'Tour packages', '/tours/' ])
 *   itour/card_facts  short facts on a card ( "4 days" ), given the post id
 *   itour/destination the Location Tree destination id of a product, given the post id
 *
 * @package ITour\Core
 */

namespace ITour\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Cards, grids and the two blocks.
 */
final class Cards {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action(
			'init',
			static function (): void {
				register_block_type( dirname( ITOUR_CORE_FILE ) . '/blocks/trips', array( 'render_callback' => array( self::class, 'trips_block' ) ) );
				register_block_type( dirname( ITOUR_CORE_FILE ) . '/blocks/destinations', array( 'render_callback' => array( self::class, 'destinations_block' ) ) );
			}
		);
	}

	/**
	 * Product types that have cards: slug => [ post type, label, listing URL ].
	 *
	 * @return array<string,array{0:string,1:string,2?:string}>
	 */
	public static function types(): array {
		$types = (array) apply_filters( 'itour/card_types', array() );
		return array_merge( array_intersect_key( array_flip( array( 'tours', 'excursions' ) ), $types ), $types ); // Tours first, whatever the plugin load order.
	}

	/**
	 * Destination id of a product (0 = none).
	 *
	 * @param int $post Post id.
	 */
	private static function destination( int $post ): int {
		return (int) apply_filters( 'itour/destination', 0, $post );
	}

	/**
	 * Destination names by id.
	 *
	 * @return array<int,string>
	 */
	private static function destination_names(): array {
		static $names = null;
		if ( null === $names ) {
			global $wpdb;
			$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM %i WHERE type = 'destination' AND active = 1", $wpdb->prefix . 'itour_locations' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$names = array_column( $rows, 'name', 'id' );
		}
		return $names;
	}

	/**
	 * One card.
	 *
	 * @param int $post Post id.
	 */
	public static function card( int $post ): string {
		$url   = (string) get_permalink( $post );
		$title = get_the_title( $post );
		$img   = get_post_thumbnail_id( $post ) ? wp_get_attachment_image( (int) get_post_thumbnail_id( $post ), 'medium_large', false, array( 'loading' => 'lazy' ) ) : '';
		$where = self::destination_names()[ self::destination( $post ) ] ?? '';
		$facts = array_filter( (array) apply_filters( 'itour/card_facts', array(), $post ) );
		$from  = (float) get_post_meta( $post, '_itour_price_from', true );
		$h     = '<article class="itc"><a class="itc__link" href="' . esc_url( $url ) . '">';
		$h    .= '<div class="itc__media">' . ( '' !== $img ? $img : '<span class="itc__noimg" aria-hidden="true"></span>' ) . ( '' !== $where ? '<span class="itc__where">' . esc_html( $where ) . '</span>' : '' ) . '</div>';
		$h    .= '<div class="itc__body"><h3 class="itc__title">' . esc_html( $title ) . '</h3>';
		if ( $facts ) {
			$h .= '<p class="itc__facts">' . esc_html( implode( ' · ', $facts ) ) . '</p>';
		}
		if ( $from > 0 ) {
			$h .= '<p class="itc__price"><span>' . esc_html__( 'From', 'itour-core' ) . '</span> <strong>' . esc_html( itour()->currencies()->format( $from ) ) . '</strong> <span>' . esc_html__( 'per adult', 'itour-core' ) . '</span></p>';
		}
		return $h . '</div></a></article>';
	}

	/**
	 * Cards in a grid.
	 *
	 * @param int[] $ids Post ids.
	 */
	public static function grid( array $ids ): string {
		self::style();
		return '<div class="itc-grid">' . implode( '', array_map( array( self::class, 'card' ), $ids ) ) . '</div>';
	}

	/**
	 * Card styles.
	 */
	private static function style(): void {
		wp_enqueue_style( 'itour-cards', plugins_url( 'assets/cards.css', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION );
	}

	/**
	 * Published products of a type, filtered by a typed place or word.
	 *
	 * @param string $type  Post type.
	 * @param int    $count Cards wanted (0 = all).
	 * @param string $where What the visitor typed ('' = everything).
	 * @return int[]
	 */
	public static function find( string $type, int $count, string $where = '' ): array {
		$args  = array(
			'post_type'   => $type,
			'post_status' => 'publish',
			'numberposts' => $count > 0 && '' === $where ? $count : 200, // ponytail: filters in PHP; fine for a DMC catalogue of a few hundred trips.
			'fields'      => 'ids',
			'orderby'     => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		);
		$ids   = array_map( 'intval', get_posts( $args ) );
		$where = trim( $where );
		if ( '' === $where ) {
			return $ids;
		}
		$dest = 0;
		$loc  = Locations::find( $where );
		if ( $loc ) {
			$dest = 'destination' === $loc->type ? (int) $loc->id : (int) $loc->parent_id;
			if ( ! in_array( $loc->type, array( 'destination', 'zone' ), true ) ) {
				global $wpdb;
				$dest = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT parent_id FROM %i WHERE id = %d', $wpdb->prefix . 'itour_locations', $dest ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- place → zone → destination.
			}
		}
		$hit = static fn( int $id ): bool => ( $dest && self::destination( $id ) === $dest )
			|| false !== mb_stripos( get_the_title( $id ) . ' ' . get_the_excerpt( $id ), $where );
		$ids = array_values( array_filter( $ids, $hit ) );
		return $count > 0 ? array_slice( $ids, 0, $count ) : $ids;
	}

	/**
	 * Up to three other trips of the same kind, same destination first.
	 *
	 * @param \WP_Post $post Product.
	 */
	public static function related( \WP_Post $post ): string {
		$ids  = array_values( array_diff( self::find( $post->post_type, 0 ), array( $post->ID ) ) );
		$dest = self::destination( $post->ID );
		usort( $ids, static fn( $a, $b ) => (int) ( self::destination( $b ) === $dest ) <=> (int) ( self::destination( $a ) === $dest ) ); // Stable: keeps menu order within each group.
		$ids = array_slice( $ids, 0, 3 );
		if ( ! $ids ) {
			return '';
		}
		return '<section class="itc-related container" aria-labelledby="itc-related-title"><h2 id="itc-related-title" class="itc-related__title">' . esc_html__( 'You may also like', 'itour-core' ) . '</h2>' . self::grid( $ids ) . '</section>';
	}

	/**
	 * "Trips" block: tabs per product type (+ the transfer search), or one type as a listing page that
	 * answers the booking widget's search (?where=).
	 *
	 * @param array<string,mixed> $a Attributes.
	 */
	public static function trips_block( array $a ): string {
		$types = self::types();
		$want  = array_filter( (array) ( $a['types'] ?? array() ) );
		if ( $want ) {
			$types = array_intersect_key( $types, array_flip( $want ) );
		}
		$count     = max( 0, (int) ( $a['count'] ?? 6 ) );
		$listing   = ! empty( $a['listing'] );
		$types     = $listing ? array_slice( $types, 0, 1, true ) : $types; // A listing page lists one kind.
		$transfers = ! $listing && ! empty( $a['transfers'] ) && isset( BookingWidget::tabs()['transfers'] );
		$panels    = array();
		foreach ( $types as $slug => list( $type, $label ) ) {
			$where = $listing ? sanitize_text_field( wp_unslash( $_GET['where'] ?? '' ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public search.
			$ids   = self::find( $type, $listing ? 0 : $count, $where );
			$body  = $ids ? self::grid( $ids ) : '<p class="itc-empty">' . esc_html(
				'' !== $where
					/* translators: %s: what the visitor searched for. */
					? sprintf( __( 'Nothing found for “%s”. Try another place, or see everything below.', 'itour-core' ), $where )
					: __( 'Trips are coming soon.', 'itour-core' )
			) . '</p>';
			if ( ! $ids && '' !== $where ) {
				$body .= self::grid( self::find( $type, 0 ) );
			}
			$panels[ $slug ] = array( $label, $body );
		}
		if ( $transfers ) {
			$panels['transfers'] = array(
				__( 'Transfers', 'itour-core' ),
				render_block(
					array(
						'blockName' => 'itour/booking-widget',
						'attrs'     => array(
							'tabs'  => array( 'transfers' ),
							'model' => 'bar',
						),
					)
				),
			);
		}
		if ( ! $panels ) {
			return '';
		}
		self::style();
		$wrap  = get_block_wrapper_attributes( array( 'class' => 'itc-trips' ) );
		$title = (string) ( $a['title'] ?? '' );
		$h     = '<div ' . $wrap . '>';
		if ( 1 === count( $panels ) ) {
			$p = reset( $panels );
			return $h . ( '' !== $title ? '<h2 class="itc-trips__title">' . esc_html( $title ) . '</h2>' : '' ) . $p[1] . '</div>';
		}
		$uid = wp_unique_id( 'itc-' );
		$h  .= '<div class="itc-trips__head">' . ( '' !== $title ? '<h2 class="itc-trips__title">' . esc_html( $title ) . '</h2>' : '' ) . '<div class="itc-tabs" role="tablist">';
		$i   = 0;
		foreach ( $panels as $slug => list( $label ) ) {
			$h .= '<button type="button" role="tab" id="' . esc_attr( "$uid-t-$slug" ) . '" aria-controls="' . esc_attr( "$uid-p-$slug" ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '"' . ( $i ? ' tabindex="-1"' : '' ) . '>' . esc_html( $label ) . '</button>';
			++$i;
		}
		$h .= '</div></div>';
		$i  = 0;
		foreach ( $panels as $slug => list( , $body ) ) {
			$h .= '<div class="itc-panel" role="tabpanel" id="' . esc_attr( "$uid-p-$slug" ) . '" aria-labelledby="' . esc_attr( "$uid-t-$slug" ) . '"' . ( $i ? ' hidden' : '' ) . '>' . $body . '</div>';
			++$i;
		}
		wp_enqueue_script( 'itour-cards', plugins_url( 'assets/cards.js', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION, array( 'strategy' => 'defer' ) );
		return $h . '</div>';
	}

	/**
	 * "Destinations" block: one photo tile per Location Tree destination that has trips, linking to the
	 * listing page searched for it. The photo is the first trip's featured image there.
	 *
	 * @param array<string,mixed> $a Attributes.
	 */
	public static function destinations_block( array $a ): string {
		$types = self::types();
		$by    = array(); // destination id => [ trip ids ].
		foreach ( $types as list( $type ) ) {
			foreach ( self::find( $type, 0 ) as $id ) {
				$d = self::destination( $id );
				if ( $d ) {
					$by[ $d ][] = $id;
				}
			}
		}
		$names = self::destination_names();
		$by    = array_intersect_key( $by, $names );
		if ( ! $by ) {
			return current_user_can( 'edit_posts' ) ? '<p class="itc-empty">' . esc_html__( 'Destinations: set a destination (Location Tree) on your tours or day trips to show tiles here.', 'itour-core' ) . '</p>' : '';
		}
		uasort( $by, static fn( $x, $y ) => count( $y ) <=> count( $x ) );
		$by      = array_slice( $by, 0, max( 1, (int) ( $a['count'] ?? 6 ) ), true );
		$listing = array();
		foreach ( $types as $t ) {
			$listing[ $t[0] ] = (string) ( $t[2] ?? '' );
		}
		self::style();
		$title = (string) ( $a['title'] ?? '' );
		// Columns that leave no holes: 3 and 5 tiles make a mosaic (first tile big), others a plain grid.
		$n    = count( $by );
		$cols = match ( $n ) {
			1       => 1,
			2, 3    => 2,
			4, 5    => 4,
			default => 3,
		};
		$h = '<div ' . get_block_wrapper_attributes( array( 'class' => 'itc-dest' ) ) . '>' . ( '' !== $title ? '<h2 class="itc-trips__title">' . esc_html( $title ) . '</h2>' : '' ) . '<ul class="itc-dest__grid' . ( 3 === $n || 5 === $n ? ' itc-dest__grid--mosaic' : '' ) . '" style="--itc-cols:' . (int) $cols . '">';
		foreach ( $by as $dest => $ids ) {
			$photo = 0;
			foreach ( $ids as $id ) {
				$photo = (int) get_post_thumbnail_id( $id );
				if ( $photo ) {
					break;
				}
			}
			$list = $listing[ get_post_type( $ids[0] ) ] ?? '';
			$url  = '' !== $list ? add_query_arg( 'where', rawurlencode( $names[ $dest ] ), $list ) : get_permalink( $ids[0] );
			/* translators: %d: number of trips. */
			$n  = sprintf( _n( '%d trip', '%d trips', count( $ids ), 'itour-core' ), count( $ids ) );
			$h .= '<li class="itc-dest__tile"><a href="' . esc_url( (string) $url ) . '">' . ( $photo ? wp_get_attachment_image( $photo, 'medium_large', false, array( 'loading' => 'lazy' ) ) : '' ) . '<span class="itc-dest__text"><strong>' . esc_html( $names[ $dest ] ) . '</strong><span>' . esc_html( $n ) . '</span></span></a></li>';
		}
		return $h . '</ul></div>';
	}
}
