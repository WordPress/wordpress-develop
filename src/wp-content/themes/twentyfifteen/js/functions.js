/* global screenReaderText */
/**
 * Theme functions file.
 *
 * Contains handlers for navigation and widget area.
 */

( function() {
	function domReady( callback ) {
		if ( document.readyState === 'complete' || document.readyState === 'interactive' ) {
			callback();
		} else {
			document.addEventListener( 'DOMContentLoaded', callback );
		}
	}

	function toggleClass( element, className ) {
		if ( -1 !== element.className.indexOf( className ) ) {
			element.className = element.className.replace( ' ' + className, '' );
		} else {
			element.className += ' ' + className;
		}
	}

	var sidebar, resizeTimer, secondary, button;

	function initMainNavigation( container ) {
		if ( ! container ) {
			return;
		}

		// Add dropdown toggle that display child menu items.
		Array.from( container.querySelectorAll( '.menu-item-has-children > a' ) ).forEach( function( menuLink ) {
			var dropdownToggle = document.createElement( 'button' );
			dropdownToggle.className = 'dropdown-toggle';
			dropdownToggle.setAttribute( 'aria-expanded', 'false' );
			dropdownToggle.innerHTML = screenReaderText.expand;
			menuLink.parentElement.insertBefore( dropdownToggle, menuLink.nextElementSibling );
		} );

		// Toggle buttons and submenu items with active children menu items.
		Array.from( container.querySelectorAll( '.current-menu-ancestor > button, .current-menu-ancestor > .sub-menu' ) ).forEach( function( subItem ) {
			subItem.className += 'toggle-on';
		} );

		Array.from( container.querySelectorAll( '.dropdown-toggle' ) ).forEach( function( dropdownToggle ) {
			dropdownToggle.addEventListener( 'click', function( e ) {
				e.preventDefault();
				toggleClass( this, 'toggle-on' );
				if ( this.nextElementSibling.tagName === 'UL' || this.nextElementSibling.tagName === 'OL' ) {
					toggleClass( this.nextElementSibling, 'toggled-on' );
				}
				this.setAttribute( 'aria-expanded', this.getAttribute( 'aria-expanded' ) === 'false' ? 'true' : 'false' );
				this.innerHTML = this.innerHTML === screenReaderText.expand ? screenReaderText.collapse : screenReaderText.expand;
			} );
		} );
	}
	initMainNavigation( document.querySelector( '.main-navigation' ) );

	// Re-initialize the main navigation when it is updated, persisting any existing submenu expanded states.
	// This is only relevant for the Customize preview where jQuery is expected to be loaded.
	if ( window.jQuery ) {
		var $ = window.jQuery;
		$( document ).on( 'customize-preview-menu-refreshed', function( e, params ) {
			if ( 'primary' === params.wpNavMenuArgs.theme_location ) {
				// Extract the raw DOM element from the jQuery wrapper here.
				initMainNavigation( params.newContainer[0] );

				// Re-sync expanded states from oldContainer.
				params.oldContainer.find( '.dropdown-toggle.toggle-on' ).each(function() {
					var containerId = $( this ).parent().prop( 'id' );
					$( params.newContainer ).find( '#' + containerId + ' > .dropdown-toggle' ).triggerHandler( 'click' );
				});
			}
		});
	}

	secondary = document.getElementById( 'secondary' );
	button = document.querySelector( '.site-branding .secondary-toggle' );

	// Enable menu toggle for small screens.
	( function() {
		var menu, widgets, social;
		if ( ! secondary || ! button ) {
			return;
		}

		// Hide button if there are no widgets and the menus are missing or empty.
		menu    = secondary.querySelector( '.nav-menu' );
		widgets = secondary.querySelector( '#widget-area' );
		social  = secondary.querySelector( '#social-navigation' );
		if ( ! widgets && ! social && ( ! menu || ! menu.children.length ) ) {
			button.style.display = 'none';
			return;
		}

		button.addEventListener( 'click', function() {
			toggleClass( secondary, 'toggled-on' );
			window.dispatchEvent( new Event( 'resize' ) );
			toggleClass( this, 'toggled-on' );
			if ( -1 !== this.className.indexOf( 'toggled-on' ) && -1 !== secondary.className.indexOf( 'toggled-on' ) ) {
				this.setAttribute( 'aria-expanded', 'true' );
				secondary.setAttribute( 'aria-expanded', 'true' );
			} else {
				this.setAttribute( 'aria-expanded', 'false' );
				secondary.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	} )();

	/**
	 * Add or remove ARIA attributes.
	 *
	 * Determine the size of the window and add the default ARIA attributes
	 * for the menu toggle if it's visible.
	 *
	 * @since Twenty Fifteen 1.1
	 */
	function onResizeARIA() {
		if ( 955 > document.documentElement.clientWidth ) {
			button.setAttribute( 'aria-expanded', 'false' );
			secondary.setAttribute( 'aria-expanded', 'false' );
			button.setAttribute( 'aria-controls', 'secondary' );
		} else {
			button.removeAttribute( 'aria-expanded' );
			secondary.removeAttribute( 'aria-expanded' );
			button.removeAttribute( 'aria-controls' );
		}
	}

	// Sidebar scrolling.
	function resizeAndScroll() {
		var windowPos = document.documentElement.scrollTop,
			windowHeight = document.documentElement.clientHeight,
			sidebarHeight = sidebar.clientHeight,
			bodyHeight = document.body.clientHeight;

		if( 955 < document.documentElement.clientWidth && bodyHeight > sidebarHeight && ( windowPos + windowHeight ) >= sidebarHeight ) {
			sidebar.style.position = 'fixed';
			sidebar.style.bottom = sidebarHeight > windowHeight ? 0 : 'auto';
		} else {
			sidebar.style.position = 'relative';
		}
	}

	domReady( function() {
		sidebar = document.getElementById( 'sidebar' );

		window.addEventListener( 'scroll', resizeAndScroll );
		window.addEventListener( 'load', onResizeARIA );
		window.addEventListener( 'resize', function() {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( resizeAndScroll, 500 );
			onResizeARIA();
		} );
		Array.from( sidebar.getElementsByTagName( 'button' ) ).forEach( function( sidebarButton ) {
			sidebarButton.addEventListener( 'click', resizeAndScroll );
			sidebarButton.addEventListener( 'keydown', resizeAndScroll );
		} );

		for ( var i = 0; i < 6; i++ ) {
			setTimeout( resizeAndScroll, 100 * i );
		}
	} );

} )();
