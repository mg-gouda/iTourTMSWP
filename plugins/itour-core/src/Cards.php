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
		add_filter(
			'body_class',
			static function ( $classes ) {
				$post = get_queried_object();
				if ( $post instanceof \WP_Post && is_page() && has_block( 'itour/trips', $post ) && str_contains( $post->post_content, '"listing":true' ) ) {
					$classes[] = 'itour-has-listing';
				}
				return $classes;
			}
		);
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
	 * "45 USD" / "1,250.50 USD": amount then the site currency code.
	 *
	 * @param float $amount Amount.
	 */
	public static function money( float $amount ): string {
		return number_format_i18n( $amount, floor( $amount ) === $amount ? 0 : 2 ) . ' ' . itour()->currencies()->base();
	}

	/**
	 * Search criteria from the address (booking widget or listing search): [ Y-m-d date, travellers ].
	 *
	 * @return array{0:string,1:int}
	 */
	public static function criteria(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public search (GET).
		$date = sanitize_text_field( wp_unslash( $_GET['date'] ?? '' ) );
		$pax  = absint( $_GET['pax'] ?? 0 );
		// phpcs:enable
		return array( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '', min( 99, $pax ) );
	}

	/**
	 * Price line of a card: the total for the searched group when the visitor searched (travellers, and the date
	 * when given), else the lowest price. Never a per-person rate.
	 *
	 * @param int $post Post id.
	 */
	public static function price_line( int $post ): string {
		list( $date, $pax ) = self::criteria();
		if ( $pax ) {
			/**
			 * Total for a group (modules price it with their own rules), or null when they can't.
			 *
			 * @param float|null $total Total so far.
			 * @param int        $post  Post id.
			 * @param string     $date  Y-m-d (today when not searched).
			 * @param int        $pax   Travellers (adults).
			 */
			$total = apply_filters( 'itour/quote', null, $post, '' !== $date ? $date : wp_date( 'Y-m-d' ), $pax );
			if ( null !== $total && (float) $total > 0 ) {
				/* translators: %d: number of travellers. */
				return '<p class="itc__price itc__price--total"><span>' . esc_html( sprintf( _n( 'Total for %d traveller', 'Total for %d travellers', $pax, 'itour-core' ), $pax ) ) . '</span> <strong>' . esc_html( self::money( (float) $total ) ) . '</strong></p>';
			}
		}
		$from = (float) get_post_meta( $post, '_itour_price_from', true );
		return $from > 0 ? '<p class="itc__price"><span>' . esc_html__( 'Starting from', 'itour-core' ) . '</span> <strong>' . esc_html( self::money( $from ) ) . '</strong></p>' : '';
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
		$h     = '<article class="itc"><a class="itc__link" href="' . esc_url( $url ) . '">';
		$h    .= '<div class="itc__media">' . ( '' !== $img ? $img : '<span class="itc__noimg" aria-hidden="true"></span>' ) . ( '' !== $where ? '<span class="itc__where">' . esc_html( $where ) . '</span>' : '' ) . '</div>';
		$h    .= '<div class="itc__body"><h3 class="itc__title">' . esc_html( $title ) . '</h3>';
		if ( $facts ) {
			$h .= '<p class="itc__facts">' . esc_html( implode( ' · ', $facts ) ) . '</p>';
		}
		$h .= self::price_line( $post );
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
		$count   = max( 0, (int) ( $a['count'] ?? 6 ) );
		$listing = ! empty( $a['listing'] );
		$types   = $listing ? array_slice( $types, 0, 1, true ) : $types; // A listing page lists one kind.
		if ( $listing && $types ) {
			return self::listing( reset( $types ), $a );
		}
		$transfers = ! $listing && ! empty( $a['transfers'] ) && count( $types ) > 1 && isset( BookingWidget::tabs()['transfers'] ); // A one-kind section has no tabs.
		$panels    = array();
		foreach ( $types as $slug => list( $type, $label ) ) {
			$ids             = self::find( $type, $count );
			$body            = $ids ? self::grid( $ids ) : '<p class="itc-empty">' . esc_html__( 'Trips are coming soon.', 'itour-core' ) . '</p>';
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
			$p    = reset( $panels );
			$kind = reset( $types );
			$sub  = (string) ( $a['subtitle'] ?? '' );
			$more = ! empty( $a['more'] ) && '' !== (string) ( $kind[2] ?? '' )
				/* translators: %s: kind of trips, e.g. "Tour packages". */
				? '<p class="itc-more"><a class="wp-element-button" href="' . esc_url( (string) $kind[2] ) . '">' . esc_html( sprintf( __( 'See all %s', 'itour-core' ), mb_strtolower( (string) $kind[1] ) ) ) . '</a></p>'
				: '';
			$head = '' !== $title ? '<header class="itc-trips__heading"><h2 class="itc-trips__title">' . esc_html( $title ) . '</h2>' . ( '' !== $sub ? '<p class="itc-trips__sub">' . esc_html( $sub ) . '</p>' : '' ) . '</header>' : '';
			$body = 'row' === ( $a['style'] ?? '' ) ? str_replace( 'class="itc-grid"', 'class="itc-grid itc-grid--row"', $p[1] ) : $p[1];
			return $h . $head . $body . $more . '</div>';
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
	 * Listing page (search results): banner, filters sidebar (search, price, destination, category),
	 * results count, sort and grid / list view. Every filter is a GET parameter, so results can be shared.
	 *
	 * @param array{0:string,1:string,2?:string} $kind Post type, label, listing URL.
	 * @param array<string,mixed>                $a    Attributes.
	 */
	private static function listing( array $kind, array $a ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public search form (GET).
		$type  = $kind[0];
		$where = sanitize_text_field( wp_unslash( $_GET['where'] ?? '' ) );
		$dests = array_map( 'absint', (array) wp_unslash( $_GET['dest'] ?? array() ) );
		$cats  = array_map( 'absint', (array) wp_unslash( $_GET['cat'] ?? array() ) );
		$max   = absint( $_GET['max'] ?? 0 );
		$sort  = sanitize_key( wp_unslash( $_GET['sort'] ?? '' ) );
		$view  = 'list' === sanitize_key( wp_unslash( $_GET['view'] ?? '' ) ) ? 'list' : 'grid';
		// phpcs:enable
		$all   = self::find( $type, 0 );
		$price = static fn( int $id ): float => (float) get_post_meta( $id, '_itour_price_from', true );
		$top   = $all ? (int) ceil( max( array_map( $price, $all ) ) ) : 0;
		$ids   = self::find( $type, 0, $where );
		$ids   = array_values(
			array_filter(
				$ids,
				static fn( int $id ): bool => ( ! $dests || in_array( self::destination( $id ), $dests, true ) )
					&& ( ! $cats || has_term( $cats, $type . '_cat', $id ) )
					&& ( ! $max || $price( $id ) <= $max )
			)
		);
		if ( 'price_asc' === $sort || 'price_desc' === $sort ) {
			usort( $ids, static fn( $x, $y ) => 'price_asc' === $sort ? $price( $x ) <=> $price( $y ) : $price( $y ) <=> $price( $x ) );
		} elseif ( 'name' === $sort ) {
			usort( $ids, static fn( $x, $y ) => strcasecmp( get_the_title( $x ), get_the_title( $y ) ) );
		}

		// Filter options from what the trips really have.
		$names    = self::destination_names();
		$dest_opt = array();
		foreach ( $all as $id ) {
			$d = self::destination( $id );
			if ( $d && isset( $names[ $d ] ) ) {
				$dest_opt[ $d ] = $names[ $d ];
			}
		}
		asort( $dest_opt );
		$terms = get_terms(
			array(
				'taxonomy'   => $type . '_cat',
				'hide_empty' => true,
			)
		);
		$terms = is_wp_error( $terms ) ? array() : $terms;

		self::style();
		wp_enqueue_script( 'itour-cards', plugins_url( 'assets/cards.js', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION, array( 'strategy' => 'defer' ) );
		$page  = get_queried_object();
		$title = (string) ( $a['title'] ?? '' );
		$title = '' !== $title ? $title : ( $page instanceof \WP_Post ? get_the_title( $page ) : (string) $kind[1] );
		$sub   = (string) ( $a['subtitle'] ?? '' );
		$sub   = '' !== $sub ? $sub : ( $page instanceof \WP_Post && has_excerpt( $page ) ? get_the_excerpt( $page ) : '' );
		$photo = 0;
		foreach ( $all as $id ) {
			$photo = (int) get_post_thumbnail_id( $id );
			if ( $photo ) {
				break;
			}
		}
		$photo = $page instanceof \WP_Post && has_post_thumbnail( $page ) ? (int) get_post_thumbnail_id( $page ) : $photo;

		$h  = '<div ' . get_block_wrapper_attributes( array( 'class' => 'itc-listing' ) ) . '>';
		$h .= '<header class="itc-banner">' . ( $photo ? wp_get_attachment_image(
			$photo,
			'large',
			false,
			array(
				'class'   => 'itc-banner__img',
				'loading' => 'eager',
			)
		) : '' ) . '<div class="itc-banner__body"><span class="itc-banner__chip">' . esc_html( (string) $kind[1] ) . '</span><h1 class="itc-banner__title">' . esc_html( $title ) . '</h1>' . ( '' !== $sub ? '<p class="itc-banner__sub">' . esc_html( $sub ) . '</p>' : '' ) . '</div></header>';

		$h .= '<div class="itc-listing__grid"><aside class="itc-filters"><form method="get" data-itc-filters>';
		$h .= '<section class="itc-filters__box"><h2 class="itc-filters__title">' . esc_html__( 'Search', 'itour-core' ) . '</h2><label class="itc-field"><span>' . esc_html__( 'Destination', 'itour-core' ) . '</span><input type="search" name="where" value="' . esc_attr( $where ) . '" placeholder="' . esc_attr__( 'Where are you going?', 'itour-core' ) . '"></label>'
			. '<label class="itc-field"><span>' . esc_html__( 'Date', 'itour-core' ) . '</span><input type="date" name="date" min="' . esc_attr( wp_date( 'Y-m-d' ) ) . '" value="' . esc_attr( self::criteria()[0] ) . '"></label>'
			. '<label class="itc-field"><span>' . esc_html__( 'Travellers', 'itour-core' ) . '</span><input type="number" name="pax" min="1" max="99" inputmode="numeric" value="' . esc_attr( (string) ( self::criteria()[1] ? self::criteria()[1] : '' ) ) . '" placeholder="2"></label><button type="submit" class="wp-element-button itc-filters__go">' . esc_html__( 'Search', 'itour-core' ) . '</button></section>';
		$h .= '<details class="itc-filters__box itc-filters__more" open data-itc-more><summary class="itc-filters__title">' . esc_html__( 'Filter by', 'itour-core' ) . '</summary>';
		if ( $top > 0 ) {
			$cur = $max ? min( $max, $top ) : $top;
			$h  .= '<fieldset class="itc-filter"><legend>' . esc_html__( 'Price up to', 'itour-core' ) . '</legend><input type="range" name="max" min="0" max="' . (int) $top . '" step="5" value="' . (int) $cur . '" data-itc-range aria-describedby="itc-max-out"><output id="itc-max-out" data-itc-out>' . esc_html( itour()->currencies()->format( (float) $cur ) ) . '</output></fieldset>';
		}
		$box = static function ( string $legend, string $name, array $opts, array $on ): string {
			if ( ! $opts ) {
				return '';
			}
			$h = '<fieldset class="itc-filter"><legend>' . esc_html( $legend ) . '</legend>';
			foreach ( $opts as $v => $label ) {
				$h .= '<label class="itc-check"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . (int) $v . '"' . checked( in_array( (int) $v, $on, true ), true, false ) . '> ' . esc_html( (string) $label ) . '</label>';
			}
			return $h . '</fieldset>';
		};
		$h  .= $box( __( 'Destination', 'itour-core' ), 'dest', $dest_opt, $dests );
		$h  .= $box( __( 'Category', 'itour-core' ), 'cat', wp_list_pluck( $terms, 'name', 'term_id' ), $cats );
		$h  .= '<input type="hidden" name="sort" value="' . esc_attr( $sort ) . '"><input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
		$h  .= '<noscript><button type="submit" class="wp-element-button">' . esc_html__( 'Apply', 'itour-core' ) . '</button></noscript>';
		if ( $where || $dests || $cats || $max ) {
			$h .= '<a class="itc-filters__reset" href="' . esc_url( (string) strtok( (string) get_permalink(), '?' ) ) . '">' . esc_html__( 'Clear all filters', 'itour-core' ) . '</a>';
		}
		$h .= '</details></form></aside>';

		/* translators: %d: number of trips found. */
		$found = sprintf( _n( '%d trip found', '%d trips found', count( $ids ), 'itour-core' ), count( $ids ) );
		$sorts = array(
			''           => __( 'Recommended', 'itour-core' ),
			'price_asc'  => __( 'Price: low to high', 'itour-core' ),
			'price_desc' => __( 'Price: high to low', 'itour-core' ),
			'name'       => __( 'Name', 'itour-core' ),
		);
		$h    .= '<div class="itc-results"><div class="itc-results__bar"><p class="itc-results__count" role="status">' . esc_html( $found ) . '</p><label class="itc-sort"><span>' . esc_html__( 'Sort', 'itour-core' ) . '</span><select data-itc-sort>';
		foreach ( $sorts as $k => $label ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $sort, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$h    .= '</select></label><div class="itc-view" role="group" aria-label="' . esc_attr__( 'View', 'itour-core' ) . '">';
		$icons = array(
			'grid' => array( __( 'Grid', 'itour-core' ), '<rect x="4" y="4" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1"/>' ),
			'list' => array( __( 'List', 'itour-core' ), '<rect x="4" y="5" width="5" height="5" rx="1"/><rect x="4" y="14" width="5" height="5" rx="1"/><path d="M12 6.5h8M12 9h5M12 15.5h8M12 18h5"/>' ),
		);
		foreach ( $icons as $k => list( $label, $path ) ) {
			$h .= '<a href="' . esc_url( add_query_arg( 'view', $k ) ) . '" aria-label="' . esc_attr( $label ) . '"' . ( $view === $k ? ' aria-current="true"' : '' ) . '><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg></a>';
		}
		$h .= '</div></div>';
		if ( $ids ) {
			$h .= str_replace( 'class="itc-grid"', 'class="itc-grid' . ( 'list' === $view ? ' itc-grid--row' : '' ) . '"', self::grid( $ids ) );
		} else {
			$h .= '<div class="itc-none"><p>' . esc_html__( 'No trips match these filters.', 'itour-core' ) . '</p><a class="wp-element-button" href="' . esc_url( (string) strtok( (string) get_permalink(), '?' ) ) . '">' . esc_html__( 'Show all trips', 'itour-core' ) . '</a></div>';
		}
		return $h . '</div></div></div>';
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
