<?php
/**
 * Booking widget markup: one GET form per product tab; view.js switches between them.
 * Without JavaScript every form is shown, so search still works.
 *
 * @package ITour\Core
 *
 * @var array<string,mixed> $attributes Block attributes.
 */

use ITour\Core\BookingWidget;

defined( 'ABSPATH' ) || exit;

$itour_tabs = BookingWidget::tabs();
if ( ! empty( $attributes['tabs'] ) ) {
	$itour_tabs = array_intersect_key( $itour_tabs, array_flip( (array) $attributes['tabs'] ) );
}
if ( ! $itour_tabs ) {
	if ( current_user_can( 'activate_plugins' ) ) {
		echo '<p class="itour-bw__empty">' . esc_html__( 'Booking widget: activate iTour Tour Packages, Excursions or Transfers to add their searches here.', 'itour-core' ) . '</p>';
	}
	return;
}
$itour_model = BookingWidget::model( (string) ( $attributes['model'] ?? 'auto' ) );
$itour_uid   = wp_unique_id( 'itour-bw-' );
$itour_first = (string) array_key_first( $itour_tabs );
$itour_wrap  = get_block_wrapper_attributes( array( 'class' => 'itour-bw itour-bw--' . $itour_model ) );

$itour_field = static function ( array $f, string $form_id ): void {
	$id   = $form_id . '-' . sanitize_key( (string) $f['name'] );
	$type = in_array( $f['type'] ?? 'text', array( 'text', 'date', 'time', 'number', 'select' ), true ) ? $f['type'] : 'text';
	echo '<div class="itour-bw__field itour-bw__field--' . esc_attr( $type ) . '">';
	echo BookingWidget::icon( (string) ( $f['icon'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG paths, escaped inside.
	echo '<label for="' . esc_attr( $id ) . '">' . esc_html( (string) $f['label'] ) . '</label>';
	if ( 'select' === $type ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( (string) $f['name'] ) . '">';
		foreach ( (array) ( $f['options'] ?? array() ) as $v => $label ) {
			echo '<option value="' . esc_attr( (string) $v ) . '">' . esc_html( (string) $label ) . '</option>';
		}
		echo '</select>';
	} else {
		if ( ! empty( $f['list'] ) ) { // Suggestions (e.g. hotels from the Location Tree); typing anything else is still allowed.
			echo '<datalist id="' . esc_attr( $id ) . '-list">';
			foreach ( (array) $f['list'] as $itour_opt ) {
				echo '<option value="' . esc_attr( (string) $itour_opt ) . '"></option>';
			}
			echo '</datalist>';
		}
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s%8$s>',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( (string) $f['name'] ),
			esc_attr( isset( $_GET[ $f['name'] ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $f['name'] ] ) ) : (string) ( $f['default'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- keeps the visitor's search on the results page.
			isset( $f['placeholder'] ) ? ' placeholder="' . esc_attr( (string) $f['placeholder'] ) . '"' : '',
			'date' === $type ? ' min="' . esc_attr( wp_date( 'Y-m-d' ) ) . '"' : '',
			'number' === $type ? ' min="' . (int) ( $f['min'] ?? 1 ) . '" max="' . (int) ( $f['max'] ?? 99 ) . '" inputmode="numeric"' : '',
			empty( $f['list'] ) ? '' : ' list="' . esc_attr( $id . '-list' ) . '" autocomplete="off"'
		);
	}
	echo '</div>';
};
?>
<div <?php echo $itour_wrap; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?> data-itour-bw>
	<?php if ( 'card' === $itour_model ) : ?>
		<p class="itour-bw__title"><?php echo esc_html( '' !== ( $attributes['title'] ?? '' ) ? $attributes['title'] : __( 'Where to next?', 'itour-core' ) ); ?></p>
	<?php endif; ?>

	<?php if ( count( $itour_tabs ) > 1 ) : ?>
		<div class="itour-bw__switch" role="tablist" aria-label="<?php esc_attr_e( 'What are you looking for?', 'itour-core' ); ?>" hidden>
			<?php foreach ( $itour_tabs as $itour_slug => $itour_tab ) : ?>
				<button type="button" role="tab" class="itour-bw__tab" id="<?php echo esc_attr( $itour_uid . '-tab-' . $itour_slug ); ?>" aria-controls="<?php echo esc_attr( $itour_uid . '-' . $itour_slug ); ?>" aria-selected="<?php echo $itour_slug === $itour_first ? 'true' : 'false'; ?>" tabindex="<?php echo $itour_slug === $itour_first ? '0' : '-1'; ?>"><?php echo esc_html( (string) $itour_tab['label'] ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php foreach ( $itour_tabs as $itour_slug => $itour_tab ) : ?>
		<?php $itour_form_id = $itour_uid . '-' . $itour_slug; ?>
		<form class="itour-bw__form" id="<?php echo esc_attr( $itour_form_id ); ?>" method="get" action="<?php echo esc_url( (string) $itour_tab['action'] ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $itour_uid . '-tab-' . $itour_slug ); ?>" aria-label="<?php echo esc_attr( (string) $itour_tab['label'] ); ?>">
			<?php if ( 'bar' === $itour_model && count( $itour_tabs ) > 1 ) : ?>
				<div class="itour-bw__field itour-bw__field--kind">
					<label for="<?php echo esc_attr( $itour_form_id . '-kind' ); ?>"><?php esc_html_e( 'Looking for', 'itour-core' ); ?></label>
					<select id="<?php echo esc_attr( $itour_form_id . '-kind' ); ?>" class="itour-bw__kind">
						<?php foreach ( $itour_tabs as $itour_k => $itour_t ) : ?>
							<option value="<?php echo esc_attr( $itour_k ); ?>"<?php selected( $itour_k, $itour_slug ); ?>><?php echo esc_html( (string) $itour_t['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>
			<div class="itour-bw__fields">
				<?php
				foreach ( (array) $itour_tab['fields'] as $itour_f ) {
					$itour_field( (array) $itour_f, $itour_form_id );
				}
				?>
			</div>
			<button type="submit" class="itour-bw__submit wp-element-button">
				<?php echo BookingWidget::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
				<span><?php echo esc_html( (string) ( $itour_tab['button'] ?? __( 'Search', 'itour-core' ) ) ); ?></span>
			</button>
		</form>
	<?php endforeach; ?>
</div>
