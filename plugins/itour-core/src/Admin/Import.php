<?php
/**
 * Bulk import / export screen for one product type (Tour Packages, Excursions, Transfer prices).
 * Download a template (header + example row + Instructions sheet) or the current data, fill one product
 * per row, upload: every row is checked first and shown in a report; nothing changes until "Import".
 *
 * Config: title, cap, columns (key => [ label, help, example ]), export (callable: rows of key => text),
 * row (callable( array $row, bool $apply ): array{ action:string, title:string, errors:string[] }).
 * Images are never imported: they are added on each product afterwards.
 *
 * @package ITour\Core
 */

namespace ITour\Core\Admin;

use ITour\Core\Spreadsheet;

defined( 'ABSPATH' ) || exit;

/**
 * One import/export screen.
 */
final class Import {

	/**
	 * Screen config.
	 *
	 * @var array<string,mixed>
	 */
	private array $c;

	/**
	 * Constructor.
	 *
	 * @param string              $id     Screen id (used in nonces, storage, file names).
	 * @param array<string,mixed> $config Config.
	 */
	public function __construct( string $id, array $config ) {
		$this->c       = $config;
		$this->c['id'] = $id;
	}

	/**
	 * Admin page entry for a module's admin_pages().
	 *
	 * @param string $title Menu title.
	 * @return array{title:string,callback:callable,load:callable}
	 */
	public function page( string $title ): array {
		return array(
			'title'    => $title,
			'callback' => array( $this, 'render' ),
			'load'     => array( $this, 'load' ),
		);
	}

	/**
	 * Storage key for this user's pending import.
	 */
	private function key(): string {
		return 'itour_import_' . $this->c['id'] . '_' . get_current_user_id();
	}

	/**
	 * This screen's URL.
	 *
	 * @param array<string,string> $args Query args.
	 */
	private function url( array $args = array() ): string {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base = isset( $_GET['post_type'] ) ? 'edit.php?post_type=' . sanitize_key( wp_unslash( $_GET['post_type'] ) ) . '&page=' . $page : 'admin.php?page=' . $page; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return add_query_arg( $args, admin_url( $base ) );
	}

	/**
	 * Downloads, upload and confirm (before output).
	 */
	public function load(): void {
		if ( ! current_user_can( $this->c['cap'] ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- each action checks its nonce.
		$get = isset( $_GET['itour_import'] ) ? sanitize_key( wp_unslash( $_GET['itour_import'] ) ) : '';
		if ( in_array( $get, array( 'template', 'export' ), true ) ) {
			check_admin_referer( 'itour_import_' . $get . '_' . $this->c['id'] );
			$this->download( 'export' === $get );
		}
		// phpcs:enable
		if ( isset( $_POST['itour_import_upload'] ) ) {
			check_admin_referer( 'itour_import_upload_' . $this->c['id'] );
			$this->upload();
		}
		if ( isset( $_POST['itour_import_confirm'] ) ) {
			check_admin_referer( 'itour_import_confirm_' . $this->c['id'] );
			$this->apply();
		}
		if ( isset( $_POST['itour_import_cancel'] ) ) {
			check_admin_referer( 'itour_import_confirm_' . $this->c['id'] );
			delete_transient( $this->key() );
			wp_safe_redirect( $this->url() );
			exit;
		}
	}

	/**
	 * Send the template (header + example row) or the current data, with the Instructions sheet.
	 *
	 * @param bool $export Current data instead of the example.
	 */
	private function download( bool $export ): void {
		$cols   = $this->c['columns'];
		$header = array_map( static fn( $c ) => $c[0], $cols );
		$rows   = array( array_values( $header ) );
		if ( $export ) {
			foreach ( ( $this->c['export'] )() as $r ) {
				$rows[] = array_map( static fn( $k ) => (string) ( $r[ $k ] ?? '' ), array_keys( $cols ) );
			}
		} else {
			$rows[] = array_values( array_map( static fn( $c ) => (string) ( $c[2] ?? '' ), $cols ) );
		}
		$help = array( array( __( 'Column', 'itour-core' ), __( 'What to write', 'itour-core' ) ) );
		foreach ( $cols as $c ) {
			$help[] = array( $c[0], $c[1] );
		}
		$help[] = array( '', '' );
		$help[] = array( __( 'Rules', 'itour-core' ), __( 'One product per row. Leave ID empty to create a new product; keep the ID to update that product. When updating, an empty cell keeps what is saved; write - (a dash) to clear it. Cells marked "one per line" take one entry per line (Alt+Enter in Excel) with the parts separated by | in the order given. Images are not imported: add them on each product afterwards.', 'itour-core' ) );
		$file   = wp_tempnam( 'itour-import' );
		Spreadsheet::write(
			$file,
			array(
				$this->c['title']                  => array(
					'rows'   => $rows,
					'widths' => array_values( array_map( static fn( $c ) => $c[3] ?? 22, $cols ) ),
				),
				__( 'Instructions', 'itour-core' ) => array(
					'rows'   => $help,
					'widths' => array( 26, 110 ),
				),
			)
		);
		$name = sanitize_file_name( $this->c['id'] . ( $export ? '-export-' . wp_date( 'Y-m-d' ) : '-template' ) . '.xlsx' );
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . (string) filesize( $file ) );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $file );
		exit;
	}

