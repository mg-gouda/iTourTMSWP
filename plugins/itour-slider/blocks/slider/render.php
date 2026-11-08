<?php
/**
 * Slider markup (server-rendered). WAI-ARIA carousel pattern; view.js adds behaviour.
 *
 * @package ITour\Slider
 *
 * @var array<string,mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$itour_slides = array_values(
	array_filter(
		(array) ( $attributes['images'] ?? array() ),
		static fn( $i ) => ! empty( $i['id'] ) && wp_attachment_is_image( (int) $i['id'] )
	)
);
if ( ! $itour_slides ) {
	return;
}
$itour_count = count( $itour_slides );
$itour_uid   = wp_unique_id( 'itour-slider-' );
$itour_ratio = in_array( $attributes['ratio'] ?? '', array( '16/9', '21/9', '4/3', '1/1' ), true ) ? $attributes['ratio'] : '16/9';
$itour_label = '' !== ( $attributes['label'] ?? '' ) ? $attributes['label'] : __( 'Image slider', 'itour-slider' );
$itour_auto  = ! empty( $attributes['autoplay'] ) && $itour_count > 1;
$itour_wrap  = get_block_wrapper_attributes(
	array(
		'class'                => 'itour-slider is-' . ( 'fade' === ( $attributes['effect'] ?? '' ) ? 'fade' : 'slide' ),
		'id'                   => $itour_uid,
		'aria-roledescription' => __( 'carousel', 'itour-slider' ),
		'aria-label'           => $itour_label,
		'data-autoplay'        => $itour_auto ? (string) max( 3, (int) ( $attributes['interval'] ?? 5 ) ) : '',
		'style'                => '--itour-slider-ratio:' . $itour_ratio,
	)
);
?>
<section <?php echo $itour_wrap; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes. ?>>
	<div class="itour-slider__viewport">
		<div class="itour-slider__track" id="<?php echo esc_attr( $itour_uid ); ?>-track" aria-live="<?php echo $itour_auto ? 'off' : 'polite'; ?>">
			<?php foreach ( $itour_slides as $itour_i => $itour_slide ) : ?>
				<?php $itour_caption = ! empty( $attributes['captions'] ) ? wp_get_attachment_caption( (int) $itour_slide['id'] ) : ''; ?>
				<figure class="itour-slider__slide" role="group" aria-roledescription="<?php esc_attr_e( 'slide', 'itour-slider' ); ?>"
					<?php /* translators: 1: slide number, 2: total slides. */ ?>
					aria-label="<?php echo esc_attr( sprintf( __( '%1$d of %2$d', 'itour-slider' ), $itour_i + 1, $itour_count ) ); ?>"<?php echo $itour_i ? ' aria-hidden="true" inert' : ''; ?>>
					<?php
					echo wp_get_attachment_image(
						(int) $itour_slide['id'],
						'full',
						false,
						array(
							'class'   => 'itour-slider__img',
							'loading' => $itour_i ? 'lazy' : 'eager',
							'sizes'   => '(min-width: 1200px) 1200px, 100vw',
						)
					);
					?>
					<?php if ( $itour_caption ) : ?>
						<figcaption class="itour-slider__caption"><?php echo esc_html( $itour_caption ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
	<?php if ( $itour_count > 1 ) : ?>
		<div class="itour-slider__controls">
			<?php if ( $itour_auto ) : ?>
				<button type="button" class="itour-slider__pause" aria-pressed="false" data-label-pause="<?php esc_attr_e( 'Pause slider', 'itour-slider' ); ?>" data-label-play="<?php esc_attr_e( 'Play slider', 'itour-slider' ); ?>" aria-label="<?php esc_attr_e( 'Pause slider', 'itour-slider' ); ?>"><span aria-hidden="true"></span></button>
			<?php endif; ?>
			<?php if ( ! empty( $attributes['arrows'] ) ) : ?>
				<button type="button" class="itour-slider__arrow is-prev" aria-controls="<?php echo esc_attr( $itour_uid ); ?>-track" aria-label="<?php esc_attr_e( 'Previous slide', 'itour-slider' ); ?>"><span aria-hidden="true"></span></button>
				<button type="button" class="itour-slider__arrow is-next" aria-controls="<?php echo esc_attr( $itour_uid ); ?>-track" aria-label="<?php esc_attr_e( 'Next slide', 'itour-slider' ); ?>"><span aria-hidden="true"></span></button>
			<?php endif; ?>
			<?php if ( ! empty( $attributes['dots'] ) ) : ?>
				<div class="itour-slider__dots" role="group" aria-label="<?php esc_attr_e( 'Choose slide', 'itour-slider' ); ?>">
					<?php for ( $itour_d = 0; $itour_d < $itour_count; $itour_d++ ) : ?>
						<?php /* translators: %d: slide number. */ ?>
						<button type="button" class="itour-slider__dot" aria-label="<?php echo esc_attr( sprintf( __( 'Slide %d', 'itour-slider' ), $itour_d + 1 ) ); ?>"<?php echo 0 === $itour_d ? ' aria-current="true"' : ''; ?>></button>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
