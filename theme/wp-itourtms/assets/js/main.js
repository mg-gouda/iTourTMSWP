/* WP iTourTMS front-end script (vanilla, deferred). Transparent header on scroll; small-screen menu toggle. */
( function () {
	var header = document.querySelector( '.site-header--transparent' );
	if ( header ) {
		// The hero slides up under the header by exactly the header's height.
		var measure = function () {
			document.documentElement.style.setProperty( '--wp-itourtms-header-h', header.offsetHeight + 'px' );
		};
		measure();
		window.addEventListener( 'resize', measure );
		var onScroll = function () {
			header.classList.toggle( 'is-scrolled', window.scrollY > 40 );
		};
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		onScroll();
	}
}() );
( function () {
	var nav = document.querySelector( '.site-nav' );
	var toggle = nav && nav.querySelector( '.site-nav__toggle' );
	if ( ! toggle ) {
		return;
	}
	// The button stays hidden without JavaScript, so the menu is always reachable.
	toggle.hidden = false;
	nav.classList.add( 'has-toggle' );

	function setOpen( open ) {
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		nav.classList.toggle( 'is-open', open );
	}
	toggle.addEventListener( 'click', function () {
		setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && nav.classList.contains( 'is-open' ) ) {
			setOpen( false );
			toggle.focus();
		}
	} );
}() );
