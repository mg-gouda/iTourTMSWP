<?php
/**
 * Company profile (one company per site) and its settings screen.
 *
 * @package ITour\Core
 */

namespace ITour\Core;

use ITour\Core\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in the `itour_company` option.
 */
final class Company {

	private const OPTION = 'itour_company';

	/**
	 * Form fields.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function fields(): array {
		return array(
			array(
				'key'      => 'name',
				'label'    => __( 'Company name', 'itour-core' ),
				'type'     => 'text',
				'required' => true,
			),
			array(
				'key'   => 'legal_name',
				'label' => __( 'Legal name', 'itour-core' ),
				'type'  => 'text',
				'help'  => __( 'As it appears on invoices and vouchers.', 'itour-core' ),
			),
			array(
				'key'   => 'tax_id',
				'label' => __( 'Tax ID', 'itour-core' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'licence',
				'label' => __( 'Tourism licence no.', 'itour-core' ),
				'type'  => 'text',
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
				'key'   => 'website',
				'label' => __( 'Website', 'itour-core' ),
				'type'  => 'url',
			),
			array(
				'key'   => 'address',
				'label' => __( 'Address', 'itour-core' ),
				'type'  => 'textarea',
			),
			array(
				'key'     => 'country',
				'label'   => __( 'Country', 'itour-core' ),
				'type'    => 'select',
				'options' => array( self::class, 'country_options' ),
			),
			array(
				'key'      => 'base_currency',
				'label'    => __( 'Base currency', 'itour-core' ),
				'type'     => 'select',
				'required' => true,
				'options'  => static fn() => itour()->currencies()->options(),
				'help'     => __( 'Prices and accounts are kept in this currency; others convert from it using the daily rates.', 'itour-core' ),
			),
			array(
				'key'     => 'fiscal_year_start',
				'label'   => __( 'Financial year starts', 'itour-core' ),
				'type'    => 'select',
				'options' => static function (): array {
					global $wp_locale;
					$m = array();
					for ( $i = 1; $i <= 12; $i++ ) {
						$m[ $i ] = $wp_locale->get_month( $i );
					}
					return $m;
				},
				'default' => 1,
			),
		);
	}

	/**
	 * The saved profile, with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		return (array) get_option( self::OPTION, array() ) + array(
			'name'              => get_bloginfo( 'name' ),
			'base_currency'     => 'USD',
			'country'           => '',
			'fiscal_year_start' => 1,
		);
	}

	/**
	 * Countries: code => name.
	 *
	 * @return array<string,string>
	 */
	public static function country_options(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT code, name FROM %i WHERE active = 1 ORDER BY name', $wpdb->prefix . 'itour_countries' ), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( static fn( $r ) => $r->name, (array) $rows );
	}

	/**
	 * Errors from the last failed save.
	 *
	 * @var array{0:array<string,mixed>,1:array<string,string>}|null
	 */
	private static ?array $failed = null;

	/**
	 * Save the settings form (load-{page}).
	 */
	public static function load(): void {
		if ( empty( $_POST['itour_save'] ) || ! current_user_can( 'itour_manage_core' ) ) {
			return;
		}
		check_admin_referer( 'itour_company' );
		$raw                  = isset( $_POST['itour'] ) && is_array( $_POST['itour'] ) ? wp_unslash( $_POST['itour'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by Fields::clean().
		list( $row, $errors ) = Fields::clean( self::fields(), $raw );
		if ( $errors ) {
			self::$failed = array( $row, $errors );
			return;
		}
		update_option( self::OPTION, $row, false );
		wp_safe_redirect( admin_url( 'admin.php?page=itour-core-company&done=saved' ) );
		exit;
	}

	/**
	 * Render the settings form.
	 */
	public static function render(): void {
		list( $values, $errors ) = self::$failed ?? array( self::get(), array() );
		echo '<div class="wrap itour"><h1>' . esc_html__( 'Company', 'itour-core' ) . '</h1>';
		if ( isset( $_GET['done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'itour-core' ) . '</p></div>';
		}
		if ( $errors ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Please fix the highlighted fields.', 'itour-core' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Your company details appear on vouchers, invoices and emails from every iTour module.', 'itour-core' ) . '</p>';
		echo '<form method="post" novalidate>';
		wp_nonce_field( 'itour_company' );
		Fields::table( self::fields(), $values, $errors );
		submit_button( __( 'Save', 'itour-core' ), 'primary', 'itour_save' );
		echo '</form></div>';
	}
}
