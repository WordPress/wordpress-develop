/* global screenReaderText */
/**
 * Theme functions file.
 *
 * Contains handlers for navigation and widget area.
 */
 ( function () {
	'use strict';

	var masthead, menuToggle, siteNavigation, socialNavigation, siteHeaderMenu, resizeTimer;

	function matches( el, sel ) {
		if ( Element.prototype.matches ) {
			return el.matches( sel );
		}

		if ( Element.prototype.msMatchesSelector ) {
			return el.msMatchesSelector( sel );
		}
	}

	// Based on https://developer.mozilla.org/en-US/docs/Web/API/Element/closest#Polyfill
	function closest( el, sel ) {
		if ( el.closest ) {
			return el.closest( sel );
		}

		var current = el;

		do {
			if ( matches( el, sel ) ) {
				return current;
			}
			current = current.parentElement || current.parentNode;
		} while ( current !== null && current.nodeType === 1 );

		return null;
	}

	function forEachNode( parent, selector, fn ) {
		var nodes = parent.querySelectorAll( selector );
		for ( var i = 0; i < nodes.length; i++ ) {
			fn( nodes[ i ] );
		}
	}

	function initMainNavigation( container ) {
		forEachNode( container, '.menu-item-has-children > a', function ( el ) {
			// Add dropdown toggle that displays child menu items.
			var dropdownToggle = document.createElement( 'button' );
			dropdownToggle.className = 'dropdown-toggle';
			dropdownToggle.setAttribute( 'aria-expanded', 'false' );

			var span = document.createElement( 'span' );
			span.className = 'screen-reader-text';
			span.textContent = screenReaderText.expand;

			dropdownToggle.appendChild( span );

			el.parentNode.insertBefore( dropdownToggle, el.nextSibling );
		} );

		// Toggle buttons and submenu items with active children menu items.
		forEachNode( container, '.current-menu-ancestor > button', function ( el ) {
			el.classList.add( 'toggled-on' );
		} );
		forEachNode( container, '.current-menu-ancestor > .sub-menu', function ( el ) {
			el.classList.add( 'toggled-on' );
		} );

		// Add menu items with submenus to aria-haspopup="true".
		forEachNode( container, '.menu-item-has-children', function ( el ) {
			el.setAttribute( 'aria-haspopup', 'true' );
		} );

		forEachNode( container, '.dropdown-toggle', function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				var screenReaderSpan = el.querySelector( '.screen-reader-text' );

				e.preventDefault();
				el.classList.toggle( 'toggled-on' );
				var next = el.nextSibling;
				if ( next && matches( next, '.children, .sub-menu' ) ) {
					next.classList.toggle( 'toggled-on' );
				}

				el.setAttribute(
					'aria-expanded',
					el.getAttribute( 'aria-expanded' ) === 'false' ? 'true' : 'false'
				);
				screenReaderSpan.textContent =
					screenReaderSpan.textContent === screenReaderText.expand ? screenReaderText.collapse: screenReaderText.expand;
			} );
		} );
	}
	initMainNavigation( document.querySelector( '.main-navigation' ) );

	masthead = document.querySelector( '#masthead' );
	menuToggle = masthead && masthead.querySelector( '#menu-toggle' );
	siteHeaderMenu = masthead && masthead.querySelector( '#site-header-menu' );
	siteNavigation = masthead && masthead.querySelector( '#site-navigation' );
	socialNavigation = masthead && masthead.querySelector( '#social-navigation' );

	// Enable menuToggle.
	( function () {
		// Return early if menuToggle is missing.
		if ( ! menuToggle ) {
			return;
		}

		var controls = [ menuToggle, siteNavigation, socialNavigation ].filter( Boolean );

		// Add an initial values for the attribute.
		controls.forEach( function ( el ) {
			el.setAttribute( 'aria-expanded', 'false' );
		} );

		menuToggle.addEventListener( 'click', function () {
			menuToggle.classList.toggle( 'toggled-on' );
			siteHeaderMenu.classList.toggle( 'toggled-on' );

			var newVal = menuToggle.getAttribute( 'aria-expanded' ) === 'false' ? 'true' : 'false';
			controls.forEach( function ( el ) {
				el.setAttribute( 'aria-expanded', newVal );
			} );
		} );
	} )();

	// Fix sub-menus for touch devices and better focus for hidden submenu items for accessibility.
	( function () {
		if ( ! siteNavigation || siteNavigation.children.length === 0 ) {
			return;
		}

		// Init `focus` class to allow submenu access on tablets.
		function initFocusClassTouchScreen() {
			document.body.addEventListener( 'touchstart', function ( e ) {
				if ( ! closest( e.target, '.main-navigation li' ) ) {
					forEachNode( document, '.main-navigation li', function ( el ) {
						el.classList.remove( 'focus' );
					} );
				}
			} );
			forEachNode( siteNavigation, '.menu-item-has-children > a', function ( link ) {
				link.addEventListener( 'touchstart', function ( e ) {
					if ( window.innerWidth >= 910 ) {
						var el = link.parentElement;

						if ( matches( el, 'li' ) && ! el.classList.contains( 'focus' ) ) {
							e.preventDefault();
							el.classList.toggle( 'focus' );
							var children = el.parentElement.children;
							for ( var i = 0; i < children.length; i++ ) {
								if ( children[ i ] !== el ) {
									children[ i ].classList.remove( 'focus' );
								}
							}
						}
					}
				} );
			} );
		}

		function toggleFocus( el ) {
			var item = closest( el, '.menu-item' );
			if ( item ) {
				item.classList.toggle( 'focus' );
			}
		}

		if ( 'ontouchstart' in window ) {
			initFocusClassTouchScreen();
		}

		forEachNode( siteNavigation, 'a', function ( link ) {
			link.addEventListener( 'focus', toggleFocus( link ) );
			link.addEventListener( 'blur', toggleFocus( link ) );
		} );
	} )();

	// Add the default ARIA attributes for the menu toggle and the navigations.
	function onResizeARIA() {
		if ( window.innerWidth < 910 ) {
			if ( menuToggle && menuToggle.classList.contains( 'toggled-on' ) ) {
				menuToggle.setAttribute( 'aria-expanded', 'true' );
			} else {
				menuToggle.setAttribute( 'aria-expanded', 'false' );
			}

			if ( siteHeaderMenu && siteHeaderMenu.classList.contains( 'toggled-on' ) ) {
				siteNavigation && siteNavigation.setAttribute( 'aria-expanded', 'true' );
				socialNavigation && socialNavigation.setAttribute( 'aria-expanded', 'true' );
			} else {
				siteNavigation && siteNavigation.setAttribute( 'aria-expanded', 'false' );
				socialNavigation && socialNavigation.setAttribute( 'aria-expanded', 'false' );
			}

			menuToggle && menuToggle.setAttribute( 'aria-controls', 'site-navigation social-navigation' );
		} else {
			menuToggle && menuToggle.removeAttribute( 'aria-expanded' );
			siteNavigation && siteNavigation.removeAttribute( 'aria-expanded' );
			socialNavigation && socialNavigation.removeAttribute( 'aria-expanded' );
			menuToggle && menuToggle.removeAttribute( 'aria-controls' );
		}
	}

	// Add 'below-entry-meta' class to elements.
	function belowEntryMetaClass( selector ) {
		if (
			document.body.classList.contains( 'page' ) ||
			document.body.classList.contains( 'search' ) ||
			document.body.classList.contains( 'single-attachment' ) ||
			document.body.classList.contains( 'error404' )
		) {
			return;
		}

		forEachNode( document, '.entry-content', function ( content ) {
			forEachNode( content, selector, function ( element ) {
				var elementPosTop = element.getBoundingClientRect().top;
				var article = closest( element, 'article' );
				var entryFooter = article && article.querySelector( '.entry-footer' );
				var entryFooterPosBottom =
					entryFooter ? entryFooter.getBoundingClientRect().top + entryFooter.offsetHeight + 28	: null;
				var caption = closest( element, 'figure' );
				var next = element.nextSibling;
				var figcaption = next && matches( next, 'figcaption' ) ? next : null;
				var newImg;

				// Add 'below-entry-meta' to elements below the entry meta.
				if ( entryFooterPosBottom !== null && elementPosTop > entryFooterPosBottom ) {
					// Check if full-size images and captions are larger than or equal to 840px.
					if ( selector === 'img.size-full' || selector === '.wp-block-image img' ) {
						// Create an image to find native image width of resized images (i.e. max-width: 100%).
						newImg = new Image();
						newImg.src = element.getAttribute( 'src' );
						newImg.onload = function () {
							if ( newImg.width >= 840 ) {
								// Check if an image in an image block has a width attribute; if its value is less than 840, return.
								if (
									selector === '.wp-block-image img' &&
									element.hasAttribute( 'width' ) &&
									element.getAttribute( 'width' ) < 840
								) {
									return;
								}

								element.classList.add( 'below-entry-meta' );

								if ( caption && caption.classList.contains( 'wp-caption' ) ) {
									caption.classList.add( 'below-entry-meta' );
									caption.removeAttribute( 'style' );
								}

								if ( figcaption ) {
									figcaption.classList.add( 'below-entry-meta' );
								}
							}
						};
					} else {
						element.classList.add( 'below-entry-meta' );
					}
				} else {
					element.classList.remove( 'below-entry-meta' );
					caption && caption.classList.remove( 'below-entry-meta' );
				}
			} );
		} );
	}

	function init() {
		window.addEventListener( 'load', onResizeARIA );
		window.addEventListener( 'resize', function () {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( function () {
				belowEntryMetaClass( 'img.size-full' );
				belowEntryMetaClass( 'blockquote.alignleft, blockquote.alignright' );
				belowEntryMetaClass( '.wp-block-image img' );
			}, 300 );
			onResizeARIA();
		} );

		belowEntryMetaClass( 'img.size-full' );
		belowEntryMetaClass( 'blockquote.alignleft, blockquote.alignright' );
		belowEntryMetaClass( '.wp-block-image img' );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
