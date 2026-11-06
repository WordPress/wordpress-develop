/**
 * Makes submenus of the main navigation menu usable on touch devices.
 *
 * Submenus are only shown on hover, which touch devices emulate
 * inconsistently. The first tap on a menu item with a submenu opens
 * the submenu, and a second tap follows the link.
 */
( function() {
	var nav = document.getElementById( 'access' );

	if ( ! nav || ! ( 'ontouchstart' in window ) ) {
		return;
	}

	/**
	 * Closes all open submenus, except the ones containing the given menu item.
	 *
	 * @since Twenty Eleven 5.3
	 *
	 * @param {Element} [item] Menu item whose submenus stay open.
	 */
	function closeSubmenus( item ) {
		nav.querySelectorAll( '.focus' ).forEach( function( openItem ) {
			if ( ! item || ! openItem.contains( item ) ) {
				openItem.classList.remove( 'focus' );
			}
		} );
	}

	/**
	 * Opens the submenu of a tapped menu item, unless it is already open.
	 *
	 * @since Twenty Eleven 5.3
	 *
	 * @param {TouchEvent} event Touch event within the menu.
	 */
	function onTouchStart( event ) {
		var link = event.target.closest(
			'.menu-item-has-children > a, .page_item_has_children > a'
		);

		if ( ! link || link.parentNode.classList.contains( 'focus' ) ) {
			return;
		}

		// Open the submenu instead of following the link.
		event.preventDefault();
		closeSubmenus( link.parentNode );
		link.parentNode.classList.add( 'focus' );
	}

	// Listen on the container, as the Customizer may re-render the menu.
	nav.addEventListener( 'touchstart', onTouchStart );

	// Close open submenus when tapping outside the menu.
	document.addEventListener( 'touchstart', function( event ) {
		if ( ! nav.contains( event.target ) ) {
			closeSubmenus();
		}
	} );

	// Close submenus when the page is restored from the back/forward cache.
	window.addEventListener( 'pageshow', function( event ) {
		if ( event.persisted ) {
			closeSubmenus();
		}
	} );
} )();
