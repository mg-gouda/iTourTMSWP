/* iTour Contact form: send in place, show field errors, keep the plain post as fallback. */
( function () {
	document.querySelectorAll( '[data-itour-form]' ).forEach( function ( form ) {
		var status = form.querySelector( '.itour-form__status' );
		var button = form.querySelector( '.itour-form__submit' );

		function clear() {
			form.querySelectorAll( '.itour-form__error' ).forEach( function ( e ) {
				e.hidden = true;
				e.textContent = '';
			} );
			form.querySelectorAll( '[aria-invalid]' ).forEach( function ( f ) {
				f.removeAttribute( 'aria-invalid' );
				f.removeAttribute( 'aria-describedby' );
			} );
		}

		form.addEventListener( 'submit', function ( ev ) {
			if ( ! window.fetch ) {
				return; // Plain post.
			}
			ev.preventDefault();
			clear();
			button.disabled = true;
			button.classList.add( 'is-busy' );
			// getAttribute: form.action would return the hidden <input name="action">.
			fetch( form.getAttribute( 'action' ), { method: 'POST', body: new FormData( form ), headers: { Accept: 'application/json' }, credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( res ) {
					status.hidden = false;
					status.textContent = res.message;
					status.classList.toggle( 'is-error', ! res.ok );
					if ( res.ok ) {
						form.reset();
						form.querySelector( '.itour-form__grid' ).hidden = true;
						form.querySelector( '.itour-form__actions' ).hidden = true;
						status.focus && status.setAttribute( 'tabindex', '-1' );
						status.focus();
						return;
					}
					var first = null;
					Object.keys( res.errors || {} ).forEach( function ( name ) {
						var field = form.querySelector( '[name="itour_' + name + '"]' );
						var msg = field && document.getElementById( field.id + '-error' );
						if ( field && msg ) {
							msg.textContent = res.errors[ name ];
							msg.hidden = false;
							field.setAttribute( 'aria-invalid', 'true' );
							field.setAttribute( 'aria-describedby', msg.id );
							first = first || field;
						}
					} );
					( first || status ).focus();
				} )
				.catch( function () {
					form.submit(); // Network trouble: fall back to a normal post.
				} )
				.finally( function () {
					button.disabled = false;
					button.classList.remove( 'is-busy' );
				} );
		} );
	} );
}() );
