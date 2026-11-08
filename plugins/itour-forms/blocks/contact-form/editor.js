/* iTour Contact form block editor (React via wp.element; no build step). */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;

	wp.blocks.registerBlockType( 'itour/contact-form', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			function toggle( key, label, help ) {
				return el( c.ToggleControl, {
					label: label,
					help: help,
					checked: !! a[ key ],
					onChange: function ( v ) {
						var o = {};
						o[ key ] = v;
						set( o );
					},
				} );
			}
			function text( key, label, help, placeholder ) {
				return el( c.TextControl, {
					label: label,
					help: help,
					placeholder: placeholder,
					value: a[ key ],
					onChange: function ( v ) {
						var o = {};
						o[ key ] = v;
						set( o );
					},
				} );
			}
			return el(
				'div',
				be.useBlockProps(),
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Fields', 'itour-forms' ) },
						el( 'p', { className: 'components-base-control__help' }, __( 'Name, email and message are always included.', 'itour-forms' ) ),
						toggle( 'showPhone', __( 'Phone / WhatsApp', 'itour-forms' ) ),
						a.showPhone && toggle( 'requirePhone', __( 'Phone is required', 'itour-forms' ) ),
						toggle( 'showSubject', __( 'Subject', 'itour-forms' ) ),
						toggle( 'showTravelDate', __( 'Travel date', 'itour-forms' ) ),
						toggle( 'showTravellers', __( 'Number of travellers', 'itour-forms' ) )
					),
					el(
						c.PanelBody,
						{ title: __( 'After sending', 'itour-forms' ) },
						text( 'recipient', __( 'Send to', 'itour-forms' ), __( 'Leave empty to use the company email (iTour → Company).', 'itour-forms' ), 'reservations@example.com' ),
						text( 'buttonText', __( 'Button text', 'itour-forms' ), '', __( 'Send message', 'itour-forms' ) ),
						text( 'successMessage', __( 'Thank-you message', 'itour-forms' ), '', __( 'Thank you! We’ll reply within one working day.', 'itour-forms' ) ),
						text( 'formName', __( 'Form name', 'itour-forms' ), __( 'Shown in Enquiries, e.g. “contact” or “group-quote”.', 'itour-forms' ) )
					)
				),
				el( c.Disabled, null, el( wp.serverSideRender, { block: 'itour/contact-form', attributes: a } ) )
			);
		},
		save: function () {
			return null; // Rendered by render.php.
		},
	} );
}( window.wp ) );
