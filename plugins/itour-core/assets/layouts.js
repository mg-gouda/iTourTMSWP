/* iTour product layouts: highlight the section link in view; hide the "Book" bar while the booking form
   (or, on immersive pages, the hero) is on screen. */
( function () {
	// Sticky parts sit below the theme's header when that header is sticky too.
	var itl = document.querySelector( '.itl' );
	var header = document.querySelector( '.site-header' );
	function offset() {
		if ( ! itl || ! header ) {
			return;
		}
		var cs = getComputedStyle( header );
		var stuck = 'sticky' === cs.position || 'fixed' === cs.position;
		itl.style.setProperty( '--itl-top', stuck ? ( parseFloat( cs.top ) || 0 ) + header.offsetHeight + 'px' : 'var(--wp-admin--admin-bar--height, 0px)' );
	}
	offset();
	window.addEventListener( 'resize', offset );
	if ( ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	var tabs = document.querySelector( '[data-itl-tabs]' );
	if ( tabs ) {
		var links = Array.prototype.slice.call( tabs.querySelectorAll( 'a' ) );
		var spy = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( e ) {
				if ( e.isIntersecting ) {
					links.forEach( function ( a ) {
						a.classList.toggle( 'is-current', a.getAttribute( 'href' ) === '#' + e.target.id );
					} );
				}
			} );
		}, { rootMargin: '-30% 0px -60% 0px' } );
		links.forEach( function ( a ) {
			var target = document.getElementById( a.getAttribute( 'href' ).slice( 1 ) );
			if ( target ) {
				spy.observe( target );
			}
		} );
	}
	var jump = document.querySelector( '[data-itl-jump]' );
	if ( jump ) {
		var watch = [ document.getElementById( 'book' ), document.querySelector( '.itl-hero' ) ].filter( Boolean );
		var visible = {};
		var seen = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( e ) {
				visible[ e.target.id || 'hero' ] = e.isIntersecting;
			} );
			jump.classList.toggle( 'is-hidden', Object.keys( visible ).some( function ( k ) {
				return visible[ k ];
			} ) );
		} );
		watch.forEach( function ( el ) {
			seen.observe( el );
		} );
	}
}() );
