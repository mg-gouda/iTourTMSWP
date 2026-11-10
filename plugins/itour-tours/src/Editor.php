<?php
/**
 * "Tour details" panel on the tour package edit screen: vertical tabs, every field saves on its own.
 * Tabs: Basics, Dates, Itinerary, Prices, Extras, Supplements, then the shared Included / FAQ / Photos.
 * Categories, languages and suitability stay in the editor sidebar.
 *
 * @package ITour\Tours
 */

namespace ITour\Tours;

use ITour\Core\Admin\Panel;
use ITour\Core\Admin\Rows;
use ITour\Core\Locations;
use ITour\Core\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Panel sections: render + save per tab.
 */
final class Editor {

	/**
	 * Register the panel.
	 */
	public function hooks(): void {
		add_action(
			'init',
			static function (): void {
				Panel::register( Tour::TYPE, __( 'Tour details', 'itour-tours' ), self::sections() + Products::info_sections() );
			},
			20
		);
	}

	/**
	 * Merge values into the tour's settings.
	 *
	 * @param int                 $post   Post id.
	 * @param array<string,mixed> $values Values.
	 */
	private static function put( int $post, array $values ): void {
		update_post_meta( $post, Tour::META, array_merge( Tour::config( $post ), $values ) );
	}

	/**
	 * Column sets for the repeating tables.
	 *
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	private static function cols(): array {
		$money = static fn( string $label ): array => array(
			'label' => $label,
			'type'  => 'decimal',
			'width' => '6.5em',
		);
		$date  = static fn( string $label ): array => array(
			'label' => $label,
			'type'  => 'date',
			'width' => '9.5em',
		);
		return array(
			'departures'  => array(
				'date'  => $date( __( 'Departure date', 'itour-tours' ) ),
				'seats' => array(
					'label' => __( 'Seats (0 = no limit)', 'itour-tours' ),
					'type'  => 'number',
					'width' => '9em',
				),
			),
			'itinerary'   => array(
				'title'     => array(
					'label' => __( 'Day title', 'itour-tours' ),
					'type'  => 'text',
					'width' => '18%',
				),
				'place'     => array(
					'label'   => __( 'On the map', 'itour-tours' ),
					'type'    => 'select',
					'options' => array( Locations::class, 'all_options' ),
					'width'   => '16%',
				),
				'places'    => array(
					'label' => __( 'Places visited', 'itour-tours' ),
					'type'  => 'text',
					'width' => '16%',
				),
				'meals'     => array(
					'label'   => __( 'Meals', 'itour-tours' ),
					'type'    => 'select',
					'options' => Tour::meals(),
					'width'   => '9.5em',
				),
				'overnight' => array(
					'label' => __( 'Overnight', 'itour-tours' ),
					'type'  => 'text',
					'width' => '12%',
				),
				'text'      => array(
					'label' => __( 'What happens', 'itour-tours' ),
					'type'  => 'textarea',
				),
			),
			'rates'       => array(
				'hotel_class' => array(
					'label'   => __( 'Hotels', 'itour-tours' ),
					'type'    => 'select',
					'options' => Tour::classes(),
					'width'   => '9em',
				),
				'season_from' => $date( __( 'Season from', 'itour-tours' ) ),
				'season_to'   => $date( __( 'Season to', 'itour-tours' ) ),
				'pax_min'     => array(
					'label'   => __( 'From travellers', 'itour-tours' ),
					'type'    => 'number',
					'default' => 1,
					'width'   => '5.5em',
				),
				'pax_max'     => array(
					'label'      => __( 'To travellers', 'itour-tours' ),
					'type'       => 'number',
					'width'      => '5.5em',
					'zero_empty' => true,
				),
				'adult'       => $money( __( 'Adult (sharing)', 'itour-tours' ) ),
				'child'       => $money( __( 'Child', 'itour-tours' ) ),
				'infant'      => $money( __( 'Infant', 'itour-tours' ) ),
				'single_supp' => $money( __( 'Single room +', 'itour-tours' ) ),
				'triple_red'  => $money( __( 'Triple room −', 'itour-tours' ) ),
			),
			'extras'      => array(
				'name'  => array(
					'label' => __( 'Optional extra', 'itour-tours' ),
					'type'  => 'text',
				),
				'price' => $money( __( 'Price', 'itour-tours' ) ),
				'per'   => array(
					'label'   => __( 'Charged', 'itour-tours' ),
					'type'    => 'select',
					'options' => array(
						'person'  => __( 'per person', 'itour-tours' ),
						'booking' => __( 'per booking', 'itour-tours' ),
					),
					'width'   => '9em',
				),
			),
			'supplements' => array(
				'name'   => array(
					'label' => __( 'Supplement', 'itour-tours' ),
					'type'  => 'text',
				),
				'from'   => $date( __( 'Date', 'itour-tours' ) ),
				'to'     => $date( __( 'Until (optional)', 'itour-tours' ) ),
				'adult'  => $money( __( 'Adult', 'itour-tours' ) ),
				'child'  => $money( __( 'Child', 'itour-tours' ) ),
				'infant' => $money( __( 'Infant', 'itour-tours' ) ),
			),
		);
	}

	/**
	 * A full-width repeating table with a help line, posted as itp[tab][field].
	 *
	 * @param string                         $tab   Panel section id.
	 * @param string                         $cols  Column set key.
	 * @param string                         $field Field key inside the section.
	 * @param string                         $help  Help text.
	 * @param array<int,array<string,mixed>> $rows  Saved rows.
	 * @param string                         $add   Add-row label.
	 */
	private static function table( string $tab, string $cols, string $field, string $help, array $rows, string $add ): void {
		echo '<div class="itp-row itp-row--wide"><div class="itp-row-text"><p class="itp-help">' . esc_html( $help ) . '</p></div><div class="itp-table-wrap">';
		Rows::render( Panel::name( $tab, $field ), self::cols()[ $cols ], $rows, $add );
		echo '</div></div>';
	}

