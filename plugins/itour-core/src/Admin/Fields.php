<?php
/**
 * Form fields shared by every iTour admin screen: render, sanitize, validate.
 *
 * A field is an array: key, label, type (text|email|url|tel|number|decimal|select|checkbox|textarea|date),
 * plus optional required, options (array or callable returning value => label), help, placeholder, maxlength.
 *
 * @package ITour\Core
 */

namespace ITour\Core\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless field helpers.
 */
final class Fields {

	/**
	 * Render fields as a WordPress form table.
	 *
	 * @param array<int,array<string,mixed>> $fields Field definitions.
	 * @param array<string,mixed>            $values Current values by key.
	 * @param array<string,string>           $errors Error messages by key.
	 */
	public static function table( array $fields, array $values, array $errors = array() ): void {
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $fields as $f ) {
			$key   = (string) $f['key'];
			$id    = 'itour-' . $key;
			$error = $errors[ $key ] ?? '';
			echo '<tr><th scope="row">';
			if ( 'checkbox' !== $f['type'] ) {
				echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . ( empty( $f['required'] ) ? '' : ' <span class="itour-req" aria-hidden="true">*</span>' ) . '</label>';
			} else {
				echo esc_html( $f['label'] );
			}
			echo '</th><td>';
			self::control( $f, $values[ $key ] ?? ( $f['default'] ?? '' ), $id, '' !== $error );
			if ( $error ) {
				echo '<p class="itour-error" id="' . esc_attr( $id ) . '-error">' . esc_html( $error ) . '</p>';
			}
			if ( ! empty( $f['help'] ) ) {
				echo '<p class="description">' . esc_html( $f['help'] ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Render one control.
	 *
	 * @param array<string,mixed> $f       Field.
	 * @param mixed               $value   Current value.
	 * @param string              $id      Element id.
	 * @param bool                $invalid Whether it failed validation.
	 */
	private static function control( array $f, $value, string $id, bool $invalid ): void {
		$name  = 'itour[' . $f['key'] . ']';
		$attrs = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"'
			. ( empty( $f['required'] ) ? '' : ' required' )
			. ( empty( $f['readonly'] ) ? '' : ' readonly' )
			. ( $invalid ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-error"' : '' );

		switch ( $f['type'] ) {
			case 'select':
				echo '<select' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				if ( empty( $f['required'] ) ) {
					echo '<option value="">' . esc_html__( '— None —', 'itour-core' ) . '</option>';
				}
				foreach ( self::options( $f ) as $opt => $label ) {
					echo '<option value="' . esc_attr( (string) $opt ) . '"' . selected( (string) $value, (string) $opt, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				return;
			case 'checkbox':
				echo '<label><input type="checkbox" value="1"' . $attrs . checked( (bool) $value, true, false ) . '> ' . esc_html( $f['checkbox_label'] ?? __( 'Yes', 'itour-core' ) ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				return;
			case 'textarea':
				echo '<textarea class="large-text" rows="4"' . $attrs . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				return;
		}
		$type   = array(
			'decimal' => 'number',
			'number'  => 'number',
			'email'   => 'email',
			'url'     => 'url',
			'tel'     => 'tel',
			'date'    => 'date',
		)[ $f['type'] ] ?? 'text';
		$extra  = 'decimal' === $f['type'] ? ' step="any"' : '';
		$extra .= isset( $f['maxlength'] ) ? ' maxlength="' . (int) $f['maxlength'] . '"' : '';
		$extra .= isset( $f['placeholder'] ) ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';
		$class  = in_array( $f['type'], array( 'number', 'decimal' ), true ) || ( isset( $f['maxlength'] ) && $f['maxlength'] <= 8 ) ? 'small-text' : 'regular-text';
		echo '<input type="' . esc_attr( $type ) . '" class="' . esc_attr( $class ) . '" value="' . esc_attr( (string) $value ) . '"' . $attrs . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	/**
	 * Resolve a select field's options.
	 *
	 * @param array<string,mixed> $f Field.
	 * @return array<string|int,string>
	 */
	public static function options( array $f ): array {
		$o = $f['options'] ?? array();
		return is_callable( $o ) ? (array) $o() : (array) $o;
	}

	/**
	 * Sanitize and validate submitted values.
	 *
	 * @param array<int,array<string,mixed>> $fields Field definitions.
	 * @param array<string,mixed>            $raw    Submitted values (unslashed).
	 * @return array{0:array<string,mixed>,1:array<string,string>} Clean values and errors.
	 */
	public static function clean( array $fields, array $raw ): array {
		$out    = array();
		$errors = array();
		foreach ( $fields as $f ) {
			$key = (string) $f['key'];
			$v   = $raw[ $key ] ?? '';
			switch ( $f['type'] ) {
				case 'checkbox':
					$v = empty( $v ) ? 0 : 1;
					break;
				case 'email':
					$v = sanitize_email( (string) $v );
					break;
				case 'url':
					$v = esc_url_raw( trim( (string) $v ) );
					break;
				case 'number':
					$v = '' === $v ? '' : (int) $v;
					break;
				case 'decimal':
					$v = '' === trim( (string) $v ) ? '' : ( is_numeric( $v ) ? (string) $v : null );
					break;
				case 'textarea':
					$v = sanitize_textarea_field( (string) $v );
					break;
				case 'select':
					$v = (string) $v;
					if ( '' !== $v && ! array_key_exists( $v, self::options( $f ) ) ) {
						$v = null;
					}
					break;
				case 'date':
					$v = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? (string) $v : '';
					break;
				default:
					$v = sanitize_text_field( (string) $v );
					if ( isset( $f['maxlength'] ) ) {
						$v = mb_substr( $v, 0, (int) $f['maxlength'] );
					}
			}
			if ( null === $v ) {
				/* translators: %s: field label. */
				$errors[ $key ] = sprintf( __( '%s has an invalid value.', 'itour-core' ), $f['label'] );
				$v              = '';
			} elseif ( ! empty( $f['required'] ) && '' === $v ) {
				/* translators: %s: field label. */
				$errors[ $key ] = sprintf( __( '%s is required.', 'itour-core' ), $f['label'] );
			}
			$out[ $key ] = $v;
		}
		return array( $out, $errors );
	}
}
