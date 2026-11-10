<?php
/**
 * Repeating rows editor (price tables, pick-up zones, add-ons…): a table of inputs with Add row /
 * Remove. Rows post as name[i][key]; Rows::clean() sanitizes them and drops empty ones.
 *
 * Column: key => [ 'label' => …, 'type' => text|textarea|number|decimal|date|time|select, 'options' => [] | callable, 'width' => '8em' ]
 *
 * @package ITour\Core
 */

namespace ITour\Core\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Render + clean.
 */
final class Rows {

	/**
	 * Print the editor.
	 *
	 * @param string                            $name Input name prefix.
	 * @param array<string,array<string,mixed>> $cols Columns.
	 * @param array<int,array<string,mixed>>    $rows Saved rows.
	 * @param string                            $add  "Add row" button text.
	 */
	public static function render( string $name, array $cols, array $rows, string $add = '' ): void {
		wp_enqueue_script( 'itour-rows', plugins_url( 'assets/rows.js', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION, true );
		echo '<table class="widefat itour-rows" data-itour-rows><thead><tr>';
		foreach ( $cols as $c ) {
			echo '<th scope="col"' . ( isset( $c['width'] ) ? ' style="width:' . esc_attr( $c['width'] ) . '"' : '' ) . '>' . esc_html( $c['label'] ) . '</th>';
		}
		echo '<th scope="col" style="width:3em"><span class="screen-reader-text">' . esc_html__( 'Remove', 'itour-core' ) . '</span></th></tr></thead><tbody>';
		foreach ( array_values( $rows ) as $i => $row ) {
			self::row( $name, $cols, (array) $row, (string) $i );
		}
		echo '</tbody><tfoot><tr><td colspan="' . ( count( $cols ) + 1 ) . '">';
		echo '<template>';
		self::row( $name, $cols, array(), '__i__' );
		echo '</template><button type="button" class="button" data-itour-rows-add>' . esc_html( '' !== $add ? $add : __( 'Add row', 'itour-core' ) ) . '</button></td></tr></tfoot></table>';
	}

	/**
	 * One row.
	 *
	 * @param string                            $name Input name prefix.
	 * @param array<string,array<string,mixed>> $cols Columns.
	 * @param array<string,mixed>               $row  Values.
	 * @param string                            $i    Row index.
	 */
	private static function row( string $name, array $cols, array $row, string $i ): void {
		echo '<tr>';
		foreach ( $cols as $key => $c ) {
			$n = $name . '[' . $i . '][' . $key . ']';
			$v = (string) ( $row[ $key ] ?? ( $c['default'] ?? '' ) );
			if ( ! empty( $c['zero_empty'] ) && '0' === $v ) {
				$v = ''; // e.g. "To travellers": 0 means no limit, shown empty.
			}
			$a = ' name="' . esc_attr( $n ) . '" aria-label="' . esc_attr( $c['label'] ) . '"';
			echo '<td>';
			if ( 'select' === $c['type'] ) {
				echo '<select' . $a . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				foreach ( Fields::options( $c ) as $opt => $label ) {
					echo '<option value="' . esc_attr( (string) $opt ) . '"' . selected( $v, (string) $opt, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'textarea' === $c['type'] ) {
				echo '<textarea class="widefat" rows="3"' . $a . '>' . esc_textarea( $v ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			} else {
				$type = array(
					'number'  => 'number',
					'decimal' => 'number',
					'date'    => 'date',
					'time'    => 'time',
				)[ $c['type'] ] ?? 'text';
				echo '<input type="' . esc_attr( $type ) . '" class="widefat" value="' . esc_attr( $v ) . '"' . $a . ( 'decimal' === $c['type'] ? ' step="any" min="0"' : '' ) . ( 'number' === $c['type'] ? ' min="0"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			}
			echo '</td>';
		}
		echo '<td><button type="button" class="button-link button-link-delete" data-itour-rows-remove>' . esc_html__( 'Remove', 'itour-core' ) . '</button></td></tr>';
	}

	/**
	 * Sanitize posted rows; rows whose required columns are empty are dropped.
	 *
	 * @param array<string,array<string,mixed>> $cols     Columns.
	 * @param mixed                             $raw      Posted rows (unslashed).
	 * @param string[]                          $required Columns that must be filled for a row to count.
	 * @return array<int,array<string,mixed>>
	 */
	public static function clean( array $cols, $raw, array $required ): array {
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$row = array();
			foreach ( $cols as $key => $c ) {
				$v = trim( (string) ( $r[ $key ] ?? '' ) );
				switch ( $c['type'] ) {
					case 'number':
						$v = '' === $v ? '' : max( 0, (int) $v );
						break;
					case 'decimal':
						$v = is_numeric( $v ) ? (string) max( 0, (float) $v ) : '';
						break;
					case 'date':
						$v = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
						break;
					case 'time':
						$v = preg_match( '/^\d{2}:\d{2}$/', $v ) ? $v : '';
						break;
					case 'select':
						$v = array_key_exists( $v, Fields::options( $c ) ) ? $v : (string) array_key_first( Fields::options( $c ) );
						break;
					case 'textarea':
						$v = sanitize_textarea_field( (string) ( $r[ $key ] ?? '' ) );
						break;
					default:
						$v = sanitize_text_field( $v );
				}
				$row[ $key ] = $v;
			}
			$filled = array_filter( $required, static fn( $k ) => '' !== $row[ $k ] );
			if ( count( $filled ) === count( $required ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}
}
