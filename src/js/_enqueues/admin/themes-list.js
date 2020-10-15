/**
 * @output wp-admin/js/themes-list.js
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
		document.querySelectorAll( '.submitdelete-theme' ).forEach( function ( item ) {
			item.addEventListener( 'click', function ( event ) {
				if ( ! confirm( item.dataset.prompt ) ) {
					event.preventDefault();
				}
			} );
		} );
	} );
} )( document );
