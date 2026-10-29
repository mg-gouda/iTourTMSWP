<?php
/**
 * Generic list + add/edit screen for one iTour table. Every module builds its admin from this.
 *
 * Config keys: table (without prefix), key (primary key column, default id), singular, plural,
 * fields (see Fields), columns (list columns: key => label), search (columns), order (ORDER BY column),
 * capability, unique (columns that must be unique), auto_key (bool, default true),
 * after_save (callable receiving the row id and clean values).
 *
 * @package ITour\Core
 */

namespace ITour\Core\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One table's admin screen.
 */
final class Crud {

	/**
	 * Screen config.
	 *
	 * @var array<string,mixed>
	 */
	private array $c;

	/**
	 * Values and errors kept after a failed save, so the form shows them.
	 *
	 * @var array{0:array<string,mixed>,1:array<string,string>}|null
	 */
	private ?array $failed = null;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $config Screen config.
	 */
	public function __construct( array $config ) {
		$this->c = $config + array(
			'key'      => 'id',
			'search'   => array(),
			'unique'   => array(),
			'auto_key' => true,
		);
	}

	/**
	 * Full table name.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'itour_' . $this->c['table'];
	}

	/**
	 * This screen's admin URL.
	 *
	 * @param array<string,string|int> $args Extra query args.
	 */
	private function url( array $args = array() ): string {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		return add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) );
	}

	/**
	 * Handle save and delete before output (hooked on load-{page}).
	 */
	public function load(): void {
		if ( ! current_user_can( $this->c['capability'] ) ) {
			return;
		}
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonces checked below.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = sanitize_text_field( wp_unslash( $_GET['id'] ) );
			check_admin_referer( 'itour_delete_' . $this->c['table'] . '_' . $id );
			$wpdb->delete( $this->table(), array( $this->c['key'] => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_safe_redirect( $this->url( array( 'done' => 'deleted' ) ) );
			exit;
		}
		// phpcs:enable
		if ( empty( $_POST['itour_save'] ) ) {
			return;
		}
		check_admin_referer( 'itour_save_' . $this->c['table'] );
		$id  = isset( $_POST['itour_id'] ) ? sanitize_text_field( wp_unslash( $_POST['itour_id'] ) ) : '';
		$raw = isset( $_POST['itour'] ) && is_array( $_POST['itour'] ) ? wp_unslash( $_POST['itour'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by Fields::clean().

		list( $row, $errors ) = Fields::clean( $this->c['fields'], $raw );
		foreach ( $this->c['unique'] as $col ) {
			if ( '' === $row[ $col ] || isset( $errors[ $col ] ) ) {
				continue;
			}
			$clash = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( 'SELECT %i FROM %i WHERE %i = %s AND %i <> %s', $this->c['key'], $this->table(), $col, $row[ $col ], $this->c['key'], $id )
			);
			if ( null !== $clash ) {
				/* translators: %s: field value. */
				$errors[ $col ] = sprintf( __( '“%s” is already used.', 'itour-core' ), $row[ $col ] );
			}
		}
		if ( $errors ) {
			$this->failed = array( $row, $errors );
			return;
		}
		$store = array_filter( $row, static fn( $k ) => 0 !== strpos( (string) $k, '_' ), ARRAY_FILTER_USE_KEY );
		$store = array_map( static fn( $v ) => '' === $v ? null : $v, $store );
		if ( '' !== $id ) {
			$wpdb->update( $this->table(), $store, array( $this->c['key'] => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$wpdb->insert( $this->table(), $store ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$id = $this->c['auto_key'] ? (string) $wpdb->insert_id : (string) $row[ $this->c['key'] ];
		}
		if ( isset( $this->c['after_save'] ) ) {
			( $this->c['after_save'] )( $id, $row );
		}
		wp_safe_redirect( $this->url( array( 'done' => 'saved' ) ) );
		exit;
	}

	/**
	 * Render the list or the form.
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen state.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$done   = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
		// phpcs:enable
		echo '<div class="wrap itour">';
		if ( $this->failed || in_array( $action, array( 'new', 'edit' ), true ) ) {
			$this->form( $this->failed ? ( isset( $_POST['itour_id'] ) ? sanitize_text_field( wp_unslash( $_POST['itour_id'] ) ) : '' ) : $id ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in load().
		} else {
			$this->listing( $done );
		}
		echo '</div>';
	}

	/**
	 * The add/edit form.
	 *
	 * @param string $id Row id, or empty for a new row.
	 */
	private function form( string $id ): void {
		global $wpdb;
		$values = array();
		if ( '' !== $id ) {
			$values = (array) $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE %i = %s', $this->table(), $this->c['key'], $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( ! $values ) {
				echo '<h1>' . esc_html__( 'Not found', 'itour-core' ) . '</h1><p><a href="' . esc_url( $this->url() ) . '">' . esc_html__( 'Back to the list', 'itour-core' ) . '</a></p>';
				return;
			}
			if ( isset( $this->c['load_extra'] ) ) {
				$values = ( $this->c['load_extra'] )( $id, $values );
			}
		}
		$errors = array();
		if ( $this->failed ) {
			list( $values, $errors ) = $this->failed;
		}
		/* translators: %s: item type, e.g. "branch". */
		$title = '' === $id ? sprintf( __( 'Add %s', 'itour-core' ), $this->c['singular'] ) : sprintf( __( 'Edit %s', 'itour-core' ), $this->c['singular'] );
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p><a href="' . esc_url( $this->url() ) . '">&larr; ' . esc_html( $this->c['plural'] ) . '</a></p>';
		if ( $errors ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Please fix the highlighted fields.', 'itour-core' ) . '</p></div>';
		}
		echo '<form method="post" novalidate>';
		wp_nonce_field( 'itour_save_' . $this->c['table'] );
		echo '<input type="hidden" name="itour_id" value="' . esc_attr( $id ) . '">';
		$fields = $this->c['fields'];
		if ( '' !== $id && ! $this->c['auto_key'] ) {
			// A natural key (e.g. a currency code) can't change once saved.
			$pk     = $this->c['key'];
			$fields = array_map( static fn( $f ) => $f['key'] === $pk ? array( 'readonly' => true ) + $f : $f, $fields );
		}
		Fields::table( $fields, $values, $errors );
		submit_button( __( 'Save', 'itour-core' ), 'primary', 'itour_save' );
		echo '</form>';
	}

	/**
	 * The list with search and pagination.
	 *
	 * @param string $done Result of the last action, for the notice.
	 */
	private function listing( string $done ): void {
		global $wpdb;
		$per  = 50;
		$page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$q    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$where = '1=1';
		$args  = array( $this->table() );
		if ( '' !== $q && $this->c['search'] ) {
			$like  = '%' . $wpdb->esc_like( $q ) . '%';
			$where = '(' . implode( ' OR ', array_fill( 0, count( $this->c['search'] ), '%i LIKE %s' ) ) . ')';
			foreach ( $this->c['search'] as $col ) {
				array_push( $args, $col, $like );
			}
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- $where only holds placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where}", ...$args ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$where} ORDER BY %i LIMIT %d OFFSET %d", ...array_merge( $args, array( $this->c['order'] ?? $this->c['key'], $per, ( $page - 1 ) * $per ) ) ), ARRAY_A );
		// phpcs:enable

		echo '<h1 class="wp-heading-inline">' . esc_html( $this->c['plural'] ) . '</h1> ';
		echo '<a class="page-title-action" href="' . esc_url( $this->url( array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Add new', 'itour-core' ) . '</a><hr class="wp-header-end">';
		if ( 'saved' === $done ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'itour-core' ) . '</p></div>';
		} elseif ( 'deleted' === $done ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Deleted.', 'itour-core' ) . '</p></div>';
		}
		if ( $this->c['search'] ) {
			echo '<form method="get" class="search-form"><input type="hidden" name="page" value="' . esc_attr( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' ) . '">'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<p class="search-box"><label class="screen-reader-text" for="itour-search">' . esc_html__( 'Search', 'itour-core' ) . '</label><input type="search" id="itour-search" name="s" value="' . esc_attr( $q ) . '"> ';
			submit_button( __( 'Search', 'itour-core' ), '', '', false );
			echo '</p></form>';
		}
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		foreach ( $this->c['columns'] as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			$empty = '' !== $q ? __( 'Nothing matches that search.', 'itour-core' ) : ( $this->c['empty'] ?? __( 'Nothing here yet. Use “Add new” to create the first one.', 'itour-core' ) );
			echo '<tr><td colspan="' . count( $this->c['columns'] ) . '">' . esc_html( $empty ) . '</td></tr>';
		}
		foreach ( (array) $rows as $row ) {
			$id    = (string) $row[ $this->c['key'] ];
			$first = true;
			echo '<tr>';
			foreach ( array_keys( $this->c['columns'] ) as $col ) {
				$cell = $this->cell( $col, $row );
				if ( $first ) {
					$edit = $this->url(
						array(
							'action' => 'edit',
							'id'     => $id,
						)
					);
					$del  = wp_nonce_url(
						$this->url(
							array(
								'action' => 'delete',
								'id'     => $id,
							)
						),
						'itour_delete_' . $this->c['table'] . '_' . $id
					);
					echo '<td class="column-primary"><strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $cell ) . '</a></strong>';
					echo '<div class="row-actions"><span class="edit"><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'itour-core' ) . '</a> | </span>';
					echo '<span class="trash"><a href="' . esc_url( $del ) . '" onclick="return confirm(this.dataset.confirm)" data-confirm="' . esc_attr__( 'Delete this permanently?', 'itour-core' ) . '">' . esc_html__( 'Delete', 'itour-core' ) . '</a></span></div></td>';
					$first = false;
				} else {
					echo '<td>' . esc_html( $cell ) . '</td>';
				}
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<div class="tablenav bottom"><div class="tablenav-pages">' . wp_kses_post(
				(string) paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $page,
						'total'   => $pages,
					)
				)
			) . '</div></div>';
		}
	}

	/**
	 * Display text for a list cell: select labels, yes/no for checkboxes, raw value otherwise.
	 *
	 * @param string              $col Column key.
	 * @param array<string,mixed> $row Row.
	 */
	private function cell( string $col, array $row ): string {
		if ( isset( $this->c['cell'] ) ) {
			$custom = ( $this->c['cell'] )( $col, $row );
			if ( null !== $custom ) {
				return (string) $custom;
			}
		}
		$v = $row[ $col ] ?? '';
		foreach ( $this->c['fields'] as $f ) {
			if ( $f['key'] !== $col ) {
				continue;
			}
			if ( 'checkbox' === $f['type'] ) {
				return $v ? __( 'Yes', 'itour-core' ) : '—';
			}
			if ( 'select' === $f['type'] ) {
				return (string) ( Fields::options( $f )[ $v ] ?? $v );
			}
		}
		return '' === (string) $v ? '—' : (string) $v;
	}
}