	/**
	 * Weekday options, Monday first.
	 *
	 * @return array<int,string>
	 */
	private static function weekdays(): array {
		$out = array();
		foreach ( range( 1, 7 ) as $d ) {
			$out[ $d ] = wp_date( 'D', (int) strtotime( "monday this week +{$d} days -1 day" ) );
		}
		return $out;
	}

	/**
	 * The tour's own tabs.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function sections(): array {
		$cols = self::cols();
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Panel helpers escape.
		return array(
			'basics'      => array(
				'label'  => __( 'Basics', 'itour-tours' ),
				'badge'  => static fn( int $post ): string => (string) Tour::config( $post )['days'] . 'd',
				'render' => static function ( \WP_Post $post ): void {
					global $wpdb;
					$c    = Tour::config( $post->ID );
					$dest = array( 0 => __( '— Choose —', 'itour-tours' ) ) + array_map( static fn( $d ) => $d->name, (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM %i WHERE type = 'destination' AND active = 1 ORDER BY name", $wpdb->prefix . 'itour_locations' ), OBJECT_K ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					echo Panel::row( __( 'Main destination', 'itour-tours' ), Panel::select( 'basics', 'destination', $dest, $c['destination'] ), __( 'From Location Tree. Searches by destination use it.', 'itour-tours' ), 'itp-basics-destination' );
					echo Panel::row(
						__( 'Length', 'itour-tours' ),
						Panel::input(
							'basics',
							'days',
							$c['days'],
							'number',
							array(
								'min' => '1',
								'max' => '60',
							)
						) . '<span class="itp-unit">' . esc_html__( 'days', 'itour-tours' ) . '</span>',
						__( 'Nights are worked out for you: days minus one.', 'itour-tours' ),
						'itp-basics-days'
					);
					echo Panel::row(
						__( 'Booking', 'itour-tours' ),
						Panel::choices(
							'basics',
							'booking',
							array(
								'instant' => __( 'Instant, pay online', 'itour-tours' ),
								'enquiry' => __( 'Enquiry only', 'itour-tours' ),
							),
							$c['booking'],
							false
						),
						__( 'Enquiry only: the customer sends a request and you reply with a quote.', 'itour-tours' )
					);
					echo Panel::row(
						__( 'Deposit', 'itour-tours' ),
						Panel::input(
							'basics',
							'deposit',
							$c['deposit'],
							'number',
							array(
								'min' => '0',
								'max' => '100',
							)
						) . '<span class="itp-unit">%</span>',
						__( '0 = customers pay the full price at checkout. Otherwise they may pay this share now and the rest before travel.', 'itour-tours' ),
						'itp-basics-deposit'
					);
				},
				'save'   => static function ( int $post, array $in ): void {
					self::put(
						$post,
						array(
							'destination' => absint( $in['destination'] ?? 0 ),
							'days'        => max( 1, min( 60, absint( $in['days'] ?? 1 ) ) ),
							'booking'     => 'enquiry' === ( $in['booking'] ?? '' ) ? 'enquiry' : 'instant',
							'deposit'     => min( 100, absint( $in['deposit'] ?? 0 ) ),
						)
					);
				},
			),
			'dates'       => array(
				'label'  => __( 'Dates', 'itour-tours' ),
				'badge'  => static function ( int $post ): string {
					$c = Tour::config( $post );
					return 'fixed' === $c['dates'] ? (string) count( (array) $c['departures'] ) : ( 7 === count( (array) $c['start_days'] ) ? __( 'any', 'itour-tours' ) : (string) count( (array) $c['start_days'] ) . '/7' );
				},
				'render' => static function ( \WP_Post $post ): void {
					$c = Tour::config( $post->ID );
					echo Panel::row(
						__( 'How it starts', 'itour-tours' ),
						Panel::choices(
							'dates',
							'dates',
							array(
								'weekdays' => __( 'On chosen weekdays', 'itour-tours' ),
								'fixed'    => __( 'Fixed departures', 'itour-tours' ),
							),
							$c['dates'],
							false
						),
						__( 'Weekdays: the customer picks any date that falls on one of them. Fixed: only the departures listed below, each with its own seats.', 'itour-tours' )
					);
					echo Panel::row( __( 'Start days', 'itour-tours' ), Panel::choices( 'dates', 'start_days', self::weekdays(), $c['start_days'] ), __( 'Tick all seven to allow any day.', 'itour-tours' ) );
					echo Panel::row( __( 'Booking closes', 'itour-tours' ), Panel::input( 'dates', 'cutoff', $c['cutoff'], 'number', array( 'min' => '0' ) ) . '<span class="itp-unit">' . esc_html__( 'days before the start', 'itour-tours' ) . '</span>', '', 'itp-dates-cutoff' );
					self::table( 'dates', 'departures', 'departures', __( 'Fixed departures: one row per date. Used when "Fixed departures" is chosen above.', 'itour-tours' ), (array) $c['departures'], __( 'Add departure', 'itour-tours' ) );
				},
				'save'   => static function ( int $post, array $in ): void {
					$deps = Rows::clean( self::cols()['departures'], $in['departures'] ?? array(), array( 'date' ) );
					usort( $deps, static fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );
					self::put(
						$post,
						array(
							'dates'      => 'fixed' === ( $in['dates'] ?? '' ) ? 'fixed' : 'weekdays',
							'start_days' => array_values( array_intersect( range( 1, 7 ), array_map( 'intval', (array) ( $in['start_days'] ?? array() ) ) ) ),
							'cutoff'     => absint( $in['cutoff'] ?? 7 ),
							'departures' => $deps,
						)
					);
				},
			),
			'itinerary'   => array(
				'label'  => __( 'Itinerary', 'itour-tours' ),
				'badge'  => static fn( int $post ): string => Tour::config( $post )['itinerary'] ? (string) count( Tour::config( $post )['itinerary'] ) : '',
				'render' => static function ( \WP_Post $post ): void {
					self::table( 'itinerary', 'itinerary', 'rows', __( 'One row per day, in order. "On the map" picks a place from Location Tree (it needs latitude and longitude there) to draw the route map.', 'itour-tours' ), (array) Tour::config( $post->ID )['itinerary'], __( 'Add day', 'itour-tours' ) );
				},
				'save'   => static function ( int $post, array $in ): void {
					self::put( $post, array( 'itinerary' => Rows::clean( self::cols()['itinerary'], $in['rows'] ?? array(), array( 'title' ) ) ) );
				},
			),
			'prices'      => array(
				'label'  => __( 'Prices', 'itour-tours' ),
				'badge'  => static fn( int $post ): string => Tour::rates( $post ) ? (string) count( Tour::rates( $post ) ) : '',
				'render' => static function ( \WP_Post $post ): void {
					self::table( 'prices', 'rates', 'rows', __( 'Price per person sharing a double or twin room. One row per hotel class, season and group size: the more travellers, the lower the price. Empty "To travellers" = no upper limit. Season rows override all-year rows. Single room + is added per traveller in a single room; Triple room − is taken off per traveller in a triple room.', 'itour-tours' ), Tour::rates( $post->ID ), __( 'Add price row', 'itour-tours' ) );
				},
				'save'   => static function ( int $post, array $in ): void {
					global $wpdb;
					$table = $wpdb->prefix . 'itour_tour_rates';
					$wpdb->delete( $table, array( 'post_id' => $post ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					foreach ( Rows::clean( self::cols()['rates'], $in['rows'] ?? array(), array( 'adult' ) ) as $row ) {
						foreach ( $row as $k => $v ) {
							$row[ $k ] = '' !== $v || 'hotel_class' === $k ? $v : ( str_starts_with( $k, 'season_' ) ? null : 0 ); // Empty season = all year; empty number = 0.
						}
						$wpdb->insert( $table, array( 'post_id' => $post ) + $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					}
					update_post_meta( $post, '_itour_price_from', Tour::from_price( $post ) ); // For sorting lists by price.
				},
			),
			'extras'      => array(
				'label'  => __( 'Extras', 'itour-tours' ),
				'badge'  => static fn( int $post ): string => Tour::config( $post )['extras'] ? (string) count( Tour::config( $post )['extras'] ) : '',
				'render' => static function ( \WP_Post $post ): void {
					self::table( 'extras', 'extras', 'rows', __( 'Paid extras the customer can tick, e.g. a hot-air balloon ride or a sound and light show.', 'itour-tours' ), (array) Tour::config( $post->ID )['extras'], __( 'Add extra', 'itour-tours' ) );
				},
				'save'   => static function ( int $post, array $in ): void {
					self::put( $post, array( 'extras' => Rows::clean( self::cols()['extras'], $in['rows'] ?? array(), array( 'name', 'price' ) ) ) );
				},
			),
			'supplements' => array(
				'label'  => __( 'Supplements', 'itour-tours' ),
				'badge'  => static fn( int $post ): string => Tour::config( $post )['supplements'] ? (string) count( Tour::config( $post )['supplements'] ) : '',
				'render' => static function ( \WP_Post $post ): void {
					self::table( 'supplements', 'supplements', 'rows', __( 'Charged automatically per person when the tour dates include these dates, e.g. a Christmas Gala Meal.', 'itour-tours' ), (array) Tour::config( $post->ID )['supplements'], __( 'Add supplement', 'itour-tours' ) );
				},
				'save'   => static function ( int $post, array $in ): void {
					self::put( $post, array( 'supplements' => Rows::clean( self::cols()['supplements'], $in['rows'] ?? array(), array( 'name', 'from' ) ) ) );
				},
			),
		);
		// phpcs:enable
	}
}
