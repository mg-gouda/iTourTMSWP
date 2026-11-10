<?php
/**
 * "Trip details" panel on the excursion edit screen: vertical tabs, every field saves on its own.
 * Tabs: Basics, Schedule, Pick-up, Prices, Add-ons, Supplements, then the shared Included / FAQ / Photos.
 *
 * @package ITour\Excursions
 */

namespace ITour\Excursions;

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
				Panel::register( Excursion::TYPE, __( 'Trip details', 'itour-excursions' ), self::sections() + \ITour\Core\Policies::section() + Products::info_sections() );
			},
			20
		);
	}

	/**
	 * Merge values into the excursion's settings.
	 *
	 * @param int                 $post   Post id.
	 * @param array<string,mixed> $values Values.
	 */
	private static function put( int $post, array $values ): void {
		update_post_meta( $post, Excursion::META, array_merge( Excursion::config( $post ), $values ) );
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
			'width' => '7em',
		);
		$date  = static fn( string $label ): array => array(
			'label' => $label,
			'type'  => 'date',
			'width' => '10em',
		);
		return array(
			'rates'       => array(
				'variant'     => array(
					'label'   => __( 'Type', 'itour-excursions' ),
					'type'    => 'select',
					'options' => Excursion::variants(),
					'width'   => '10em',
				),
				'season_from' => $date( __( 'Season from', 'itour-excursions' ) ),
				'season_to'   => $date( __( 'Season to', 'itour-excursions' ) ),
				'pax_min'     => array(
					'label'   => __( 'From travellers', 'itour-excursions' ),
					'type'    => 'number',
					'default' => 1,
					'width'   => '6em',
				),
				'pax_max'     => array(
					'label'      => __( 'To travellers', 'itour-excursions' ),
					'type'       => 'number',
					'width'      => '6em',
					'zero_empty' => true,
				),
				'adult'       => $money( __( 'Adult', 'itour-excursions' ) ),
				'child'       => $money( __( 'Child', 'itour-excursions' ) ),
				'infant'      => $money( __( 'Infant', 'itour-excursions' ) ),
			),
			'times'       => array(
				'time' => array(
					'label' => __( 'Start time', 'itour-excursions' ),
					'type'  => 'time',
				),
			),
			'blocked'     => array(
				'from' => $date( __( 'Closed from', 'itour-excursions' ) ),
				'to'   => $date( __( 'Closed to', 'itour-excursions' ) ),
			),
			'pickups'     => array(
				'zone_id'     => array(
					'label'   => __( 'Zone', 'itour-excursions' ),
					'type'    => 'select',
					'options' => array( Locations::class, 'zone_options' ),
				),
				'pickup_time' => array(
					'label' => __( 'Pick-up time', 'itour-excursions' ),
					'type'  => 'time',
					'width' => '9em',
				),
				'extra'       => $money( __( 'Extra per person', 'itour-excursions' ) ),
			),
			'addons'      => array(
				'name'  => array(
					'label' => __( 'Add-on', 'itour-excursions' ),
					'type'  => 'text',
				),
				'price' => $money( __( 'Price', 'itour-excursions' ) ),
				'per'   => array(
					'label'   => __( 'Charged', 'itour-excursions' ),
					'type'    => 'select',
					'options' => array(
						'person'  => __( 'per person', 'itour-excursions' ),
						'booking' => __( 'per booking', 'itour-excursions' ),
					),
					'width'   => '9em',
				),
			),
			'supplements' => array(
				'name'   => array(
					'label' => __( 'Supplement', 'itour-excursions' ),
					'type'  => 'text',
				),
				'from'   => $date( __( 'Date', 'itour-excursions' ) ),
				'to'     => $date( __( 'Until (optional)', 'itour-excursions' ) ),
				'adult'  => $money( __( 'Adult', 'itour-excursions' ) ),
				'child'  => $money( __( 'Child', 'itour-excursions' ) ),
				'infant' => $money( __( 'Infant', 'itour-excursions' ) ),
			),
		);
	}

	/**
	 * A repeating table in a row, posted as itp[tab][field].
	 *
	 * @param string                         $tab   Panel section id.
	 * @param string                         $cols  Column set key.
	 * @param string                         $field Field key inside the section.
	 * @param string                         $label Row label ('' = full width with help only).
	 * @param string                         $help  Help text.
	 * @param array<int,array<string,mixed>> $rows  Saved rows.
	 * @param string                         $add   Add-row label.
	 */
	private static function table( string $tab, string $cols, string $field, string $label, string $help, array $rows, string $add ): void {
		ob_start();
		Rows::render( Panel::name( $tab, $field ), self::cols()[ $cols ], $rows, $add );
		$t = '<div class="itp-table-wrap">' . ob_get_clean() . '</div>';
		if ( '' !== $label ) {
			echo Panel::row( $label, $t, $help ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			return;
		}
		echo '<div class="itp-row itp-row--wide"><div class="itp-row-text"><p class="itp-help">' . esc_html( $help ) . '</p></div>' . $t . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by Rows.
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
	 * Count badge for a list ('' when empty).
	 *
	 * @param array<mixed> $items List.
	 */
	private static function count( array $items ): string {
		return $items ? (string) count( $items ) : '';
	}

	/**
	 * The excursion's own tabs.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function sections(): array {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Panel helpers escape.
		return array(
			'basics'      => array(
				'label'  => __( 'Basics', 'itour-excursions' ),
				'render' => static function ( \WP_Post $post ): void {
					global $wpdb;
					$c    = Excursion::config( $post->ID );
					$dest = array( 0 => __( '— Choose —', 'itour-excursions' ) ) + array_map( static fn( $d ) => $d->name, (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM %i WHERE type = 'destination' AND active = 1 ORDER BY name", $wpdb->prefix . 'itour_locations' ), OBJECT_K ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					echo Panel::row( __( 'Destination', 'itour-excursions' ), Panel::select( 'basics', 'destination', $dest, $c['destination'] ), __( 'From Location Tree. Searches by destination use it.', 'itour-excursions' ), 'itp-basics-destination' );
					echo Panel::row( __( 'Duration', 'itour-excursions' ), Panel::input( 'basics', 'duration', $c['duration'], 'text', array( 'placeholder' => __( 'e.g. 12 hours, or Half day', 'itour-excursions' ) ) ), __( 'Shown as written on the page.', 'itour-excursions' ), 'itp-basics-duration' );
					echo Panel::row(
						__( 'Deposit', 'itour-excursions' ),
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
						__( '0 = customers pay the full price at checkout. Otherwise they may pay this share now and the rest on the day.', 'itour-excursions' ),
						'itp-basics-deposit'
					);
				},
				'save'   => static function ( int $post, array $in ): void {
					self::put(
						$post,
						array(
							'destination' => absint( $in['destination'] ?? 0 ),
							'duration'    => sanitize_text_field( (string) ( $in['duration'] ?? '' ) ),
							'deposit'     => min( 100, absint( $in['deposit'] ?? 0 ) ),
						)
					);
				},
			),
			'schedule'    => array(
				'label'  => __( 'Schedule', 'itour-excursions' ),
				'badge'  => static function ( int $post ): string {
					$days = (array) Excursion::config( $post )['days'];
					return 7 === count( $days ) ? __( 'daily', 'itour-excursions' ) : count( $days ) . '/7';
				},
				'render' => static function ( \WP_Post $post ): void {
					$c = Excursion::config( $post->ID );
					echo Panel::row( __( 'Runs on', 'itour-excursions' ), Panel::choices( 'schedule', 'days', self::weekdays(), $c['days'] ), __( 'Customers can only book these weekdays.', 'itour-excursions' ) );
					self::table( 'schedule', 'times', 'times', __( 'Start times', 'itour-excursions' ), __( 'One row per start time. With none, the trip shows without a time.', 'itour-excursions' ), array_map( static fn( $t ) => array( 'time' => $t ), (array) $c['times'] ), __( 'Add start time', 'itour-excursions' ) );
					echo Panel::row( __( 'Booking closes', 'itour-excursions' ), Panel::input( 'schedule', 'cutoff', $c['cutoff'], 'number', array( 'min' => '0' ) ) . '<span class="itp-unit">' . esc_html__( 'hours before the start', 'itour-excursions' ) . '</span>', '', 'itp-schedule-cutoff' );
					echo Panel::row( __( 'Seats per day', 'itour-excursions' ), Panel::input( 'schedule', 'seats', $c['seats'], 'number', array( 'min' => '0' ) ), __( '0 = no limit. Counts every booked traveller, infants included.', 'itour-excursions' ), 'itp-schedule-seats' );
					self::table( 'schedule', 'blocked', 'blocked', __( 'Closed dates', 'itour-excursions' ), __( 'Holidays, weather, private charters. Leave "Closed to" empty for a single day.', 'itour-excursions' ), (array) $c['blocked'], __( 'Add closed dates', 'itour-excursions' ) );
				},
				'save'   => static function ( int $post, array $in ): void {
					$times = array_values( array_unique( array_column( Rows::clean( self::cols()['times'], $in['times'] ?? array(), array( 'time' ) ), 'time' ) ) );
					sort( $times );
					self::put(
						$post,
						array(
							'days'    => array_values( array_intersect( range( 1, 7 ), array_map( 'intval', (array) ( $in['days'] ?? array() ) ) ) ),
							'times'   => $times,
							'cutoff'  => absint( $in['cutoff'] ?? 24 ),
							'seats'   => absint( $in['seats'] ?? 0 ),
							'blocked' => Rows::clean( self::cols()['blocked'], $in['blocked'] ?? array(), array( 'from' ) ),
						)
					);
				},
			),
			'pickup'      => array(
				'label'  => __( 'Pick-up', 'itour-excursions' ),
				'badge'  => static fn( int $post ): string => self::count( Excursion::pickups( $post ) ),
				'render' => static function ( \WP_Post $post ): void {
					$c       = Excursion::config( $post->ID );
					$pickups = array_map(
						static function ( $p ) {
							$p['pickup_time'] = substr( (string) $p['pickup_time'], 0, 5 );
							return $p;
						},
						Excursion::pickups( $post->ID )
					);
					$modes   = array(
						'hotel'   => __( 'Hotels in the zones below', 'itour-excursions' ),
						'typed'   => __( 'Customer types the hotel', 'itour-excursions' ),
						'meeting' => __( 'Meeting point', 'itour-excursions' ),
					);
					echo Panel::row( __( 'Customers can be picked up', 'itour-excursions' ), Panel::choices( 'pickup', 'modes', $modes, $c['pickup_modes'] ), __( 'Tick every way you offer. The customer chooses one when booking.', 'itour-excursions' ) );
					self::table( 'pickup', 'pickups', 'zones', __( 'Pick-up zones', 'itour-excursions' ), __( 'Hotels in these Location Tree zones appear in the booking form, with the zone’s pick-up time and extra charge.', 'itour-excursions' ), $pickups, __( 'Add zone', 'itour-excursions' ) );
					echo Panel::row( __( 'Meeting point', 'itour-excursions' ), Panel::input( 'pickup', 'meeting_point', $c['meeting_point'], 'text', array( 'placeholder' => __( 'e.g. Marina Boulevard, in front of the pharmacy, 07:30', 'itour-excursions' ) ) ), __( 'Shown to customers who choose the meeting point.', 'itour-excursions' ), 'itp-pickup-meeting_point' );
				},
				'save'   => static function ( int $post, array $in ): void {
					global $wpdb;
					self::put(
						$post,
						array(
							'pickup_modes'  => array_values( array_intersect( array( 'hotel', 'typed', 'meeting' ), (array) ( $in['modes'] ?? array() ) ) ),
							'meeting_point' => sanitize_text_field( (string) ( $in['meeting_point'] ?? '' ) ),
						)
					);
					$table = $wpdb->prefix . 'itour_excursion_pickups';
					$wpdb->delete( $table, array( 'post_id' => $post ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					foreach ( Rows::clean( self::cols()['pickups'], $in['zones'] ?? array(), array( 'zone_id', 'pickup_time' ) ) as $row ) {
						$row['extra'] = '' !== $row['extra'] ? $row['extra'] : 0;
						$wpdb->insert( $table, array( 'post_id' => $post ) + $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					}
				},
			),
			'prices'      => array(
				'label'  => __( 'Prices', 'itour-excursions' ),
				'badge'  => static fn( int $post ): string => self::count( Excursion::rates( $post ) ),
				'render' => static function ( \WP_Post $post ): void {
					self::table( 'prices', 'rates', 'rows', '', __( 'Price per person. One row per type, season and group size: the more travellers in the booking, the lower the price. Empty "To travellers" = no upper limit. Season rows override all-year rows on those dates. Infants count towards the group size.', 'itour-excursions' ), Excursion::rates( $post->ID ), __( 'Add price row', 'itour-excursions' ) );
				},
				'save'   => static function ( int $post, array $in ): void {
					global $wpdb;
					$table = $wpdb->prefix . 'itour_excursion_rates';
					$wpdb->delete( $table, array( 'post_id' => $post ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					foreach ( Rows::clean( self::cols()['rates'], $in['rows'] ?? array(), array( 'adult' ) ) as $row ) {
						foreach ( $row as $k => $v ) {
							$row[ $k ] = '' !== $v ? $v : ( str_starts_with( $k, 'season_' ) ? null : 0 ); // Empty season = all year; empty number = 0.
						}
						$wpdb->insert( $table, array( 'post_id' => $post ) + $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					}
					update_post_meta( $post, '_itour_price_from', Excursion::from_price( $post ) ); // For sorting lists by price.
				},
			),
			'addons'      => array(
				'label'  => __( 'Add-ons', 'itour-excursions' ),
				'badge'  => static fn( int $post ): string => self::count( (array) Excursion::config( $post )['addons'] ),
				'render' => static function ( \WP_Post $post ): void {
					self::table( 'addons', 'addons', 'rows', '', __( 'Paid extras the customer can tick: lunch, entry tickets, a guide in their language, photos.', 'itour-excursions' ), (array) Excursion::config( $post->ID )['addons'], __( 'Add add-on', 'itour-excursions' ) );
				},
				'save'   => static function ( int $post, array $in ): void {
					self::put( $post, array( 'addons' => Rows::clean( self::cols()['addons'], $in['rows'] ?? array(), array( 'name', 'price' ) ) ) );
				},
			),
			'supplements' => array(
				'label'  => __( 'Supplements', 'itour-excursions' ),
				'badge'  => static fn( int $post ): string => self::count( (array) Excursion::config( $post )['supplements'] ),
				'render' => static function ( \WP_Post $post ): void {
					self::table( 'supplements', 'supplements', 'rows', '', __( 'Charged automatically on these dates, per person, e.g. a Christmas Gala Meal.', 'itour-excursions' ), (array) Excursion::config( $post->ID )['supplements'], __( 'Add supplement', 'itour-excursions' ) );
				},
				'save'   => static function ( int $post, array $in ): void {
					self::put( $post, array( 'supplements' => Rows::clean( self::cols()['supplements'], $in['rows'] ?? array(), array( 'name', 'from' ) ) ) );
				},
			),
		);
		// phpcs:enable
	}
}
