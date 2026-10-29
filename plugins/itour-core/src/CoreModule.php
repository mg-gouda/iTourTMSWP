<?php
/**
 * The core's own data and screens: company, branches, partners, currencies, countries, sequences.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

use ITour\Core\Admin\Crud;

defined( 'ABSPATH' ) || exit;

/**
 * Core registered as a module, so it uses the same migrator and menu as every other module.
 */
final class CoreModule extends Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'core';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Core', 'itour-core' );
	}

	/**
	 * Schema version.
	 */
	public function version(): string {
		return '0.2.0';
	}

	/**
	 * Nothing to hook beyond the admin screens.
	 */
	public function boot(): void {
	}

	/**
	 * Core tables.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array(
			'branches'       => 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code varchar(8) NOT NULL,
  name varchar(190) NOT NULL,
  is_hq tinyint(1) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY code (code)',
			'currencies'     => 'code char(3) NOT NULL,
  name varchar(100) NOT NULL,
  name_ar varchar(100) NULL,
  symbol varchar(8) NOT NULL,
  decimals tinyint(1) NOT NULL DEFAULT 2,
  active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (code)',
			'currency_rates' => "code char(3) NOT NULL,
  rate_date date NOT NULL,
  rate decimal(18,6) NOT NULL,
  source varchar(20) NOT NULL DEFAULT 'manual',
  PRIMARY KEY  (code,rate_date)",
			'countries'      => 'code char(2) NOT NULL,
  code3 char(3) NULL,
  name varchar(100) NOT NULL,
  name_ar varchar(100) NULL,
  phone varchar(8) NULL,
  continent varchar(20) NULL,
  active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (code)',
			'partners'       => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  type varchar(20) NOT NULL DEFAULT 'customer',
  is_company tinyint(1) NOT NULL DEFAULT 0,
  name varchar(190) NOT NULL,
  email varchar(190) NULL,
  phone varchar(40) NULL,
  mobile varchar(40) NULL,
  website varchar(190) NULL,
  tax_id varchar(60) NULL,
  country char(2) NULL,
  city varchar(100) NULL,
  street varchar(190) NULL,
  zip varchar(20) NULL,
  credit_limit decimal(14,4) NOT NULL DEFAULT 0,
  credit_currency char(3) NULL,
  notes text NULL,
  active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY type (type),
  KEY name (name)",
			'sequences'      => "code varchar(40) NOT NULL,
  prefix varchar(12) NOT NULL,
  separator_char varchar(3) NOT NULL DEFAULT '-',
  padding tinyint(2) NOT NULL DEFAULT 5,
  next_number int(10) unsigned NOT NULL DEFAULT 1,
  reset_policy varchar(10) NOT NULL DEFAULT 'yearly',
  period varchar(7) NOT NULL DEFAULT '',
  PRIMARY KEY  (code)",
		);
	}

	/**
	 * Seed currencies and countries the first time.
	 *
	 * @param string $from Previous schema version ('' on first install).
	 */
	public function installed( string $from ): void {
		global $wpdb;
		foreach ( array( 'currencies', 'countries' ) as $name ) {
			$table = $wpdb->prefix . 'itour_' . $name;
			if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ) > 0 ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				continue;
			}
			foreach ( require dirname( ITOUR_CORE_FILE ) . "/data/{$name}.php" as $row ) {
				$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
	}

	/**
	 * Admin screens.
	 *
	 * @return array<string,array{title:string,callback:callable,load?:callable}>
	 */
	public function admin_pages(): array {
		$branches   = new Crud(
			array(
				'table'      => 'branches',
				'singular'   => __( 'branch', 'itour-core' ),
				'plural'     => __( 'Branches', 'itour-core' ),
				'capability' => $this->capability(),
				'order'      => 'code',
				'unique'     => array( 'code' ),
				'empty'      => __( 'No branches yet. Add your head office first; offices such as Hurghada or Luxor can follow.', 'itour-core' ),
				'columns'    => array(
					'name'   => __( 'Branch', 'itour-core' ),
					'code'   => __( 'Code', 'itour-core' ),
					'is_hq'  => __( 'Head office', 'itour-core' ),
					'active' => __( 'Active', 'itour-core' ),
				),
				'fields'     => array(
					array(
						'key'      => 'name',
						'label'    => __( 'Name', 'itour-core' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'       => 'code',
						'label'     => __( 'Code', 'itour-core' ),
						'type'      => 'text',
						'required'  => true,
						'maxlength' => 8,
						'help'      => __( 'Short code used in document numbers, e.g. HRG.', 'itour-core' ),
					),
					array(
						'key'            => 'is_hq',
						'label'          => __( 'Head office', 'itour-core' ),
						'type'           => 'checkbox',
						'checkbox_label' => __( 'This is the head office', 'itour-core' ),
					),
					array(
						'key'     => 'active',
						'label'   => __( 'Active', 'itour-core' ),
						'type'    => 'checkbox',
						'default' => 1,
					),
				),
			)
		);
		$partners   = new Crud(
			array(
				'table'      => 'partners',
				'singular'   => __( 'partner', 'itour-core' ),
				'plural'     => __( 'Partners', 'itour-core' ),
				'capability' => $this->capability(),
				'order'      => 'name',
				'search'     => array( 'name', 'email', 'phone' ),
				'empty'      => __( 'No partners yet. Partners are your customers, suppliers, tour operators and corporate clients, shared by every module.', 'itour-core' ),
				'columns'    => array(
					'name'    => __( 'Name', 'itour-core' ),
					'type'    => __( 'Type', 'itour-core' ),
					'email'   => __( 'Email', 'itour-core' ),
					'phone'   => __( 'Phone', 'itour-core' ),
					'country' => __( 'Country', 'itour-core' ),
				),
				'fields'     => array(
					array(
						'key'      => 'name',
						'label'    => __( 'Name', 'itour-core' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'      => 'type',
						'label'    => __( 'Type', 'itour-core' ),
						'type'     => 'select',
						'required' => true,
						'options'  => array(
							'customer'      => __( 'Customer', 'itour-core' ),
							'supplier'      => __( 'Supplier', 'itour-core' ),
							'tour_operator' => __( 'Tour operator', 'itour-core' ),
							'travel_agent'  => __( 'Travel agent', 'itour-core' ),
							'corporate'     => __( 'Corporate', 'itour-core' ),
						),
					),
					array(
						'key'            => 'is_company',
						'label'          => __( 'Organisation', 'itour-core' ),
						'type'           => 'checkbox',
						'checkbox_label' => __( 'This is a company, not a person', 'itour-core' ),
					),
					array(
						'key'   => 'email',
						'label' => __( 'Email', 'itour-core' ),
						'type'  => 'email',
					),
					array(
						'key'   => 'phone',
						'label' => __( 'Phone', 'itour-core' ),
						'type'  => 'tel',
					),
					array(
						'key'   => 'mobile',
						'label' => __( 'Mobile / WhatsApp', 'itour-core' ),
						'type'  => 'tel',
					),
					array(
						'key'   => 'website',
						'label' => __( 'Website', 'itour-core' ),
						'type'  => 'url',
					),
					array(
						'key'   => 'tax_id',
						'label' => __( 'Tax ID', 'itour-core' ),
						'type'  => 'text',
					),
					array(
						'key'     => 'country',
						'label'   => __( 'Country', 'itour-core' ),
						'type'    => 'select',
						'options' => array( Company::class, 'country_options' ),
					),
					array(
						'key'   => 'city',
						'label' => __( 'City', 'itour-core' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'street',
						'label' => __( 'Street', 'itour-core' ),
						'type'  => 'text',
					),
					array(
						'key'       => 'zip',
						'label'     => __( 'Postcode', 'itour-core' ),
						'type'      => 'text',
						'maxlength' => 20,
					),
					array(
						'key'     => 'credit_limit',
						'label'   => __( 'Credit limit', 'itour-core' ),
						'type'    => 'decimal',
						'default' => '0',
						'help'    => __( 'How much this partner may owe before new bookings are blocked. 0 = pay before travel.', 'itour-core' ),
					),
					array(
						'key'     => 'credit_currency',
						'label'   => __( 'Credit currency', 'itour-core' ),
						'type'    => 'select',
						'options' => static fn() => itour()->currencies()->options(),
					),
					array(
						'key'   => 'notes',
						'label' => __( 'Notes', 'itour-core' ),
						'type'  => 'textarea',
					),
					array(
						'key'     => 'active',
						'label'   => __( 'Active', 'itour-core' ),
						'type'    => 'checkbox',
						'default' => 1,
					),
				),
			)
		);
		$currencies = new Crud(
			array(
				'table'      => 'currencies',
				'key'        => 'code',
				'auto_key'   => false,
				'singular'   => __( 'currency', 'itour-core' ),
				'plural'     => __( 'Currencies', 'itour-core' ),
				'capability' => $this->capability(),
				'order'      => 'code',
				'search'     => array( 'code', 'name' ),
				'unique'     => array( 'code' ),
				'columns'    => array(
					'code'   => __( 'Code', 'itour-core' ),
					'name'   => __( 'Name', 'itour-core' ),
					'symbol' => __( 'Symbol', 'itour-core' ),
					'_rate'  => __( 'Rate today', 'itour-core' ),
					'active' => __( 'Active', 'itour-core' ),
				),
				'cell'       => static function ( string $col, array $row ) {
					if ( '_rate' !== $col ) {
						return null;
					}
					$cur = itour()->currencies();
					if ( $row['code'] === $cur->base() ) {
						return __( 'Base currency', 'itour-core' );
					}
					$rate = $cur->rate( $row['code'] );
					/* translators: 1: rate, 2: base currency code. */
					return null === $rate ? __( 'No rate yet', 'itour-core' ) : sprintf( __( '%1$s %2$s', 'itour-core' ), rtrim( rtrim( $rate, '0' ), '.' ), $cur->base() );
				},
				'load_extra' => static fn( string $id, array $row ): array => $row + array( '_rate' => (string) itour()->currencies()->rate( $id ) ),
				'after_save' => static function ( string $id, array $row ): void {
					if ( '' !== $row['_rate'] ) {
						itour()->currencies()->set_rate( $id, (string) $row['_rate'], wp_date( 'Y-m-d' ) );
					}
				},
				'fields'     => array(
					array(
						'key'       => 'code',
						'label'     => __( 'ISO code', 'itour-core' ),
						'type'      => 'text',
						'required'  => true,
						'maxlength' => 3,
					),
					array(
						'key'      => 'name',
						'label'    => __( 'Name', 'itour-core' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'       => 'symbol',
						'label'     => __( 'Symbol', 'itour-core' ),
						'type'      => 'text',
						'required'  => true,
						'maxlength' => 8,
					),
					array(
						'key'     => 'decimals',
						'label'   => __( 'Decimals', 'itour-core' ),
						'type'    => 'number',
						'default' => 2,
					),
					array(
						'key'   => '_rate',
						'label' => __( 'Rate today', 'itour-core' ),
						'type'  => 'decimal',
						/* translators: %s: base currency code. */
						'help'  => sprintf( __( 'How many %s one unit of this currency is worth today. Leave empty to keep the last rate.', 'itour-core' ), itour()->currencies()->base() ),
					),
					array(
						'key'     => 'active',
						'label'   => __( 'Active', 'itour-core' ),
						'type'    => 'checkbox',
						'default' => 1,
					),
				),
			)
		);
		return array(
			'company'    => array(
				'title'    => __( 'Company', 'itour-core' ),
				'callback' => array( Company::class, 'render' ),
				'load'     => array( Company::class, 'load' ),
			),
			'branches'   => array(
				'title'    => __( 'Branches', 'itour-core' ),
				'callback' => array( $branches, 'render' ),
				'load'     => array( $branches, 'load' ),
			),
			'partners'   => array(
				'title'    => __( 'Partners', 'itour-core' ),
				'callback' => array( $partners, 'render' ),
				'load'     => array( $partners, 'load' ),
			),
			'currencies' => array(
				'title'    => __( 'Currencies', 'itour-core' ),
				'callback' => array( $currencies, 'render' ),
				'load'     => array( $currencies, 'load' ),
			),
		);
	}
}
