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
		var siblingElements = [];

		element.parentElement.children.forEach( function( sibling ) {
			if ( sibling !== element && ( ! selector || sibling.matches( selector ) ) ) {
				siblingElements.push( sibling );
			}
		} );

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

	button.onclick = function() {
		if ( ! menu.classList.contains( 'nav-menu' ) ) {
			menu.className = 'nav-menu';
		}

		button.classList.toggle( 'toggled-on' );
		menu.classList.toggle( 'toggled-on' );
	};

	// Better focus for hidden submenu items for accessibility.
	function toggleParentsFocusClass() {
		getParentElements( this, '.menu-item, .page_item' ).forEach( function( parentElement ) {
			parentElement.classList.toggle( 'focus' );
		} );
	}
	document.querySelector( '.main-navigation' ).getElementsByTagName( 'a' ).forEach( function( menuLink ) {
		menuLink.addEventListener( 'focus', toggleParentsFocusClass );
		menuLink.addEventListener( 'blur', toggleParentsFocusClass );
	} );

	if ( 'ontouchstart' in window ) {
		document.body.addEventListener( 'touchstart', function( e ) {
			for ( var target = e.target; target && target != this; target = target.parentNode ) {
				if ( target.matches( '.menu-item-has-children > a, .page_item_has_children > a' ) ) {
					var el = getParentElements( target, 'li' )[0];
					if ( el.classList.contains( 'focus' ) ) {
						e.preventDefault();
						el.classList.add( 'focus' );
						getSiblingElements( el, '.focus' ).forEach( function( siblingElement ) {
							siblingElement.classList.remove( 'focus' );
						} );
					}
					break;
				}
			}
		} );
		document.querySelectorAll( '.menu-item-has-children > a, .page_item_has_children > a' ).forEach( function( menuLink ) {
			menuLink.addEventListener( 'touchstart', function( e ) {
				var el = getParentElements( this, 'li' )[0];

				if ( el.classList.contains( 'focus' ) ) {
					e.preventDefault();
					el.classList.add( 'focus' );
					getSiblingElements( el, '.focus' ).forEach( function( siblingElement ) {
						siblingElement.classList.remove( 'focus' );
					} );
				}
			} );
		} );
	}
} )();
