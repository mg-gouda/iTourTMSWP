/* iTour Transfer routes block editor (no build step): heading, subtitle, number of cards; live preview. */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	wp.blocks.registerBlockType( 'itour/transfer-routes', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			return el(
				'div',
				be.useBlockProps(),
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Transfer routes', 'itour-transfers' ) },
						el( c.TextControl, { label: __( 'Heading (optional)', 'itour-transfers' ), value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
						el( c.TextControl, { label: __( 'Subtitle (optional)', 'itour-transfers' ), value: a.subtitle, onChange: function ( v ) { set( { subtitle: v } ); } } ),
						el( c.RangeControl, { label: __( 'Routes', 'itour-transfers' ), min: 1, max: 24, value: a.count, onChange: function ( v ) { set( { count: v } ); } } )
					)
				),
				el( wp.serverSideRender, { block: 'itour/transfer-routes', attributes: a } )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
