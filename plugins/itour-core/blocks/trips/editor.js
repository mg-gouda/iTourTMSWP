/* iTour Trips + Destinations block editors (React via wp.element; no build step). The editor shows the real
   front-end output through ServerSideRender. */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	var SSR = wp.serverSideRender;

	function titleAndCount( a, set, countLabel ) {
		return [
			el( c.TextControl, { key: 't', label: __( 'Heading (optional)', 'itour-core' ), value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
			el( c.RangeControl, { key: 'n', label: countLabel, min: 1, max: 24, value: a.count, onChange: function ( v ) { set( { count: v } ); } } ),
		];
	}

	wp.blocks.registerBlockType( 'itour/trips', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var kinds = [
				{ slug: 'tours', label: __( 'Tour packages', 'itour-core' ) },
				{ slug: 'excursions', label: __( 'Day trips', 'itour-core' ) },
			];
			return el(
				'div',
				be.useBlockProps(),
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Trips', 'itour-core' ) },
						el( c.ToggleControl, {
							label: __( 'Listing page', 'itour-core' ),
							help: __( 'Shows every trip of the first kind ticked below and answers the booking widget’s search. Use it on your Tours or Day trips page.', 'itour-core' ),
							checked: a.listing,
							onChange: function ( v ) { set( { listing: v } ); },
						} ),
						el( 'p', { className: 'components-base-control__label' }, __( 'Show these kinds', 'itour-core' ) ),
						kinds.map( function ( k ) {
							var on = ! a.types.length || -1 !== a.types.indexOf( k.slug );
							return el( c.CheckboxControl, {
								key: k.slug,
								label: k.label,
								checked: on,
								onChange: function ( v ) {
									var now = a.types.length ? a.types.slice() : kinds.map( function ( x ) { return x.slug; } );
									now = v ? now.concat( [ k.slug ] ) : now.filter( function ( s ) { return s !== k.slug; } );
									set( { types: now.filter( function ( s, i ) { return now.indexOf( s ) === i; } ) } );
								},
							} );
						} ),
						a.listing ? null : titleAndCount( a, set, __( 'Cards per tab', 'itour-core' ) ),
						a.listing ? null : el( c.ToggleControl, {
							label: __( 'Transfers tab (transfer search)', 'itour-core' ),
							checked: a.transfers,
							onChange: function ( v ) { set( { transfers: v } ); },
						} )
					)
				),
				el( SSR, { block: 'itour/trips', attributes: a } )
			);
		},
		save: function () {
			return null;
		},
	} );

	wp.blocks.registerBlockType( 'itour/destinations', {
		edit: function ( props ) {
			var a = props.attributes;
			return el(
				'div',
				be.useBlockProps(),
				el( be.InspectorControls, null, el( c.PanelBody, { title: __( 'Destinations', 'itour-core' ) }, titleAndCount( a, props.setAttributes, __( 'Tiles', 'itour-core' ) ) ) ),
				el( SSR, { block: 'itour/destinations', attributes: a } )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
