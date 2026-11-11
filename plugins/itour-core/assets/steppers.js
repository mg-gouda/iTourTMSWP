/* iTour: −/+ buttons around the number fields of booking forms (people, passengers, bags). The input stays
   a normal number field, so typing and form posting work as before. */
( function () {
	function wrap( input ) {
		if ( input.closest( '.itl-step' ) ) {
			return;
		}
		var box = document.createElement( 'span' );
		box.className = 'itl-step';
		input.parentNode.insertBefore( box, input );
		var label = input.labels && input.labels[ 0 ] ? input.labels[ 0 ].textContent.trim() : '';
		function btn( d, text ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'itl-step__btn';
			b.textContent = text;
			b.setAttribute( 'aria-label', ( d < 0 ? '−1 ' : '+1 ' ) + label );
			b.addEventListener( 'click', function () {
				var min = input.min !== '' ? +input.min : -Infinity;
				var max = input.max !== '' ? +input.max : Infinity;
				var v = Math.min( max, Math.max( min, ( +input.value || 0 ) + d ) );
				input.value = v;
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				sync();
			} );
			return b;
		}
		var minus = btn( -1, '−' );
		var plus = btn( 1, '+' );
		box.appendChild( minus );
		box.appendChild( input );
		box.appendChild( plus );
		function sync() {
			minus.disabled = input.min !== '' && +input.value <= +input.min;
			plus.disabled = input.max !== '' && +input.value >= +input.max;
		}
		input.addEventListener( 'input', sync );
		sync();
	}
	document.querySelectorAll( '.itl-book input[type="number"], .itour-tr input[type="number"]' ).forEach( wrap );
}() );
