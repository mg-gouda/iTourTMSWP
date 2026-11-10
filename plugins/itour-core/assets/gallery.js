/* iTour product gallery picker: WordPress media library, multiple images, order kept. Fires "change"
   on the hidden input so the product panel saves it. */
( function () {
	document.addEventListener( 'click', function ( e ) {
		var pick = e.target.closest( '[data-itour-gallery-pick]' );
		var clear = e.target.closest( '[data-itour-gallery-clear]' );
		var box = ( pick || clear ) && ( pick || clear ).closest( '[data-itour-gallery]' );
		if ( ! box ) {
			return;
		}
		var input = box.querySelector( 'input' );
		var list = box.querySelector( '[data-itour-gallery-list]' );
		var changed = function () {
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};
		if ( clear ) {
			input.value = '';
			list.replaceChildren();
			changed();
			return;
		}
		var frame = window.wp.media( {
			title: pick.dataset.title,
			button: { text: pick.dataset.button },
			library: { type: 'image' },
			multiple: 'add',
		} );
		frame.on( 'open', function () {
			var sel = frame.state().get( 'selection' );
			input.value.split( ',' ).filter( Boolean ).forEach( function ( id ) {
				sel.add( window.wp.media.attachment( id ) );
			} );
		} );
		frame.on( 'select', function () {
			var items = frame.state().get( 'selection' ).toJSON();
			input.value = items.map( function ( a ) {
				return a.id;
			} ).join( ',' );
			list.replaceChildren();
			items.forEach( function ( a ) {
				var li = document.createElement( 'li' );
				var img = document.createElement( 'img' );
				img.src = ( a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail : a ).url;
				img.alt = '';
				li.appendChild( img );
				list.appendChild( li );
			} );
			changed();
		} );
		frame.open();
	} );
}() );
