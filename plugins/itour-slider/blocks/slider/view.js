/* iTour Slider front end (vanilla, no jQuery): arrows, dots, swipe, keys, autoplay with pause. */
( function () {
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function init( root ) {
		var track = root.querySelector( '.itour-slider__track' );
		var slides = Array.prototype.slice.call( root.querySelectorAll( '.itour-slider__slide' ) );
		var dots = Array.prototype.slice.call( root.querySelectorAll( '.itour-slider__dot' ) );
		var pause = root.querySelector( '.itour-slider__pause' );
		var seconds = parseInt( root.getAttribute( 'data-autoplay' ), 10 ) || 0;
		var fade = root.classList.contains( 'is-fade' );
		var index = 0;
		var timer = null;
		var stopped = reduce; // Respect reduced motion: no autoplay.

		function go( n ) {
			index = ( n + slides.length ) % slides.length;
			if ( fade ) {
				slides.forEach( function ( s, i ) {
					s.classList.toggle( 'is-current', i === index );
				} );
			} else {
				track.style.transform = 'translateX(' + ( document.dir === 'rtl' ? index * 100 : -index * 100 ) + '%)';
			}
			slides.forEach( function ( s, i ) {
				var on = i === index;
				s.toggleAttribute( 'inert', ! on );
				s.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
			} );
			dots.forEach( function ( d, i ) {
				if ( i === index ) {
					d.setAttribute( 'aria-current', 'true' );
				} else {
					d.removeAttribute( 'aria-current' );
				}
			} );
		}
		function play() {
			if ( ! seconds || stopped || timer ) {
				return;
			}
			timer = setInterval( function () {
				go( index + 1 );
			}, seconds * 1000 );
		}
		function hold() {
			clearInterval( timer );
			timer = null;
		}

		root.classList.add( 'is-ready' );
		go( 0 );
		root.querySelectorAll( '.itour-slider__arrow' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				go( index + ( b.classList.contains( 'is-next' ) ? 1 : -1 ) );
			} );
		} );
		dots.forEach( function ( d, i ) {
			d.addEventListener( 'click', function () {
				go( i );
			} );
		} );
		root.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowRight' === e.key || 'ArrowLeft' === e.key ) {
				e.preventDefault();
				go( index + ( ( 'ArrowRight' === e.key ) !== ( document.dir === 'rtl' ) ? 1 : -1 ) );
			}
		} );

		// Swipe (pointer events cover touch, pen and mouse drag).
		var startX = null;
		track.addEventListener( 'pointerdown', function ( e ) {
			startX = e.clientX;
		} );
		track.addEventListener( 'pointerup', function ( e ) {
			if ( null === startX ) {
				return;
			}
			var dx = e.clientX - startX;
			startX = null;
			if ( Math.abs( dx ) > 40 ) {
				go( index + ( ( dx < 0 ) !== ( document.dir === 'rtl' ) ? 1 : -1 ) );
			}
		} );

		if ( pause ) {
			pause.addEventListener( 'click', function () {
				stopped = ! stopped;
				pause.setAttribute( 'aria-pressed', stopped ? 'true' : 'false' );
				pause.setAttribute( 'aria-label', pause.getAttribute( stopped ? 'data-label-play' : 'data-label-pause' ) );
				if ( stopped ) {
					hold();
				} else {
					play();
				}
			} );
			if ( reduce ) {
				pause.setAttribute( 'aria-pressed', 'true' );
				pause.setAttribute( 'aria-label', pause.getAttribute( 'data-label-play' ) );
			}
		}
		// Autoplay pauses while the visitor hovers or focuses inside the slider.
		root.addEventListener( 'mouseenter', hold );
		root.addEventListener( 'mouseleave', play );
		root.addEventListener( 'focusin', hold );
		root.addEventListener( 'focusout', play );
		play();
	}

	document.querySelectorAll( '.itour-slider' ).forEach( init );
}() );
