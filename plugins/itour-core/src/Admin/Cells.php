<?php
/**
 * Cell conversions for the bulk import / export of products: "one per line" cells with | separated parts,
 * comma lists, weekdays, Location Tree names, and the post itself (title, status, summary, description,
 * categories, languages, suitability). Shared by Tour Packages and Excursions.
 *
 * Rule for updates: an empty cell keeps the saved value; "-" clears it.
 *
 * @package ITour\Core
 */

namespace ITour\Core\Admin;

use ITour\Core\Spreadsheet;

defined( 'ABSPATH' ) || exit;

/**
 * Converters.
 */
final class Cells {

	/**
	 * Text with whitespace collapsed, for "did this change?" checks.
	 *
	 * @param string $v Text.
	 */
	private static function text( string $v ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', $v ) );
	}

	/**
	 * Whether a cell asks to clear the field.
	 *
	 * @param string $v Cell.
	 */
	public static function clear( string $v ): bool {
		return '-' === trim( $v );
	}

	/**
	 * "One per line" cell → rows of parts.
	 *
	 * @param string $v     Cell.
	 * @param int    $parts Expected parts per line (missing parts become '').
	 * @return array<int,array<int,string>>
	 */
	public static function lines( string $v, int $parts ): array {
		$out = array();
		foreach ( preg_split( '/\R/u', $v ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$p     = array_map( 'trim', explode( '|', $line ) );
			$out[] = array_pad( array_slice( $p, 0, $parts ), $parts, '' );
		}
		return $out;
	}

	/**
	 * Rows of parts → "one per line" cell.
	 *
	 * @param array<int,array<int,string|int|float>> $rows Rows.
	 */
	public static function join( array $rows ): string {
		return implode( "\n", array_map( static fn( $r ) => rtrim( implode( ' | ', array_map( 'strval', $r ) ), ' |' ), $rows ) );
	}

	/**
	 * Comma (or line) separated list.
	 *
	 * @param string $v Cell.
	 * @return string[]
	 */
	public static function items( string $v ): array {
		return array_values( array_filter( array_map( 'trim', preg_split( '/[,\n]/u', $v ) ) ) );
	}

	/**
	 * Weekdays from "Mon, Wed, Fri", "Monday" or "any"/"daily" → ISO numbers.
	 *
	 * @param string $v Cell.
	 * @return int[]|null Null when a name isn't a weekday.
	 */
	public static function days( string $v ): ?array {
		if ( preg_match( '/^(any|all|daily|every ?day)$/i', trim( $v ) ) ) {
			return range( 1, 7 );
		}
		$names = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
		$out   = array();
		foreach ( self::items( $v ) as $d ) {
			$i = array_search( mb_strtolower( mb_substr( $d, 0, 3 ) ), $names, true );
			if ( false === $i ) {
				return null;
			}
			$out[] = $i + 1;
		}
		$out = array_values( array_unique( $out ) );
		sort( $out );
		return $out;
	}

	/**
	 * ISO weekday numbers → "Mon, Sat" or "any".
	 *
	 * @param int[] $days Days.
	 */
	public static function days_text( array $days ): string {
		$names = array(
			1 => 'Mon',
			'Tue',
			'Wed',
			'Thu',
			'Fri',
			'Sat',
			'Sun',
		);
		return 7 === count( $days ) ? 'any' : implode( ', ', array_map( static fn( $d ) => $names[ (int) $d ] ?? '', $days ) );
	}

	/**
	 * Location Tree id from a name (case-insensitive); last part of "Hurghada › Sahl Hasheesh" also works.
	 *
	 * @param string   $name  Name.
	 * @param string[] $types Allowed types ([] = any).
	 */
	public static function location( string $name, array $types = array() ): int {
		global $wpdb;
		$parts = array_map( 'trim', preg_split( '/›|>/u', $name ) );
		$name  = (string) end( $parts );
		if ( '' === $name ) {
			return 0;
		}
		$sql = $wpdb->prepare( 'SELECT id, type FROM %i WHERE name = %s ORDER BY active DESC, id', $wpdb->prefix . 'itour_locations', $name );
		foreach ( (array) $wpdb->get_results( $sql ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
			if ( ! $types || in_array( $r->type, $types, true ) ) {
				return (int) $r->id;
			}
		}
		return 0;
	}

	/**
	 * Location Tree name for an id ('' when none).
	 *
	 * @param int $id Id.
	 */
	public static function location_name( int $id ): string {
		global $wpdb;
		return $id ? (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM %i WHERE id = %d', $wpdb->prefix . 'itour_locations', $id ) ) : ''; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * A date cell, '' when empty; null when unreadable.
	 *
	 * @param string $v Cell.
	 */
	public static function date( string $v ): ?string {
		if ( '' === trim( $v ) ) {
			return '';
		}
		$d = Spreadsheet::date( $v );
		return '' === $d ? null : $d;
	}

	/**
	 * A number cell: '' when empty, null when not a number.
	 *
	 * @param string $v Cell.
	 */
	public static function number( string $v ): ?string {
		$v = str_replace( array( ',', ' ' ), array( '.', '' ), trim( $v ) );
		if ( '' === $v ) {
			return '';
		}
		return is_numeric( $v ) ? $v : null;
	}

	/**
	 * Parse a "one per line" cell into keyed rows. Spec: key => [ kind, required ], kinds: text, date,
	 * number, or a callable( string ): ?string that returns the stored value or null when invalid.
	 *
	 * @param string                              $cell  Cell.
	 * @param array<string,array{0:mixed,1:bool}> $spec  Parts in order.
	 * @param string                              $label Column label, for errors.
	 * @return array{rows:array<int,array<string,string>>,errors:string[]}
	 */
	public static function parse( string $cell, array $spec, string $label ): array {
		$rows   = array();
		$errors = array();
		foreach ( self::lines( $cell, count( $spec ) ) as $n => $parts ) {
			$row = array();
			$i   = 0;
			foreach ( $spec as $key => list( $kind, $required ) ) {
				$raw = $parts[ $i++ ];
				if ( 'date' === $kind ) { // Named kinds first: is_callable( 'date' ) is true (PHP's date()).
					$v = self::date( $raw );
				} elseif ( 'number' === $kind ) {
					$v = self::number( $raw );
				} elseif ( 'text' === $kind || ! is_callable( $kind ) ) {
					$v = sanitize_text_field( $raw );
				} else {
					$v = $kind( $raw );
				}
				if ( null === $v || ( $required && '' === $v ) ) {
					/* translators: 1: column, 2: line number, 3: the value. */
					$errors[] = sprintf( __( '%1$s, line %2$d: “%3$s” isn’t valid here.', 'itour-core' ), $label, $n + 1, '' !== $raw ? $raw : '…' );
					continue 2;
				}
				$row[ $key ] = (string) $v;
			}
			$rows[] = $row;
		}
		return array(
			'rows'   => $rows,
			'errors' => $errors,
		);
	}

	/**
	 * Keyed rows → "one per line" cell, parts in the order of $keys.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @param string[]                       $keys Keys in order.
	 * @param array<string,callable>         $fmt  Optional per-key formatter.
	 */
	public static function export_rows( array $rows, array $keys, array $fmt = array() ): string {
		return self::join(
			array_map(
				static function ( $r ) use ( $keys, $fmt ) {
					return array_map( static fn( $k ) => isset( $fmt[ $k ] ) ? $fmt[ $k ]( $r[ $k ] ?? '' ) : (string) ( $r[ $k ] ?? '' ), $keys );
				},
				$rows
			)
		);
	}

	/**
	 * Shared columns every product has after its own: cancellation, included, not included, FAQ.
	 *
	 * @return array<string,array<int,mixed>>
	 */
	public static function info_columns(): array {
		return array(
			'cancellation' => array( __( 'Cancellation', 'itour-core' ), __( 'One tier per line: Days before arrival | Fee % | Travel from | Travel to (dates optional, for a season tier). Example: 30 | 0 then 14 | 50 then 0 | 100.', 'itour-core' ), "30 | 0\n14 | 50\n0 | 100", 22 ),
			'included'     => array( __( 'Included', 'itour-core' ), __( 'One item per line.', 'itour-core' ), "Hotel pick-up\nEgyptologist guide", 26 ),
			'excluded'     => array( __( 'Not included', 'itour-core' ), __( 'One item per line.', 'itour-core' ), 'Tips', 22 ),
			'faq'          => array( __( 'FAQ', 'itour-core' ), __( 'One question per line: Question | Answer.', 'itour-core' ), 'Is lunch included? | Yes, on the Nile.', 30 ),
		);
	}

	/**
	 * Cancellation tier spec.
	 *
	 * @return array<string,array{0:mixed,1:bool}>
	 */
	public static function cancel_spec(): array {
		return array(
			'days' => array( static fn( $v ) => ctype_digit( trim( $v ) ) ? trim( $v ) : null, true ),
			'fee'  => array( static fn( $v ) => is_numeric( trim( $v ) ) && (float) $v >= 0 && (float) $v <= 100 ? trim( $v ) : null, true ),
			'from' => array( 'date', false ),
			'to'   => array( 'date', false ),
		);
	}

	/**
	 * Export the shared info columns.
	 *
	 * @param int $post Post id.
	 * @return array<string,string>
	 */
	public static function info_export( int $post ): array {
		$i = \ITour\Core\Products::info( $post );
		return array(
			'cancellation' => self::export_rows( \ITour\Core\Policies::of( $post ), array( 'days', 'fee', 'from', 'to' ) ),
			'included'     => $i['includes'],
			'excluded'     => $i['excludes'],
			'faq'          => self::export_rows( $i['faq'], array( 'q', 'a' ) ),
		);
	}

	/**
	 * Check / save the shared info columns through the product's own panel tabs.
	 *
	 * @param string               $type  Post type.
	 * @param int                  $post  Post id (0 while checking a new product).
	 * @param array<string,string> $row   Row.
	 * @param bool                 $apply Save.
	 * @return string[] Errors.
	 */
	public static function info_import( string $type, int $post, array $row, bool $apply ): array {
		$cancel = self::parse( (string) ( $row['cancellation'] ?? '' ), self::cancel_spec(), __( 'Cancellation', 'itour-core' ) );
		$faq    = self::parse(
			(string) ( $row['faq'] ?? '' ),
			array(
				'q' => array( 'text', true ),
				'a' => array( static fn( $v ) => sanitize_textarea_field( $v ), true ),
			),
			__( 'FAQ', 'itour-core' )
		);
		$errors = array_merge( $cancel['errors'], $faq['errors'] );
		if ( ! $apply || $errors ) {
			return $errors;
		}
		$set = static fn( string $k ): bool => '' !== ( $row[ $k ] ?? '' );
		if ( $set( 'cancellation' ) ) {
			Panel::save_section( $type, 'cancellation', $post, array( 'rows' => self::clear( $row['cancellation'] ) ? array() : $cancel['rows'] ) );
		}
		if ( $set( 'included' ) || $set( 'excluded' ) ) {
			$i = \ITour\Core\Products::info( $post );
			$v = static fn( string $k, string $cur ): string => ! $set( $k ) ? $cur : ( self::clear( $row[ $k ] ) ? '' : $row[ $k ] );
			Panel::save_section(
				$type,
				'included',
				$post,
				array(
					'includes' => $v( 'included', $i['includes'] ),
					'excludes' => $v( 'excluded', $i['excludes'] ),
				)
			);
		}
		if ( $set( 'faq' ) ) {
			Panel::save_section( $type, 'faq', $post, array( 'rows' => self::clear( $row['faq'] ) ? array() : $faq['rows'] ) );
		}
		return array();
	}

	/**
	 * Shared post columns: ID, title, status, summary, description, categories, languages, suitability.
	 *
	 * @return array<string,array<int,mixed>>
	 */
	public static function post_columns(): array {
		return array(
			'id'          => array( 'ID', __( 'Empty for a new product. Keep the number from an export to update that product.', 'itour-core' ), '', 8 ),
			'title'       => array( __( 'Title', 'itour-core' ), __( 'Required for new products.', 'itour-core' ), 'Luxor day trip from Hurghada', 34 ),
			'status'      => array( __( 'Status', 'itour-core' ), __( 'publish or draft. New products are drafts unless you write publish.', 'itour-core' ), 'draft', 10 ),
			'summary'     => array( __( 'Summary', 'itour-core' ), __( 'One or two sentences, shown in lists.', 'itour-core' ), '', 30 ),
			'description' => array( __( 'Description', 'itour-core' ), __( 'The page text. Leave a blank line between paragraphs.', 'itour-core' ), 'Karnak, the Valley of the Kings and Hatshepsut’s temple in one long day.', 44 ),
			'categories'  => array( __( 'Categories', 'itour-core' ), __( 'Comma separated. New names are created.', 'itour-core' ), 'Day trips, History', 20 ),
			'languages'   => array( __( 'Languages', 'itour-core' ), __( 'Comma separated, e.g. English, German.', 'itour-core' ), 'English, German', 18 ),
			'suitable'    => array( __( 'Suitable for', 'itour-core' ), __( 'Comma separated, e.g. Families, Couples.', 'itour-core' ), 'Families', 18 ),
		);
	}

	/**
	 * Export the shared post columns.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string,string>
	 */
	public static function post_export( \WP_Post $post ): array {
		$terms = static fn( string $tax ): string => implode( ', ', (array) wp_get_post_terms( $post->ID, $tax, array( 'fields' => 'names' ) ) );
		$text  = trim( (string) preg_replace( "/\n{3,}/", "\n\n", wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br />' ), array( "\n\n", "\n", "\n" ), (string) preg_replace( '/<!--.*?-->/s', '', $post->post_content ) ) ) ) );
		return array(
			'id'          => (string) $post->ID,
			'title'       => $post->post_title,
			'status'      => 'publish' === $post->post_status ? 'publish' : 'draft',
			'summary'     => $post->post_excerpt,
			'description' => html_entity_decode( $text ),
			'categories'  => $terms( $post->post_type . '_cat' ),
			'languages'   => $terms( 'itour_language' ),
			'suitable'    => $terms( 'itour_suitability' ),
		);
	}

	/**
	 * Check (and with $apply, create or update) the post part of a row.
	 *
	 * @param string               $type  Post type.
	 * @param array<string,string> $row   Row.
	 * @param bool                 $apply Save.
	 * @return array{id:int,action:string,title:string,errors:string[]}
	 */
	public static function post( string $type, array $row, bool $apply ): array {
		$id     = absint( $row['id'] ?? 0 );
		$errors = array();
		$title  = trim( (string) ( $row['title'] ?? '' ) );
		if ( $id && get_post_type( $id ) !== $type ) {
			/* translators: %d: post id. */
			$errors[] = sprintf( __( 'ID %d is not one of these products. Leave ID empty to create a new one.', 'itour-core' ), $id );
		}
		if ( ! $id && '' === $title ) {
			$errors[] = __( 'Title is required for a new product.', 'itour-core' );
		}
		$status = mb_strtolower( trim( (string) ( $row['status'] ?? '' ) ) );
		if ( '' !== $status && ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
			$errors[] = __( 'Status must be publish or draft.', 'itour-core' );
		}
		$out = array(
			'id'     => $id,
			'action' => $id ? 'update' : 'create',
			'title'  => '' !== $title ? $title : ( $id ? get_the_title( $id ) : '' ),
			'errors' => $errors,
		);
		if ( ! $apply || $errors ) {
			return $out;
		}
		$post = array(
			'ID'        => $id,
			'post_type' => $type,
		);
		if ( '' !== $title ) {
			$post['post_title'] = $title;
		}
		$post['post_status'] = '' !== $status ? $status : ( $id ? get_post_status( $id ) : 'draft' );
		foreach ( array( 'summary' => 'post_excerpt' ) as $k => $field ) {
			if ( '' !== ( $row[ $k ] ?? '' ) ) {
				$post[ $field ] = self::clear( $row[ $k ] ) ? '' : sanitize_textarea_field( $row[ $k ] );
			}
		}
		// Rewrite the page text only when the cell differs from the saved text, so a re-imported export never
		// flattens formatting, images or blocks the description already has.
		$same = $id && self::text( $row['description'] ?? '' ) === self::text( self::post_export( get_post( $id ) )['description'] );
		if ( '' !== ( $row['description'] ?? '' ) && ! $same ) {
			$paras                = self::clear( $row['description'] ) ? array() : array_filter( array_map( 'trim', preg_split( '/\R{2,}/u', $row['description'] ) ) );
			$post['post_content'] = implode( "\n\n", array_map( static fn( $p ) => "<!-- wp:paragraph -->\n<p>" . nl2br( esc_html( $p ), false ) . "</p>\n<!-- /wp:paragraph -->", $paras ) );
		}
		$new = $id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $new ) ) {
			$out['errors'][] = $new->get_error_message();
			return $out;
		}
		$out['id'] = (int) $new;
		foreach ( array( 'categories' => $type . '_cat', 'languages' => 'itour_language', 'suitable' => 'itour_suitability' ) as $k => $tax ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
			if ( '' !== ( $row[ $k ] ?? '' ) ) {
				wp_set_object_terms( $out['id'], self::clear( $row[ $k ] ) ? array() : self::items( $row[ $k ] ), $tax );
			}
		}
		return $out;
	}
}
