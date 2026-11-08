/* iTour Slider block editor (React via wp.element; no build step). */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	var ServerSideRender = wp.serverSideRender;

	function pick( media ) {
		return ( media || [] ).map( function ( m ) {
			return { id: m.id, alt: m.alt || '', caption: m.caption || '' };
		} );
	}

	wp.blocks.registerBlockType( 'itour/slider', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = be.useBlockProps();
			var ids = a.images.map( function ( i ) {
				return i.id;
			} );

			var inspector = el(
				be.InspectorControls,
				null,
				el(
					c.PanelBody,
					{ title: __( 'Slider', 'itour-slider' ) },
					el( c.SelectControl, {
						label: __( 'Shape', 'itour-slider' ),
						value: a.ratio,
						options: [
							{ label: __( 'Wide (16:9)', 'itour-slider' ), value: '16/9' },
							{ label: __( 'Panorama (21:9)', 'itour-slider' ), value: '21/9' },
							{ label: __( 'Classic (4:3)', 'itour-slider' ), value: '4/3' },
							{ label: __( 'Square (1:1)', 'itour-slider' ), value: '1/1' },
						],
						onChange: function ( v ) {
							set( { ratio: v } );
						},
					} ),
					el( c.SelectControl, {
						label: __( 'Transition', 'itour-slider' ),
						value: a.effect,
						options: [
							{ label: __( 'Slide', 'itour-slider' ), value: 'slide' },
							{ label: __( 'Fade', 'itour-slider' ), value: 'fade' },
						],
						onChange: function ( v ) {
							set( { effect: v } );
						},
					} ),
					el( c.ToggleControl, {
						label: __( 'Play automatically', 'itour-slider' ),
						help: __( 'Visitors can always pause it.', 'itour-slider' ),
						checked: a.autoplay,
						onChange: function ( v ) {
							set( { autoplay: v } );
						},
					} ),
					a.autoplay &&
						el( c.RangeControl, {
							label: __( 'Seconds per slide', 'itour-slider' ),
							value: a.interval,
							min: 3,
							max: 15,
							onChange: function ( v ) {
								set( { interval: v } );
							},
						} ),
					el( c.ToggleControl, {
						label: __( 'Arrows', 'itour-slider' ),
						checked: a.arrows,
						onChange: function ( v ) {
							set( { arrows: v } );
						},
					} ),
					el( c.ToggleControl, {
						label: __( 'Dots', 'itour-slider' ),
						checked: a.dots,
						onChange: function ( v ) {
							set( { dots: v } );
						},
					} ),
					el( c.ToggleControl, {
						label: __( 'Show captions', 'itour-slider' ),
						checked: a.captions,
						onChange: function ( v ) {
							set( { captions: v } );
						},
					} ),
					el( c.TextControl, {
						label: __( 'Name for screen readers', 'itour-slider' ),
						help: __( 'E.g. “Nile cruise photos”.', 'itour-slider' ),
						value: a.label,
						onChange: function ( v ) {
							set( { label: v } );
						},
					} )
				)
			);

			if ( ! a.images.length ) {
				return el(
					'div',
					blockProps,
					inspector,
					el( be.MediaPlaceholder, {
						icon: 'images-alt2',
						labels: { title: __( 'Image slider', 'itour-slider' ), instructions: __( 'Choose or upload the photos for this slider.', 'itour-slider' ) },
						accept: 'image/*',
						allowedTypes: [ 'image' ],
						multiple: true,
						onSelect: function ( media ) {
							set( { images: pick( media ) } );
						},
					} )
				);
			}

			return el(
				'div',
				blockProps,
				inspector,
				el(
					be.BlockControls,
					null,
					el(
						be.MediaReplaceFlow,
						{
							mediaIds: ids,
							allowedTypes: [ 'image' ],
							accept: 'image/*',
							multiple: true,
							addToGallery: true,
							name: __( 'Edit images', 'itour-slider' ),
							onSelect: function ( media ) {
								set( { images: pick( media ) } );
							},
						}
					)
				),
				el( c.Disabled, null, el( ServerSideRender, { block: 'itour/slider', attributes: a } ) )
			);
		},
		save: function () {
			return null; // Rendered by render.php.
		},
	} );
}( window.wp ) );
