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

		Array.from( element.parentElement.children ).forEach( function( sibling ) {
			if ( sibling !== element && ( ! selector || sibling.matches( selector ) ) ) {
				siblingElements.push( sibling );
			}
		} );

		return siblingElements;
	}

	function toggleClass( element, className ) {
		if ( -1 !== element.className.indexOf( className ) ) {
			element.className = element.className.replace( ' ' + className, '' );
		} else {
			element.className += ' ' + className;
		}
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
		if ( -1 === menu.className.indexOf( 'nav-menu' ) ) {
			menu.className = 'nav-menu';
		}

		toggleClass( button, 'toggled-on' );
		toggleClass( menu, 'toggled-on' );
	};

	// Better focus for hidden submenu items for accessibility.
	function toggleParentsFocusClass() {
		getParentElements( this, '.menu-item, .page_item' ).forEach( function( parentElement ) {
			toggleClass( parentElement, 'focus' );
		} );
	}
	Array.from( document.querySelector( '.main-navigation' ).getElementsByTagName( 'a' ) ).forEach( function( menuLink ) {
		menuLink.addEventListener( 'focus', toggleParentsFocusClass );
		menuLink.addEventListener( 'blur', toggleParentsFocusClass );
	} );

	if ( 'ontouchstart' in window ) {
		Array.from( document.querySelectorAll( '.menu-item-has-children > a, .page_item_has_children > a' ) ).forEach( function( menuLink ) {
			menuLink.addEventListener( 'touchstart', function( e ) {
				var el = getParentElements( this, 'li' )[0];

				if ( -1 === el.className.indexOf( 'focus' ) ) {
					e.preventDefault();
					el.className += ' focus';
					getSiblingElements( el, '.focus' ).forEach( function( siblingElement ) {
						siblingElement.className.replace( ' focus', '' );
					} );
				}
			} );
		} );
	}
} )();
