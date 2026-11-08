/* iTour Booking widget block editor (React via wp.element; no build step). */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	var ALL = [
		{ slug: 'tours', label: __( 'Tour packages', 'itour-core' ) },
		{ slug: 'excursions', label: __( 'Excursions', 'itour-core' ) },
		{ slug: 'transfers', label: __( 'Transfers', 'itour-core' ) },
	];

	wp.blocks.registerBlockType( 'itour/booking-widget', {
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
						{ title: __( 'Booking widget', 'itour-core' ) },
						el( c.SelectControl, {
							label: __( 'Design', 'itour-core' ),
							value: a.model,
							options: [
								{ label: __( 'Site default (Theme Options → Design)', 'itour-core' ), value: 'auto' },
								{ label: __( 'Tabs: pill tabs above a panel', 'itour-core' ), value: 'tabs' },
								{ label: __( 'Search bar: one rounded bar', 'itour-core' ), value: 'bar' },
								{ label: __( 'Card: stacked, for heroes and sidebars', 'itour-core' ), value: 'card' },
							],
							onChange: function ( v ) {
								set( { model: v } );
							},
						} ),
						el( 'p', { className: 'components-base-control__label' }, __( 'Show these searches', 'itour-core' ) ),
						ALL.map( function ( t ) {
							var on = ! a.tabs.length || -1 !== a.tabs.indexOf( t.slug );
							return el( c.CheckboxControl, {
								key: t.slug,
								label: t.label,
								checked: on,
								onChange: function ( v ) {
									var cur = a.tabs.length ? a.tabs.slice() : ALL.map( function ( x ) {
										return x.slug;
									} );
									cur = v ? cur.concat( [ t.slug ] ) : cur.filter( function ( s ) {
										return s !== t.slug;
									} );
									set( { tabs: cur.filter( function ( s, i ) {
										return cur.indexOf( s ) === i;
									} ) } );
								},
							} );
						} ),
						el( c.TextControl, {
							label: __( 'Heading (card design)', 'itour-core' ),
							value: a.title,
							placeholder: __( 'Where to next?', 'itour-core' ),
							onChange: function ( v ) {
								set( { title: v } );
							},
						} )
					)
				),
				el( c.Disabled, null, el( wp.serverSideRender, { block: 'itour/booking-widget', attributes: a } ) )
			);
		},
		save: function () {
			return null; // Rendered by render.php.
		},
	} );
}( window.wp ) );