	/**
	 * Read the upload, check every row, keep it for the confirm step.
	 */
	private function upload(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in load(); path checked below.
		$tmp  = isset( $_FILES['itour_file']['tmp_name'] ) ? (string) $_FILES['itour_file']['tmp_name'] : '';
		$rows = '' !== $tmp && is_uploaded_file( $tmp ) ? Spreadsheet::read( $tmp ) : null;
		if ( null === $rows || count( $rows ) < 2 ) {
			wp_safe_redirect( $this->url( array( 'itour_msg' => null === $rows ? 'unreadable' : 'empty' ) ) );
			exit;
		}
		$labels = array();
		foreach ( $this->c['columns'] as $k => $c ) {
			$labels[ mb_strtolower( trim( $c[0] ) ) ] = $k;
		}
		$map = array();
		foreach ( array_shift( $rows ) as $i => $h ) {
			$k = $labels[ mb_strtolower( trim( (string) $h ) ) ] ?? null;
			if ( null !== $k ) {
				$map[ $i ] = $k;
			}
		}
		$data   = array();
		$report = array();
		foreach ( $rows as $n => $cells ) {
			$row = array();
			foreach ( $map as $i => $k ) {
				$row[ $k ] = trim( (string) ( $cells[ $i ] ?? '' ) );
			}
			if ( '' === implode( '', $row ) ) {
				continue; // Blank line.
			}
			$check    = ( $this->c['row'] )( $row, false );
			$data[]   = $row;
			$report[] = array( $n + 1 ) + array(
				1 => $check['action'],
				2 => $check['title'],
				3 => $check['errors'],
			);
		}
		set_transient(
			$this->key(),
			array(
				'rows'   => $data,
				'report' => $report,
				'cols'   => count( $map ),
			),
			HOUR_IN_SECONDS
		);
		wp_safe_redirect( $this->url( array( 'step' => 'review' ) ) );
		exit;
	}

	/**
	 * Import the checked rows.
	 */
	private function apply(): void {
		$pending = get_transient( $this->key() );
		if ( ! is_array( $pending ) ) {
			wp_safe_redirect( $this->url( array( 'itour_msg' => 'expired' ) ) );
			exit;
		}
		$done = array(
			'create' => 0,
			'update' => 0,
			'failed' => 0,
		);
		foreach ( $pending['rows'] as $row ) {
			$r = ( $this->c['row'] )( $row, true );
			if ( $r['errors'] ) {
				++$done['failed'];
			} else {
				++$done[ 'update' === $r['action'] ? 'update' : 'create' ];
			}
		}
		delete_transient( $this->key() );
		wp_safe_redirect( $this->url( array_map( 'strval', $done + array( 'step' => 'done' ) ) ) );
		exit;
	}

