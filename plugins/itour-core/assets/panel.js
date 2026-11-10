/* iTour product panel: vertical tabs + save-as-you-go. A field saves when it changes and loses focus
   (the native "change" event); a section saves as a whole, one request at a time. */
( function () {
	var cfg = window.itourPanel || {};
	document.querySelectorAll( '[data-itp]' ).forEach( function ( root ) {
		var post = root.dataset.post;
		var nonce = document.getElementById( '_itour_panel_nonce' );
		var status = root.querySelector( '[data-itp-status]' );
		var tabs = Array.prototype.slice.call( root.querySelectorAll( '[data-itp-tab]' ) );
		var state = {}; // section → { busy, again, row }
		var failed = {};
		var key = 'itp-tab-' + ( root.dataset.screen || ( document.body.className.match( /post-type-([\w-]+)/ ) || [ '', '' ] )[ 1 ] );

		/* Tabs */
		function show( tab, focus ) {
			tabs.forEach( function ( t ) {
				var on = t === tab;
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				t.tabIndex = on ? 0 : -1;
				root.querySelector( '#' + t.getAttribute( 'aria-controls' ) ).hidden = ! on;
			} );
			if ( focus ) {
				tab.focus();
			}
			try {
				sessionStorage.setItem( key, tab.dataset.itpTab );
			} catch ( e ) {}
		}
		tabs.forEach( function ( t, i ) {
			t.addEventListener( 'click', function () {
				show( t );
			} );
			t.addEventListener( 'keydown', function ( e ) {
				var n = { ArrowDown: i + 1, ArrowRight: i + 1, ArrowUp: i - 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[ e.key ];
				if ( undefined !== n ) {
					e.preventDefault();
					show( tabs[ ( n + tabs.length ) % tabs.length ], true );
				}
			} );
		} );
		try {
			var last = sessionStorage.getItem( key );
			var remembered = last && root.querySelector( '[data-itp-tab="' + last + '"]' );
			if ( remembered ) {
				show( remembered );
			}
		} catch ( e ) {}

		/* Status line */
		function say( kind, text, retry ) {
			status.className = 'itp-status' + ( kind ? ' is-' + kind : '' );
			status.textContent = text || '';
			if ( retry ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.textContent = cfg.retry;
				b.addEventListener( 'click', function () {
					Object.keys( failed ).forEach( save );
				} );
				status.append( ' ', b );
			}
		}

		/* Save one section */
		function save( section, row ) {
			var s = state[ section ] || ( state[ section ] = {} );
			if ( row ) {
				s.row = row;
			}
			if ( s.busy ) {
				s.again = true;
				return;
			}
			s.busy = true;
			s.again = false;
			say( 'saving', cfg.saving );
			var pane = root.querySelector( '[data-itp-section="' + section + '"]' );
			var data = new FormData();
			data.append( 'action', 'itour_panel_save' );
			data.append( 'post', post || '' );
			data.append( 'screen', root.dataset.screen || '' );
			data.append( 'section', section );
			data.append( '_itour_panel_nonce', nonce ? nonce.value : '' );
			pane.querySelectorAll( '[name^="itp["]' ).forEach( function ( el ) {
				if ( ( 'checkbox' === el.type || 'radio' === el.type ) && ! el.checked ) {
					return;
				}
				if ( ! el.closest( 'template' ) ) {
					data.append( el.name, el.value );
				}
			} );
			fetch( cfg.url, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.json().then( function ( j ) {
						if ( ! r.ok || ! j.success ) {
							throw new Error( j.data && j.data.message ? j.data.message : cfg.failed );
						}
						return j.data;
					} );
				} )
				.then( function ( d ) {
					delete failed[ section ];
					var badge = root.querySelector( '[data-itp-badge="' + section + '"]' );
					if ( badge ) {
						badge.textContent = d.badge;
						badge.hidden = '' === d.badge;
					}
					if ( s.row ) {
						var r = s.row;
						r.classList.remove( 'is-failed', 'is-saved' );
						void r.offsetWidth; // Restart the tick animation.
						r.classList.add( 'is-saved' );
						setTimeout( function () {
							r.classList.remove( 'is-saved' );
						}, 1700 );
					}
					if ( ! Object.keys( failed ).length ) {
						say( 'saved', cfg.saved );
					}
				} )
				.catch( function ( err ) {
					failed[ section ] = true;
					if ( s.row ) {
						s.row.classList.add( 'is-failed' );
					}
					say( 'failed', err.message || cfg.failed, true );
				} )
				.finally( function () {
					s.busy = false;
					if ( s.again ) {
						save( section );
					}
				} );
		}

		function sectionOf( el ) {
			var pane = el.closest( '[data-itp-section]' );
			return pane ? pane.dataset.itpSection : '';
		}

		// "change" fires when a field's value was changed and committed: on blur for text, at once for the rest.
		root.addEventListener( 'change', function ( e ) {
			if ( e.target.name && 0 === e.target.name.indexOf( 'itp[' ) ) {
				save( sectionOf( e.target ), e.target.closest( '.itp-row' ) );
			}
		} );
		// A removed table row has nothing left to blur: save its table now.
		root.addEventListener( 'itour-rows-removed', function ( e ) {
			save( sectionOf( e.target ), e.target.closest( '.itp-row' ) );
		} );
		// Enter in a single-line field commits it instead of submitting the post form.
		root.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key && 'INPUT' === e.target.tagName && 'checkbox' !== e.target.type && 'radio' !== e.target.type ) {
				e.preventDefault();
				e.target.blur();
				e.target.focus();
			}
		} );
		window.addEventListener( 'beforeunload', function ( e ) {
			var busy = Object.keys( state ).some( function ( k ) {
				return state[ k ].busy;
			} );
			if ( busy || Object.keys( failed ).length ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );
	} );
}() );
