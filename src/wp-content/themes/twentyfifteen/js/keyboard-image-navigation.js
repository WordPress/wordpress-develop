/**
 * Twenty Fifteen keyboard support for image navigation.
 */

( function() {
	document.addEventListener( 'keydown', function( e ) {
		var previousEl = document.querySelector( '.nav-previous a' );
		var nextEl = document.querySelector( '.nav-next a' );
		var url = false;

		// Left arrow key code.
		if ( e.which === 37 && previousEl ) {
			url = previousEl.getAttribute( 'href' );

		// Right arrow key code.
		} else if ( e.which === 39 && nextEl ) {
			url = nextEl.getAttribute( 'href' );
		}

		if ( url && document.activeElement && ! document.activeElement.matches( 'input, textarea' ) ) {
			window.location = url;
		}
	} );
} )();
