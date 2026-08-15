/**
 * Handles toggling the navigation menu for small screens and
 * accessibility for submenu items.
 */
( function() {
	function getParentElements( element, selector ) {
		var parentElements = [];

		while ( element.parentElement !== null ) {
			if ( ! selector || element.parentElement.matches( selector ) ) {
				parentElements.push( element.parentElement );
			}

			element = element.parentElement;
		}

		return parentElements;
	}

	function getSiblingElements( element, selector ) {
		var siblingElements = [],
			children = element.parentElement.children,
			i;

		for ( i = 0; i < children.length; i++ ) {
			if ( children[ i ] !== element && ( ! selector || children[ i ].matches( selector ) ) ) {
				siblingElements.push( children[ i ] );
			}
		}

		return siblingElements;
	}

	var nav = document.getElementById( 'site-navigation' ), button, menu;
	if ( ! nav ) {
		return;
	}

	button = nav.getElementsByTagName( 'button' )[0];
	menu   = nav.getElementsByTagName( 'ul' )[0];
	if ( ! button ) {
		return;
	}

	// Hide button if menu is missing or empty.
	if ( ! menu || ! menu.childNodes.length ) {
		button.style.display = 'none';
		return;
	}

	// Assign an ID for the default page list if no menu is set as Primary.
	if ( ! menu.id ) {
		menu.id = 'twentytwelve-page-list-menu';
	}

	button.setAttribute( 'aria-controls', menu.id );
	button.setAttribute( 'aria-expanded', 'false' );

	button.onclick = function() {
		if ( ! menu.classList.contains( 'nav-menu' ) ) {
			menu.className = 'nav-menu';
		}

		button.classList.toggle( 'toggled-on' );
		menu.classList.toggle( 'toggled-on' );
		button.setAttribute( 'aria-expanded', button.classList.contains( 'toggled-on' ) ? 'true' : 'false' );
	};

	// Better focus for hidden submenu items for accessibility.
	function toggleParentsFocusClass() {
		getParentElements( this, '.menu-item, .page_item' ).forEach( function( parentElement ) {
			parentElement.classList.toggle( 'focus' );
		} );
	}
	nav.querySelectorAll( 'a' ).forEach( function( menuLink ) {
		menuLink.addEventListener( 'focus', toggleParentsFocusClass );
		menuLink.addEventListener( 'blur', toggleParentsFocusClass );
	} );

	// Use event delegation so the handler also covers dynamically added menu items.
	if ( 'ontouchstart' in window ) {
		document.body.addEventListener( 'touchstart', function( e ) {
			var target, el;
			for ( target = e.target; target && target !== this; target = target.parentNode ) {
				if ( target.matches( '.menu-item-has-children > a, .page_item_has_children > a' ) ) {
					el = getParentElements( target, 'li' )[0];
					if ( el && ! el.classList.contains( 'focus' ) ) {
						e.preventDefault();
						el.classList.add( 'focus' );
						getSiblingElements( el, '.focus' ).forEach( function( siblingElement ) {
							siblingElement.classList.remove( 'focus' );
						} );
					}
					break;
				}
			}
		}, { passive: false } ); // Explicitly non-passive, as browsers default to passive touch listeners on body.
	}
} )();
