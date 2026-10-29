/* Theme Options screen (vanilla JS, no build). Saves through the wp-itourtms/v1 REST routes. */
( function () {
	var cfg = window.wpItourtms || {};
	var t = cfg.i18n || {};
	var root = document.querySelector( '.wp-itourtms-admin' );
	if ( ! root ) {
		return;
	}

	function api( path, method, body ) {
		return fetch( cfg.rest + path, {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: body ? JSON.stringify( body ) : undefined,
		} ).then( function ( r ) {
			return r.json().then( function ( j ) {
				if ( ! r.ok ) {
					throw new Error( ( j && j.message ) || t.error );
				}
				return j;
			} );
		} );
	}

	function toast( msg, tone ) {
		var box = root.querySelector( '.wpit-toasts' );
		var el = document.createElement( 'div' );
		el.className = 'wpit-toast' + ( tone ? ' is-' + tone : '' );
		el.textContent = msg;
		box.appendChild( el );
		setTimeout( function () {
			el.classList.add( 'is-leaving' );
			setTimeout( function () {
				el.remove();
			}, 200 );
		}, 'warn' === tone ? 7000 : 3500 );
	}

	function busy( btn, on ) {
		if ( btn ) {
			btn.disabled = on;
			btn.classList.toggle( 'is-busy', on );
		}
	}

	// Vertical tabs: click and arrow keys (WAI-ARIA tabs pattern).
	var tabs = Array.prototype.slice.call( root.querySelectorAll( '.wpit-vtabs[role="tablist"] .wpit-vtab' ) );
	function select( tab, focus ) {
		tabs.forEach( function ( x ) {
			var on = x === tab;
			x.classList.toggle( 'is-active', on );
			x.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			x.tabIndex = on ? 0 : -1;
			document.getElementById( x.getAttribute( 'aria-controls' ) ).hidden = ! on;
		} );
		if ( focus ) {
			tab.focus();
		}
	}
	tabs.forEach( function ( tab, i ) {
		tab.addEventListener( 'click', function () {
			select( tab );
		} );
		tab.addEventListener( 'keydown', function ( e ) {
			var n = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[ e.key ];
			if ( 'Home' === e.key || 'End' === e.key ) {
				e.preventDefault();
				select( tabs[ 'Home' === e.key ? 0 : tabs.length - 1 ], true );
			} else if ( n ) {
				e.preventDefault();
				select( tabs[ ( i + n + tabs.length ) % tabs.length ], true );
			}
		} );
	} );

	// Switches.
	root.querySelectorAll( '.wpit-switch' ).forEach( function ( sw ) {
		sw.addEventListener( 'click', function () {
			sw.setAttribute( 'aria-checked', 'true' === sw.getAttribute( 'aria-checked' ) ? 'false' : 'true' );
		} );
	} );

	// Colour swatch ↔ hex field, range ↔ number field.
	root.querySelectorAll( '[data-sync]' ).forEach( function ( src ) {
		var target = document.getElementById( src.getAttribute( 'data-sync' ) );
		src.addEventListener( 'input', function () {
			target.value = src.value;
		} );
		target.addEventListener( 'input', function () {
			if ( 'color' !== src.type || /^#[0-9a-f]{6}$/i.test( target.value ) ) {
				src.value = target.value;
			}
		} );
	} );

	// Options form.
	var form = root.querySelector( '[data-wpit-options]' );
	function collect() {
		var v = {};
		form.querySelectorAll( '[data-key]' ).forEach( function ( el ) {
			v[ el.getAttribute( 'data-key' ) ] = 'switch' === el.getAttribute( 'role' ) ? ( 'true' === el.getAttribute( 'aria-checked' ) ? 1 : 0 ) : el.value;
		} );
		return v;
	}
	function fill( values ) {
		form.querySelectorAll( '[data-key]' ).forEach( function ( el ) {
			var val = values[ el.getAttribute( 'data-key' ) ];
			if ( 'switch' === el.getAttribute( 'role' ) ) {
				el.setAttribute( 'aria-checked', val ? 'true' : 'false' );
				return;
			}
			el.value = val;
			el.dispatchEvent( new Event( 'input' ) );
		} );
		form.querySelectorAll( '[data-sync]' ).forEach( function ( src ) {
			src.value = document.getElementById( src.getAttribute( 'data-sync' ) ).value;
		} );
	}
	if ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = form.querySelector( '.wpit-btn-primary' );
			busy( btn, true );
			api( 'options', 'POST', { values: collect() } )
				.then( function ( res ) {
					fill( res.values );
					toast( t.saved, 'ok' );
					( res.warnings || [] ).forEach( function ( w ) {
						toast( w, 'warn' );
					} );
				} )
				.catch( function ( err ) {
					toast( err.message, 'bad' );
				} )
				.finally( function () {
					busy( btn, false );
				} );
		} );
		form.querySelector( '[data-wpit-reset]' ).addEventListener( 'click', function ( e ) {
			if ( ! window.confirm( t.confirmReset ) ) {
				return;
			}
			busy( e.currentTarget, true );
			api( 'options', 'DELETE' )
				.then( function ( res ) {
					fill( res.values );
					toast( t.reset, 'ok' );
				} )
				.catch( function ( err ) {
					toast( err.message, 'bad' );
				} )
				.finally( function () {
					busy( e.target, false );
				} );
		} );
	}

	// License tab: act, then reload so the details show the server's answer.
	function license( req, btn, okMsg ) {
		busy( btn, true );
		req.then( function ( res ) {
			toast( res.ok || 'none' === res.status ? okMsg : t.error, res.ok || 'none' === res.status ? 'ok' : 'bad' );
			setTimeout( function () {
				window.location.reload();
			}, 900 );
		} ).catch( function ( err ) {
			toast( err.message, 'bad' );
			busy( btn, false );
		} );
	}
	var lform = root.querySelector( '[data-wpit-license-form]' );
	if ( lform ) {
		lform.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var key = lform.querySelector( 'textarea' ).value.trim();
			if ( key ) {
				license( api( 'license', 'POST', { key: key } ), lform.querySelector( '.wpit-btn-primary' ), t.activated );
			}
		} );
		var check = lform.querySelector( '[data-wpit-license-check]' );
		if ( check ) {
			check.addEventListener( 'click', function () {
				license( api( 'license?refresh=1' ), check, t.checked );
			} );
		}
		var remove = lform.querySelector( '[data-wpit-license-remove]' );
		if ( remove ) {
			remove.addEventListener( 'click', function () {
				if ( window.confirm( t.confirmRemove ) ) {
					license( api( 'license', 'DELETE' ), remove, t.removed );
				}
			} );
		}
	}
}() );