	/**
	 * The screen.
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen state.
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		$msg  = isset( $_GET['itour_msg'] ) ? sanitize_key( wp_unslash( $_GET['itour_msg'] ) ) : '';
		// phpcs:enable
		wp_enqueue_style( 'itour-panel', plugins_url( 'assets/panel.css', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION );
		/* translators: %s: product type, e.g. "Tour Packages". */
		echo '<div class="wrap itp-screen"><h1>' . esc_html( sprintf( __( 'Import / export: %s', 'itour-core' ), $this->c['title'] ) ) . '</h1>';
		$notes = array(
			'unreadable' => __( 'That file couldn’t be read. Upload the .xlsx you downloaded here (Excel: File → Save As → Excel Workbook).', 'itour-core' ),
			'empty'      => __( 'The file has no rows under the header.', 'itour-core' ),
			'expired'    => __( 'The checked file expired. Upload it again.', 'itour-core' ),
		);
		if ( isset( $notes[ $msg ] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $notes[ $msg ] ) . '</p></div>';
		}
		if ( ! Spreadsheet::available() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'This server can’t read or write Excel files (the PHP zip extension is missing). Ask your host to enable it.', 'itour-core' ) . '</p></div></div>';
			return;
		}
		echo '<div class="itp"><div class="itp-panes itp-import">';
		if ( 'review' === $step ) {
			$this->render_review();
		} else {
			if ( 'done' === $step ) {
				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				$n = array_map( static fn( $k ) => absint( $_GET[ $k ] ?? 0 ), array( 'create' => 'create', 'update' => 'update', 'failed' => 'failed' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
				// phpcs:enable
				/* translators: 1: created, 2: updated, 3: failed. */
				$text = sprintf( __( 'Import finished: %1$d created, %2$d updated, %3$d failed.', 'itour-core' ), $n['create'], $n['update'], $n['failed'] );
				if ( $n['create'] && ! empty( $this->c['photos'] ) ) {
					$text .= ' ' . __( 'Add photos to the new products from their edit screens.', 'itour-core' );
				}
				echo '<div class="notice notice-success inline"><p>' . esc_html( $text ) . '</p></div>';
			}
			$this->render_start();
		}
		echo '</div></div></div>';
	}

	/**
	 * Step 1: download, fill, upload.
	 */
	private function render_start(): void {
		$template = wp_nonce_url( $this->url( array( 'itour_import' => 'template' ) ), 'itour_import_template_' . $this->c['id'] );
		$export   = wp_nonce_url( $this->url( array( 'itour_import' => 'export' ) ), 'itour_import_export_' . $this->c['id'] );
		echo '<h2 class="itp-pane-title">' . esc_html__( 'Add or update many at once', 'itour-core' ) . '</h2>';
		echo Panel::row( __( '1. Get the spreadsheet', 'itour-core' ), '<a class="itp-btn" href="' . esc_url( $template ) . '">' . esc_html__( 'Download template', 'itour-core' ) . '</a><a class="itp-btn itp-btn--plain" href="' . esc_url( $export ) . '">' . esc_html__( 'Export current data', 'itour-core' ) . '</a>', __( 'The template has one example row and an Instructions sheet. Export gives you what is saved now, ready to edit and upload back.', 'itour-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		echo Panel::row( __( '2. Fill it in', 'itour-core' ), '<p class="itp-help">' . esc_html( $this->c['fill'] ?? __( 'One product per row. Keep the header row as it is.', 'itour-core' ) ) . '</p>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'itour_import_upload_' . $this->c['id'] );
		echo Panel::row( __( '3. Upload and check', 'itour-core' ), '<input type="file" name="itour_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="itp-file"><button type="submit" name="itour_import_upload" value="1" class="itp-btn itp-btn--primary">' . esc_html__( 'Check file', 'itour-core' ) . '</button>', __( 'Every row is checked first. Nothing is saved until you confirm.', 'itour-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		echo '</form>';
	}

	/**
	 * Step 2: the check report and the confirm button.
	 */
	private function render_review(): void {
		$pending = get_transient( $this->key() );
		if ( ! is_array( $pending ) ) {
			echo '<p class="itp-help">' . esc_html__( 'The checked file expired. Upload it again.', 'itour-core' ) . '</p>';
			$this->render_start();
			return;
		}
		$report  = $pending['report'];
		$bad     = array_filter( $report, static fn( $r ) => $r[3] );
		$labels  = array(
			'create' => __( 'New', 'itour-core' ),
			'update' => __( 'Update', 'itour-core' ),
		);
		$counts  = array_count_values( array_map( static fn( $r ) => $r[1], $report ) );
		$summary = sprintf(
			/* translators: 1: rows, 2: new, 3: updates, 4: rows with problems. */
			__( '%1$d rows: %2$d new, %3$d updates, %4$d with problems.', 'itour-core' ),
			count( $report ),
			$counts['create'] ?? 0,
			$counts['update'] ?? 0,
			count( $bad )
		);
		echo '<h2 class="itp-pane-title">' . esc_html__( 'Check the file', 'itour-core' ) . '</h2><p class="itp-help itp-import-sum">' . esc_html( $summary ) . '</p>';
		echo '<div class="itp-table-wrap"><table class="itp-report"><thead><tr><th scope="col">' . esc_html__( 'Row', 'itour-core' ) . '</th><th scope="col">' . esc_html__( 'Product', 'itour-core' ) . '</th><th scope="col">' . esc_html__( 'Action', 'itour-core' ) . '</th><th scope="col">' . esc_html__( 'Problems', 'itour-core' ) . '</th></tr></thead><tbody>';
		foreach ( $report as $r ) {
			echo '<tr class="' . ( $r[3] ? 'is-bad' : 'is-ok' ) . '"><td>' . (int) $r[0] . '</td><td>' . esc_html( $r[2] ) . '</td><td>' . esc_html( $labels[ $r[1] ] ?? $r[1] ) . '</td><td>' . ( $r[3] ? '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $r[3] ) ) . '</li></ul>' : '<span class="itp-ok">' . esc_html__( 'Ready', 'itour-core' ) . '</span>' ) . '</td></tr>';
		}
		echo '</tbody></table></div><form method="post" class="itp-import-actions">';
		wp_nonce_field( 'itour_import_confirm_' . $this->c['id'] );
		if ( $bad ) {
			echo '<p class="itp-help">' . esc_html__( 'Fix the rows with problems in the spreadsheet and upload it again. Rows without problems can be imported now; the others are skipped.', 'itour-core' ) . '</p>';
		}
		$ready = count( $report ) - count( $bad );
		if ( $ready ) {
			/* translators: %d: rows. */
			echo '<button type="submit" name="itour_import_confirm" value="1" class="itp-btn itp-btn--primary">' . esc_html( sprintf( _n( 'Import %d row', 'Import %d rows', $ready, 'itour-core' ), $ready ) ) . '</button>';
		}
		echo '<button type="submit" name="itour_import_cancel" value="1" class="itp-btn">' . esc_html__( 'Start again', 'itour-core' ) . '</button></form>';
	}
}
