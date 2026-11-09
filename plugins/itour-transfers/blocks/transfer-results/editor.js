/* iTour Transfer results block editor: a placeholder; the results depend on the visitor's search. */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	wp.blocks.registerBlockType( 'itour/transfer-results', {
		edit: function () {
			return el(
				'div',
				wp.blockEditor.useBlockProps( { className: 'itour-tr itour-tr--placeholder' } ),
				el( 'strong', null, __( 'Transfer results', 'itour-transfers' ) ),
				el( 'p', null, __( 'Visitors see the vehicles and prices for their search here.', 'itour-transfers' ) )
			);
		},
		save: function () {
			return null; // Rendered by render.php.
		},
	} );
}( window.wp ) );
