<?php
/**
 * Contact form markup. Works as a plain form post; view.js submits it in place.
 *
 * @package ITour\Forms
 *
 * @var array<string,mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$itour_a      = $attributes;
$itour_signed = \ITour\Forms\Submit::sign( $itour_a );
$itour_uid    = wp_unique_id( 'itour-form-' );
$itour_state  = isset( $_GET['itour_form'] ) ? sanitize_key( wp_unslash( $_GET['itour_form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$itour_thanks = '' !== ( $itour_a['successMessage'] ?? '' ) ? $itour_a['successMessage'] : __( 'Thank you! We’ll reply within one working day.', 'itour-forms' );
$itour_field  = static function ( string $name, string $label, string $type, bool $required, string $uid, array $extra = array() ): void {
	$id = $uid . '-' . $name;
	echo '<p class="itour-form__field itour-form__field--' . esc_attr( $name ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span class="itour-form__req" aria-hidden="true">*</span>' : '' ) . '</label>';
	if ( 'textarea' === $type ) {
		echo '<textarea id="' . esc_attr( $id ) . '" name="itour_' . esc_attr( $name ) . '" rows="5"' . ( $required ? ' required' : '' ) . '></textarea>';
	} else {
		$attrs = '';
		foreach ( $extra as $k => $v ) {
			$attrs .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="itour_' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs escaped above.
	}
	echo '<span class="itour-form__error" id="' . esc_attr( $id ) . '-error" hidden></span></p>';
};
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'itour-form' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?> id="<?php echo esc_attr( $itour_uid ); ?>">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-itour-form>
		<input type="hidden" name="action" value="itour_form">
		<input type="hidden" name="itour_cfg" value="<?php echo esc_attr( $itour_signed['cfg'] ); ?>">
		<input type="hidden" name="itour_sig" value="<?php echo esc_attr( $itour_signed['sig'] ); ?>">
		<p class="itour-form__trap" aria-hidden="true"><label><?php esc_html_e( 'Leave this empty', 'itour-forms' ); ?> <input type="text" name="itour_website" tabindex="-1" autocomplete="off"></label></p>

		<div class="itour-form__status" role="status" aria-live="polite"<?php echo 'sent' === $itour_state ? '' : ' hidden'; ?>><?php echo 'sent' === $itour_state ? esc_html( $itour_thanks ) : ''; ?></div>
		<?php if ( 'error' === $itour_state ) : ?>
			<div class="itour-form__status is-error" role="alert"><?php esc_html_e( 'Your message could not be sent. Please check the fields and try again.', 'itour-forms' ); ?></div>
		<?php endif; ?>

		<div class="itour-form__grid">
			<?php
			$itour_field( 'name', __( 'Full name', 'itour-forms' ), 'text', true, $itour_uid, array( 'autocomplete' => 'name' ) );
			$itour_field( 'email', __( 'Email', 'itour-forms' ), 'email', true, $itour_uid, array( 'autocomplete' => 'email' ) );
			if ( ! empty( $itour_a['showPhone'] ) ) {
				$itour_field( 'phone', __( 'Phone / WhatsApp', 'itour-forms' ), 'tel', ! empty( $itour_a['requirePhone'] ), $itour_uid, array( 'autocomplete' => 'tel' ) );
			}
			if ( ! empty( $itour_a['showSubject'] ) ) {
				$itour_field( 'subject', __( 'Subject', 'itour-forms' ), 'text', false, $itour_uid );
			}
			if ( ! empty( $itour_a['showTravelDate'] ) ) {
				$itour_field( 'travel_date', __( 'Travel date', 'itour-forms' ), 'date', false, $itour_uid, array( 'min' => wp_date( 'Y-m-d' ) ) );
			}
			if ( ! empty( $itour_a['showTravellers'] ) ) {
				$itour_field(
					'travellers',
					__( 'Travellers', 'itour-forms' ),
					'number',
					false,
					$itour_uid,
					array(
						'min'       => '1',
						'max'       => '500',
						'inputmode' => 'numeric',
					)
				);
			}
			$itour_field( 'message', __( 'Message', 'itour-forms' ), 'textarea', true, $itour_uid );
			?>
		</div>
		<p class="itour-form__actions">
			<button type="submit" class="wp-element-button itour-form__submit"><?php echo esc_html( '' !== ( $itour_a['buttonText'] ?? '' ) ? $itour_a['buttonText'] : __( 'Send message', 'itour-forms' ) ); ?></button>
		</p>
	</form>
</div>
