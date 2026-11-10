/* iTour repeating rows editor: Add row clones the <template>, Remove deletes the row. */
( function () {
	var next = Date.now();
	document.addEventListener( 'click', function ( e ) {
		var add = e.target.closest( '[data-itour-rows-add]' );
		var del = e.target.closest( '[data-itour-rows-remove]' );
		if ( add ) {
			var table = add.closest( '[data-itour-rows]' );
			var row = table.querySelector( 'template' ).content.cloneNode( true );
			var i = String( next++ );
			row.querySelectorAll( '[name]' ).forEach( function ( el ) {
				el.name = el.name.replace( '__i__', i );
			} );
			table.querySelector( 'tbody' ).appendChild( row );
			var first = table.querySelector( 'tbody tr:last-child input, tbody tr:last-child select' );
			if ( first ) {
				first.focus();
			}
		} else if ( del ) {
			var row = del.closest( 'tr' );
			var nextFocus = row.nextElementSibling || row.previousElementSibling;
			var addBtn = row.closest( '[data-itour-rows]' ).querySelector( '[data-itour-rows-add]' );
			row.remove();
			( nextFocus ? nextFocus.querySelector( 'input, select' ) : addBtn ).focus();
		}
	} );
}() );
