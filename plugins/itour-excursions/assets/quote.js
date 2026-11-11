/* iTour day trip page: the booking card's price band shows the total for the group as the form changes
   (priced on the server with the booking rules); "Starting from" when the form can't be priced yet. */
( function () {
	var form = document.querySelector( '.itl-book form.itour-tb' );
	var band = document.querySelector( '[data-itl-band]' );
	if ( ! form || ! band || ! window.itourExcQuote || ! window.fetch ) {
		return;
	}
	var label = band.querySelector( '[data-itl-band-label]' );
	var amount = band.querySelector( '[data-itl-band-amount]' );
	var start = { label: label.textContent, amount: amount.textContent };
	var timer = 0;
	var seq = 0;
	function update() {
		var data = new FormData( form );
		data.append( 'action', 'itour_exc_quote' );
		var mine = ++seq;
		fetch( itourExcQuote.ajax, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( r ) {
				if ( mine !== seq ) {
					return;
				}
				label.textContent = r && r.total ? itourExcQuote.total : start.label;
				amount.textContent = r && r.total ? r.total : start.amount;
			} )
			.catch( function () {} );
	}
	// "× 3" beside per-person add-ons: adults + children (infants are free).
	function counts() {
		var n = 0;
		[ 'adults', 'children' ].forEach( function ( k ) {
			var f = form.querySelector( '[name$="[' + k + ']"]' );
			n += f ? Math.max( 0, parseInt( f.value, 10 ) || 0 ) : 0;
		} );
		form.querySelectorAll( '[data-itour-qty]' ).forEach( function ( q ) {
			q.textContent = '× ' + Math.max( 1, n );
		} );
	}
	function later() {
		counts();
		clearTimeout( timer );
		timer = setTimeout( update, 250 );
	}
	form.addEventListener( 'change', later );
	form.addEventListener( 'input', later );
	update();
}() );
