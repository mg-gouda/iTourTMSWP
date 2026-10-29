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
	// Values are grouped by the option ("store") each section saves to: the theme's or a module's.
	function storeOf( el ) {
		return el.closest( '[data-store]' ).getAttribute( 'data-store' );
	}
	function collect() {
		var stores = {};
		form.querySelectorAll( '[data-key]' ).forEach( function ( el ) {
			var s = storeOf( el );
			stores[ s ] = stores[ s ] || {};
			stores[ s ][ el.getAttribute( 'data-key' ) ] = 'switch' === el.getAttribute( 'role' ) ? ( 'true' === el.getAttribute( 'aria-checked' ) ? 1 : 0 ) : el.value;
		} );
		return stores;
	}
	function fill( stores ) {
		form.querySelectorAll( '[data-key]' ).forEach( function ( el ) {
			var values = stores[ storeOf( el ) ];
			if ( ! values || ! ( el.getAttribute( 'data-key' ) in values ) ) {
				return;
			}
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
		preview();
	}

	// Font preview: loads a Google font only after the user picks it.
	var box = root.querySelector( '[data-wpit-font-preview]' );
	var loaded = {};
	function stack( family ) {
		return family ? '"' + family.replace( /"/g, '' ) + '", system-ui, sans-serif' : 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
	}
	function load( family ) {
		if ( ! family || loaded[ family ] ) {
			return;
		}
		loaded[ family ] = true;
		var link = document.createElement( 'link' );
		link.rel = 'stylesheet';
		link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent( family ).replace( /%20/g, '+' ) + ':wght@400;700&display=swap';
		document.head.appendChild( link );
	}
	function preview() {
		if ( ! box ) {
			return;
		}
		var body = ( document.getElementById( 'wpit-font_body' ) || {} ).value || '';
		var head = ( document.getElementById( 'wpit-font_heading' ) || {} ).value || body;
		var weight = ( document.getElementById( 'wpit-heading_weight' ) || {} ).value || '700';
		var size = ( document.getElementById( 'wpit-font_size' ) || {} ).value || '16';
		load( body );
		load( head );
		box.querySelector( '.wpit-preview-heading' ).style.cssText = 'font-family:' + stack( head ) + ';font-weight:' + weight;
		box.querySelector( '.wpit-preview-body' ).style.cssText = 'font-family:' + stack( body ) + ';font-size:' + size + 'px';
	}
	[ 'wpit-font_body', 'wpit-font_heading', 'wpit-heading_weight', 'wpit-font_size' ].forEach( function ( id ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.addEventListener( 'change', preview );
			el.addEventListener( 'input', preview );
		}
	} );
	preview();

	// Searchable combobox for every <select class="wpit-input"> on the screen (WAI-ARIA combobox + listbox).
	var comboCount = 0;
	var LIMIT = 150; // Rows rendered at once; typing narrows long lists such as the 1,948 fonts.
	function combobox( select ) {
		var uid = 'wpit-combo-' + ( ++comboCount );
		var items = Array.prototype.map.call( select.options, function ( o ) {
			return { value: o.value, text: o.text, hint: o.getAttribute( 'data-hint' ) || '', key: o.text.toLowerCase() };
		} );
		var wrap = document.createElement( 'div' );
		wrap.className = 'wpit-combo';
		var trigger = document.createElement( 'button' );
		trigger.type = 'button';
		trigger.className = 'wpit-input wpit-combo-trigger';
		trigger.id = select.id + '-combo';
		trigger.setAttribute( 'aria-haspopup', 'listbox' );
		trigger.setAttribute( 'aria-expanded', 'false' );
		if ( select.getAttribute( 'aria-describedby' ) ) {
			trigger.setAttribute( 'aria-describedby', select.getAttribute( 'aria-describedby' ) );
		}
		var label = document.createElement( 'span' );
		label.className = 'wpit-combo-value';
		trigger.appendChild( label );
		var pop = document.createElement( 'div' );
		pop.className = 'wpit-combo-pop';
		pop.hidden = true;
		var search = document.createElement( 'input' );
		search.type = 'text';
		search.className = 'wpit-combo-search';
		search.setAttribute( 'role', 'combobox' );
		search.setAttribute( 'aria-autocomplete', 'list' );
		search.setAttribute( 'aria-controls', uid );
		search.setAttribute( 'aria-expanded', 'true' );
		search.setAttribute( 'autocomplete', 'off' );
		search.placeholder = t.search || 'Search…';
		var list = document.createElement( 'ul' );
		list.className = 'wpit-combo-list';
		list.id = uid;
		list.setAttribute( 'role', 'listbox' );
		var empty = document.createElement( 'p' );
		empty.className = 'wpit-combo-empty';
		empty.textContent = t.noMatch || 'No matches';
		pop.appendChild( search );
		pop.appendChild( list );
		pop.appendChild( empty );
		select.parentNode.insertBefore( wrap, select );
		wrap.appendChild( select );
		wrap.appendChild( trigger );
		wrap.appendChild( pop );
		select.classList.add( 'wpit-combo-native' );
		select.tabIndex = -1;
		select.setAttribute( 'aria-hidden', 'true' );
		var lab = document.querySelector( 'label[for="' + select.id + '"]' );
		if ( lab ) {
			lab.htmlFor = trigger.id;
		}

		var shown = [];
		var active = -1;
		function sync() {
			var it = items[ select.selectedIndex ] || items[ 0 ];
			label.textContent = it ? it.text : '';
		}
		function render() {
			var q = search.value.trim().toLowerCase();
			var starts = [];
			var contains = [];
			items.forEach( function ( it ) {
				if ( ! q || 0 === it.key.indexOf( q ) ) {
					starts.push( it );
				} else if ( -1 !== it.key.indexOf( q ) || ( it.hint && -1 !== it.hint.toLowerCase().indexOf( q ) ) ) {
					contains.push( it );
				}
			} );
			var all = starts.concat( contains );
			shown = all.slice( 0, LIMIT );
			list.textContent = '';
			shown.forEach( function ( it, i ) {
				var li = document.createElement( 'li' );
				li.id = uid + '-' + i;
				li.className = 'wpit-combo-option';
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', it.value === select.value ? 'true' : 'false' );
				var name = document.createElement( 'span' );
				name.textContent = it.text;
				li.appendChild( name );
				if ( it.hint ) {
					var hint = document.createElement( 'small' );
					hint.textContent = it.hint;
					li.appendChild( hint );
				}
				li.addEventListener( 'mousedown', function ( e ) {
					e.preventDefault();
					choose( it );
				} );
				li.addEventListener( 'mousemove', function () {
					highlight( i, false );
				} );
				list.appendChild( li );
			} );
			if ( all.length > LIMIT ) {
				var more = document.createElement( 'li' );
				more.className = 'wpit-combo-more';
				more.setAttribute( 'role', 'presentation' );
				more.textContent = ( t.keepTyping || 'Keep typing to see more' ) + ' (' + ( all.length - LIMIT ) + ')';
				list.appendChild( more );
			}
			empty.hidden = 0 !== shown.length;
			var sel = shown.findIndex( function ( it ) {
				return it.value === select.value;
			} );
			highlight( q ? 0 : Math.max( 0, sel ), true );
		}
		function highlight( i, scroll ) {
			var rows = list.querySelectorAll( '.wpit-combo-option' );
			if ( ! rows.length ) {
				active = -1;
				search.removeAttribute( 'aria-activedescendant' );
				return;
			}
			active = Math.max( 0, Math.min( i, rows.length - 1 ) );
			rows.forEach( function ( r, n ) {
				r.classList.toggle( 'is-active', n === active );
			} );
			search.setAttribute( 'aria-activedescendant', rows[ active ].id );
			if ( scroll ) {
				rows[ active ].scrollIntoView( { block: 'nearest' } );
			}
		}
		function open() {
			pop.hidden = false;
			wrap.classList.add( 'is-open' );
			trigger.setAttribute( 'aria-expanded', 'true' );
			search.value = '';
			render();
			search.focus();
		}
		function close( refocus ) {
			if ( pop.hidden ) {
				return;
			}
			pop.hidden = true;
			wrap.classList.remove( 'is-open' );
			trigger.setAttribute( 'aria-expanded', 'false' );
			if ( refocus ) {
				trigger.focus();
			}
		}
		function choose( it ) {
			select.value = it.value;
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			sync();
			close( true );
		}
		trigger.addEventListener( 'click', function () {
			if ( pop.hidden ) {
				open();
			} else {
				close( true );
			}
		} );
		trigger.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key || ( 1 === e.key.length && ! e.ctrlKey && ! e.metaKey && ! e.altKey && ' ' !== e.key ) ) {
				e.preventDefault();
				open();
				if ( 1 === e.key.length ) {
					search.value = e.key;
					render();
				}
			}
		} );
		search.addEventListener( 'input', render );
		search.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
				e.preventDefault();
				highlight( active + ( 'ArrowDown' === e.key ? 1 : -1 ), true );
			} else if ( 'Home' === e.key || 'End' === e.key ) {
				e.preventDefault();
				highlight( 'Home' === e.key ? 0 : shown.length - 1, true );
			} else if ( 'Enter' === e.key ) {
				e.preventDefault();
				if ( shown[ active ] ) {
					choose( shown[ active ] );
				}
			} else if ( 'Escape' === e.key ) {
				e.preventDefault();
				close( true );
			} else if ( 'Tab' === e.key ) {
				close( false );
			}
		} );
		document.addEventListener( 'mousedown', function ( e ) {
			if ( ! wrap.contains( e.target ) ) {
				close( false );
			}
		} );
		// Keep the button in step when code changes the value (save, reset).
		select.addEventListener( 'input', sync );
		select.addEventListener( 'change', sync );
		sync();
	}
	root.querySelectorAll( 'select.wpit-input' ).forEach( combobox );
	if ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = form.querySelector( '.wpit-btn-primary' );
			busy( btn, true );
			api( 'options', 'POST', { stores: collect() } )
				.then( function ( res ) {
					fill( res.stores );
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
					fill( res.stores );
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
