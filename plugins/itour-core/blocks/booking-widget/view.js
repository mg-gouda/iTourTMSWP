/* iTour Booking widget: switch between product searches (tabs, segmented switch or the bar's picker). */
( function () {
	document.querySelectorAll( '[data-itour-bw]' ).forEach( function ( root ) {
		var tabs = Array.prototype.slice.call( root.querySelectorAll( '.itour-bw__tab' ) );
		var forms = Array.prototype.slice.call( root.querySelectorAll( '.itour-bw__form' ) );
		var isBar = root.classList.contains( 'itour-bw--bar' );
		var sw = root.querySelector( '.itour-bw__switch' );

		function show( slugOrIndex, focus ) {
			var i = 'number' === typeof slugOrIndex ? slugOrIndex : forms.findIndex( function ( f ) {
				return f.id.slice( -slugOrIndex.length - 1 ) === '-' + slugOrIndex;
			} );
			if ( i < 0 ) {
				return;
			}
			forms.forEach( function ( f, n ) {
				f.hidden = n !== i;
			} );
			tabs.forEach( function ( t, n ) {
				t.setAttribute( 'aria-selected', n === i ? 'true' : 'false' );
				t.tabIndex = n === i ? 0 : -1;
			} );
			if ( isBar ) {
				var kind = forms[ i ].querySelector( '.itour-bw__kind' );
				if ( kind && focus ) {
					kind.focus();
				}
			} else if ( focus && tabs[ i ] ) {
				tabs[ i ].focus();
			}
		}

		if ( forms.length < 2 ) {
			return;
		}
		if ( sw && ! isBar ) {
			sw.hidden = false; // Tabs and card use the switch; the bar uses its built-in picker.
		}
		tabs.forEach( function ( t, i ) {
			t.addEventListener( 'click', function () {
				show( i );
			} );
			t.addEventListener( 'keydown', function ( e ) {
				var d = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[ e.key ];
				if ( document.dir === 'rtl' && ( 'ArrowRight' === e.key || 'ArrowLeft' === e.key ) ) {
					d = -d;
				}
				if ( d ) {
					e.preventDefault();
					show( ( i + d + tabs.length ) % tabs.length, true );
				}
			} );
		} );
		root.querySelectorAll( '.itour-bw__kind' ).forEach( function ( sel ) {
			sel.addEventListener( 'change', function () {
				show( sel.value, true );
				// Keep every bar's picker in step.
				root.querySelectorAll( '.itour-bw__kind' ).forEach( function ( other ) {
					other.value = sel.value;
				} );
			} );
		} );
		show( 0 );
	} );
}() );
