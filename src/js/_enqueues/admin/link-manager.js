/**
 * @output wp-admin/js/link-manager.js
 */
/**
 * Link manager event handler
 *
 * @since 5.6.0
 *
 * @param {Object} document  The document object.
 *
 * @return {void}
 */
 /* global confirm */
( function ( document ) {
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.submitdelete-link' ).forEach( function ( item ) {
			item.addEventListener( 'click', function ( event ) {
				if ( ! confirm( item.dataset.prompt ) ) {
					event.preventDefault();
				}
			} );
		} );
	} );
} )( document );
