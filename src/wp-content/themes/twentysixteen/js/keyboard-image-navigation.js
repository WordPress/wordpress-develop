/**
 * Twenty Sixteen keyboard support for image navigation.
 */

 ( function() {
	'use strict';

	document.addEventListener( 'keydown', function ( e ) {
		var url = null;
		var focusedTag;
		var el;

		if ( e.code === 'ArrowLeft' || e.keyCode === 37 ) {
			el = document.querySelector( '.nav-previous a' );
			url = el && el.getAttribute( 'href' );
		} else if ( e.code === 'ArrowRight' || e.keyCode === 39 ) {
			el = document.querySelector( '.nav-next a' );
			url = el && el.getAttribute( 'href' );
		} else {
			return;
		}

		focusedTag =
			document.activeElement && document.activeElement.tagName.toLowerCase();

		if ( url && focusedTag !== 'textarea' && focusedTag !== 'input' ) {
			window.location = url;
		}
	} );
} )();
