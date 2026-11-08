<?php
/**
 * iTour Forms: contact/enquiry form block, submissions table, Enquiries screen.
 *
 * @package ITour\Forms
 */

namespace ITour\Forms;

use ITour\Core\Admin\Crud;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Forms module with iTour Core.
 */
final class Module extends \ITour\Core\Module {

	/**
	 * Module slug.
	 */
	public function slug(): string {
		return 'forms';
	}

	/**
	 * Module name.
	 */
	public function name(): string {
		return __( 'Forms', 'itour-forms' );
	}

	/**
	 * Schema version.
	 */
	public function version(): string {
		return '0.1.0';
	}

	/**
	 * Submissions table.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array(
			'form_entries' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  form varchar(60) NOT NULL DEFAULT 'contact',
  created_at datetime NOT NULL,
  status varchar(10) NOT NULL DEFAULT 'new',
  name varchar(190) NOT NULL,
  email varchar(190) NOT NULL,
  phone varchar(40) NULL,
  subject varchar(190) NULL,
  travel_date date NULL,
  travellers smallint(5) unsigned NULL,
  message text NOT NULL,
  page_url varchar(255) NULL,
  ip_hash char(16) NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at),
  KEY status (status)",
		);
	}

	/**
	 * Hook the block and the submit handler.
	 */
	public function boot(): void {
		add_action(
			'init',
			static function (): void {
				register_block_type( dirname( ITOUR_FORMS_FILE ) . '/blocks/contact-form' );
			}
		);
		add_action( 'admin_post_nopriv_itour_form', array( Submit::class, 'handle' ) );
		add_action( 'admin_post_itour_form', array( Submit::class, 'handle' ) );
	}

	/**
	 * Enquiries screen (read-only list).
	 *
	 * @return array<string,array{title:string,callback:callable,load?:callable}>
	 */
	public function admin_pages(): array {
		$crud = new Crud(
			array(
				'table'      => 'form_entries',
				'singular'   => __( 'enquiry', 'itour-forms' ),
				'plural'     => __( 'Enquiries', 'itour-forms' ),
				'capability' => $this->capability(),
				'order'      => 'created_at',
				'order_desc' => true,
				'search'     => array( 'name', 'email', 'subject', 'message' ),
				'can_add'    => false,
				'readonly'   => true,
				'empty'      => __( 'No enquiries yet. Add the “Contact form” block to a page to start receiving them.', 'itour-forms' ),
				'on_view'    => static function ( string $id ): void {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, single-row status flag.
					$wpdb->update(
						$wpdb->prefix . 'itour_form_entries',
						array( 'status' => 'read' ),
						array(
							'id'     => (int) $id,
							'status' => 'new',
						)
					); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				},
				'columns'    => array(
					'name'       => __( 'From', 'itour-forms' ),
					'email'      => __( 'Email', 'itour-forms' ),
					'subject'    => __( 'Subject', 'itour-forms' ),
					'created_at' => __( 'Received', 'itour-forms' ),
					'status'     => __( 'Status', 'itour-forms' ),
				),
				'cell'       => static function ( string $col, array $row ) {
					if ( 'created_at' === $col ) {
						return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] . ' UTC' ) );
					}
					if ( 'status' === $col ) {
						return 'new' === $row['status'] ? __( 'New', 'itour-forms' ) : __( 'Read', 'itour-forms' );
					}
					return null;
				},
				'fields'     => array(
					array(
						'key'   => 'name',
						'label' => __( 'Name', 'itour-forms' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'email',
						'label' => __( 'Email', 'itour-forms' ),
						'type'  => 'email',
					),
					array(
						'key'   => 'phone',
						'label' => __( 'Phone', 'itour-forms' ),
						'type'  => 'tel',
					),
					array(
						'key'   => 'subject',
						'label' => __( 'Subject', 'itour-forms' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'travel_date',
						'label' => __( 'Travel date', 'itour-forms' ),
						'type'  => 'date',
					),
					array(
						'key'   => 'travellers',
						'label' => __( 'Travellers', 'itour-forms' ),
						'type'  => 'number',
					),
					array(
						'key'   => 'message',
						'label' => __( 'Message', 'itour-forms' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'page_url',
						'label' => __( 'Sent from', 'itour-forms' ),
						'type'  => 'url',
					),
				),
			)
		);
		return array(
			'enquiries' => array(
				'title'    => __( 'Enquiries', 'itour-forms' ),
				'callback' => array( $crud, 'render' ),
				'load'     => array( $crud, 'load' ),
			),
		);
	}
}
