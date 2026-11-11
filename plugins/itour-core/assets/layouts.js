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

/* Photo slider (native scroll-snap: swipe works without script) and the "Booking conditions" dialog. */
( function () {
	document.querySelectorAll( '[data-itl-slider]' ).forEach( function ( box ) {
		var track = box.querySelector( '.itl-slider__track' );
		var slides = track.children;
		var thumbs = box.nextElementSibling && box.nextElementSibling.classList.contains( 'itl-thumbs' ) ? box.nextElementSibling.querySelectorAll( '[data-itl-thumb]' ) : [];
		var prev = box.querySelector( '[data-itl-prev]' );
		var next = box.querySelector( '[data-itl-next]' );
		var at = box.querySelector( '[data-itl-at]' );
		var rtl = 'rtl' === getComputedStyle( track ).direction;
		function index() {
			return Math.round( Math.abs( track.scrollLeft ) / track.clientWidth );
		}
		function go( i ) {
			i = Math.max( 0, Math.min( slides.length - 1, i ) );
			track.scrollTo( { left: ( rtl ? -1 : 1 ) * i * track.clientWidth } );
		}
		function sync() {
			var i = index();
			if ( at ) {
				at.textContent = i + 1;
			}
			if ( prev ) {
				prev.disabled = 0 === i;
				next.disabled = i === slides.length - 1;
			}
			Array.prototype.forEach.call( thumbs, function ( t, n ) {
				t.setAttribute( 'aria-current', n === i ? 'true' : 'false' );
				if ( n === i ) {
					t.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
				}
			} );
		}
		track.addEventListener( 'scroll', function () {
			window.requestAnimationFrame( sync );
		}, { passive: true } );
		if ( prev ) {
			prev.addEventListener( 'click', function () { go( index() - 1 ); } );
			next.addEventListener( 'click', function () { go( index() + 1 ); } );
		}
		track.addEventListener( 'keydown', function ( e ) {
			var d = { ArrowRight: 1, ArrowLeft: -1 }[ e.key ];
			if ( d ) {
				e.preventDefault();
				go( index() + ( rtl ? -d : d ) );
			}
		} );
		Array.prototype.forEach.call( thumbs, function ( t ) {
			t.addEventListener( 'click', function () { go( +t.getAttribute( 'data-itl-thumb' ) ); } );
		} );
		sync();
	} );
	var dlg = document.querySelector( '.itl-cond' );
	if ( dlg && 'function' === typeof dlg.showModal ) {
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-itl-cond]' ) ) {
				dlg.showModal();
			} else if ( e.target.closest( '[data-itl-cond-close]' ) || e.target === dlg ) {
				dlg.close();
			}
		} );
	}
}() );
