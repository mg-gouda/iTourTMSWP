/* WP iTourTMS front-end script (vanilla, deferred). Small-screen menu toggle. */
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
