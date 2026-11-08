/* iTour Shield admin: tabs, dependent rows, my-IP button, confirm, file scan. */
( function () {
	const root = document.querySelector( '.itsh' );
	if ( ! root ) {
		return;
	}
	const cfg = window.itourShield || {};
	const form = document.getElementById( 'itsh-form' );
	const save = root.querySelector( '[data-save]' );
	const tabInput = document.getElementById( 'itsh-tab' );
	const noSave = [ 'overview', 'scan', 'activity' ];
	const head = root.querySelector( '.itsh-head' );
	head.style.background = getComputedStyle( document.body ).backgroundColor; // Match whatever admin colour scheme is active.
	new IntersectionObserver( ( [ e ] ) => head.classList.toggle( 'is-stuck', e.intersectionRatio < 1 ), { rootMargin: '-33px 0px 0px 0px', threshold: [ 1 ] } ).observe( head );

	function show( tab ) {
		root.querySelectorAll( '[data-panel]' ).forEach( ( p ) => ( p.hidden = p.dataset.panel !== tab ) );
		root.querySelectorAll( '.itsh-nav-item' ).forEach( ( a ) => {
			const on = a.dataset.tab === tab;
			a.classList.toggle( 'is-active', on );
			on ? a.setAttribute( 'aria-current', 'page' ) : a.removeAttribute( 'aria-current' );
		} );
		tabInput.value = tab;
		save.hidden = noSave.includes( tab );
		const url = new URL( window.location.href );
		url.searchParams.set( 'tab', tab );
		[ 'saved', 'purged', 'cleaned', 'newlogin' ].forEach( ( k ) => url.searchParams.delete( k ) );
		window.history.replaceState( null, '', url );
	}
	root.addEventListener( 'click', ( e ) => {
		const link = e.target.closest( '[data-tab]' );
		if ( link && ! e.metaKey && ! e.ctrlKey ) {
			e.preventDefault();
			show( link.dataset.tab );
			window.scrollTo( { top: 0 } );
		}
	} );
	save.hidden = noSave.includes( tabInput.value );

	// Rows that only matter when a switch is on.
	function sync() {
		root.querySelectorAll( '[data-if]' ).forEach( ( row ) => {
			const sw = root.querySelector( `[data-key="${ row.dataset.if }"]` );
			row.hidden = !! sw && ! sw.checked;
		} );
	}
	root.addEventListener( 'change', ( e ) => e.target.matches( '[data-key]' ) && sync() );
	sync();

	// Add my IP to a list.
	root.querySelectorAll( '[data-add-ip]' ).forEach( ( btn ) =>
		btn.addEventListener( 'click', () => {
			const ta = document.getElementById( btn.dataset.addIp );
			const lines = ta.value.split( /\s+/ ).filter( Boolean );
			if ( ! lines.includes( cfg.ip ) ) {
				ta.value = lines.concat( cfg.ip ).join( '\n' );
			}
			ta.focus();
		} )
	);

	// Confirm destructive links.
	root.querySelectorAll( '[data-confirm]' ).forEach( ( a ) =>
		a.addEventListener( 'click', ( e ) => {
			if ( ! window.confirm( cfg.i18n.clean ) ) {
				e.preventDefault();
			}
		} )
	);

	// Activity filter.
	const filter = root.querySelector( '[data-filter]' );
	if ( filter ) {
		filter.addEventListener( 'change', () => {
			const url = new URL( filter.dataset.filter );
			if ( filter.value ) {
				url.searchParams.set( 'type', filter.value );
			}
			window.location.href = url;
		} );
	}

	// Enter in a text field saves; never submits from panels without settings.
	form.addEventListener( 'submit', ( e ) => {
		if ( save.hidden ) {
			e.preventDefault();
		}
	} );

	// File scan, one batch per request.
	const start = document.getElementById( 'itsh-scan-start' );
	if ( start ) {
		const status = document.getElementById( 'itsh-scan-status' );
		const bar = document.getElementById( 'itsh-scan-progress' );
		const fmt = ( s, a, b ) => s.replace( '%1$s', a.toLocaleString() ).replace( '%2$s', b.toLocaleString() );
		async function step( first ) {
			const body = new FormData();
			body.append( 'action', 'itour_shield_scan' );
			body.append( '_ajax_nonce', cfg.nonce );
			if ( first ) {
				body.append( 'start', '1' );
			}
			const res = await fetch( cfg.ajax, { method: 'POST', body, credentials: 'same-origin' } );
			const json = await res.json();
			if ( ! json.success ) {
				throw new Error( 'scan' );
			}
			return json.data;
		}
		start.addEventListener( 'click', async () => {
			start.disabled = true;
			bar.hidden = false;
			bar.removeAttribute( 'value' );
			try {
				let d = await step( true );
				while ( ! d.done ) {
					bar.max = d.total;
					bar.value = d.pos;
					status.textContent = fmt( cfg.i18n.scanning, d.pos, d.total );
					d = await step( false );
				}
				const url = new URL( window.location.href );
				url.searchParams.set( 'tab', 'scan' );
				window.location.href = url;
			} catch ( err ) {
				status.textContent = cfg.i18n.failed;
				start.disabled = false;
				bar.hidden = true;
			}
		} );
	}
}() );
