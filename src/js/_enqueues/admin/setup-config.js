/**
 * @output wp-admin/js/setup-config.js
 */
/**
 * Vanilla JavaScript 'Go back' event handler
 *
 * @since 5.6.0
 *
 * @param {Object} document  The document object.
 * @param {Object} window    The window object.
 *
 * @return {void}
 */
( function ( document, window ) {
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.go-back' ).forEach( function ( item ) {
			item.addEventListener( 'click', function ( event ) {
				window.history.go( -1 );
				event.preventDefault();
			} );
		} );
	} );
} )( document, window );
