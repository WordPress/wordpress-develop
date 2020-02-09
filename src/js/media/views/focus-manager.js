var $ = jQuery;

/**
 * wp.media.view.FocusManager
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
var FocusManager = wp.media.View.extend(/** @lends wp.media.view.FocusManager.prototype */{

	events: {
		'keydown': 'focusManagementMode'
	},

	/**
	 * Initializes the Focus Manager.
	 *
	 * @param {object} options The Focus Manager options.
	 *
	 * @since 5.3.0
	 *
	 * @return {void}
	 */
	initialize: function( options ) {
		this.mode                    = options.mode || 'constrainTabbing';
		this.tabsAutomaticActivation = options.tabsAutomaticActivation || false;
	},

 	/**
	 * Determines which focus management mode to use.
	 *
	 * @since 5.3.0
	 *
	 * @param {object} event jQuery event object.
	 *
	 * @return {void}
	 */
	focusManagementMode: function( event ) {
		if ( this.mode === 'constrainTabbing' ) {
			this.constrainTabbing( event );
		}

		if ( this.mode === 'tabsNavigation' ) {
			this.tabsNavigation( event );
		}
	},

	/**
	 * Gets all the tabbable elements.
	 *
	 * @since 5.3.0
	 *
	 * @return {object} A jQuery collection of tabbable elements.
	 */
	getTabbables: function() {
		// Skip the file input added by Plupload.
		return this.$( ':tabbable' ).not( '.moxie-shim input[type="file"]' );
	},

	/**
	 * Moves focus to the modal dialog.
	 *
	 * @since 3.5.0
	 *
	 * @return {void}
	 */
	focus: function() {
		this.$( '.media-modal' ).focus();
	},

	/**
	 * Constrains navigation with the Tab key within the media view element.
	 *
	 * @since 4.0.0
	 *
	 * @param {Object} event A keydown jQuery event.
	 *
	 * @return {void}
	 */
	constrainTabbing: function( event ) {
		var tabbables;

		// Look for the tab key.
		if ( 9 !== event.keyCode ) {
			return;
		}

		tabbables = this.getTabbables();

		// Keep tab focus within media modal while it's open.
		if ( tabbables.last()[0] === event.target && ! event.shiftKey ) {
			tabbables.first().focus();
			return false;
		} else if ( tabbables.first()[0] === event.target && event.shiftKey ) {
			tabbables.last().focus();
			return false;
		}
	},

	/**
	 * Hides from assistive technologies all the body children.
	 *
	 * Sets an `aria-hidden="true"` attribute on all the body children except
	 * the provided element and other elements that should not be hidden.
	 *
	 * The reason why we use `aria-hidden` is that `aria-modal="true"` is buggy
	 * in Safari 11.1 and support is spotty in other browsers. Also, `aria-modal="true"`
	 * prevents the `wp.a11y.speak()` ARIA live regions to work as they're outside
	 * of the modal dialog and get hidden from assistive technologies.
	 *
	 * @since 5.2.3
	 *
	 * @param {object} visibleElement The jQuery object representing the element that should not be hidden.
	 *
	 * @return {void}
	 */
	setAriaHiddenOnBodyChildren: function( visibleElement ) {
		var bodyChildren,
			self = this;

		if ( this.isBodyAriaHidden ) {
			return;
		}

		// Get all the body children.
		bodyChildren = document.body.children;

		// Loop through the body children and hide the ones that should be hidden.
		_.each( bodyChildren, function( element ) {
			// Don't hide the modal element.
			if ( element === visibleElement[0] ) {
				return;
			}

			// Determine the body children to hide.
			if ( self.elementShouldBeHidden( element ) ) {
				element.setAttribute( 'aria-hidden', 'true' );
				// Store the hidden elements.
				self.ariaHiddenElements.push( element );
			}
		} );

		this.isBodyAriaHidden = true;
	},

	/**
	 * Unhides from assistive technologies all the body children.
	 *
	 * Makes visible again to assistive technologies all the body children
	 * previously hidden and stored in this.ariaHiddenElements.
	 *
	 * @since 5.2.3
	 *
	 * @return {void}
	 */
	removeAriaHiddenFromBodyChildren: function() {
		_.each( this.ariaHiddenElements, function( element ) {
			element.removeAttribute( 'aria-hidden' );
		} );

		this.ariaHiddenElements = [];
		this.isBodyAriaHidden   = false;
	},

	/**
	 * Determines if the passed element should not be hidden from assistive technologies.
	 *
	 * @since 5.2.3
	 *
	 * @param {object} element The DOM element that should be checked.
	 *
	 * @return {boolean} Whether the element should not be hidden from assistive technologies.
	 */
	elementShouldBeHidden: function( element ) {
		var role = element.getAttribute( 'role' ),
			liveRegionsRoles = [ 'alert', 'status', 'log', 'marquee', 'timer' ];

		/*
		 * Don't hide scripts, elements that already have `aria-hidden`, and
		 * ARIA live regions.
		 */
		return ! (
			element.tagName === 'SCRIPT' ||
			element.hasAttribute( 'aria-hidden' ) ||
			element.hasAttribute( 'aria-live' ) ||
			liveRegionsRoles.indexOf( role ) !== -1
		);
	},

	/**
	 * Whether the body children are hidden from assistive technologies.
	 *
	 * @since 5.2.3
	 */
	isBodyAriaHidden: false,

	/**
	 * Stores an array of DOM elements that should be hidden from assistive
	 * technologies, for example when the media modal dialog opens.
	 *
	 * @since 5.2.3
	 */
	ariaHiddenElements: [],

	/**
	 * Holds the jQuery collection of ARIA tabs.
	 *
	 * @since 5.3.0
	 */
	tabs: $(),

	/**
	 * Sets up tabs in an ARIA tabbed interface.
	 *
	 * @since 5.3.0
	 *
	 * @param {object} event jQuery event object.
	 *
	 * @return {void}
	 */
	setupAriaTabs: function() {
		this.tabs = this.$( '[role="tab"]' );

		// Set up initial attributes.
		this.tabs.attr( {
			'aria-selected': 'false',
			tabIndex: '-1'
		} );

		// Set up attributes on the initially active tab.
		this.tabs.filter( '.active' )
			.removeAttr( 'tabindex' )
			.attr( 'aria-selected', 'true' );
	},

	/**
	 * Enables arrows navigation within the ARIA tabbed interface.
	 *
	 * @since 5.3.0
	 *
	 * @param {object} event jQuery event object.
	 *
	 * @return {void}
	 */
	tabsNavigation: function( event ) {
		var orientation = 'horizontal',
			keys = [ 32, 35, 36, 37, 38, 39, 40 ];

		// Return if not Spacebar, End, Home, or Arrow keys.
		if ( keys.indexOf( event.which ) === -1 ) {
			return;
		}

		// Determine navigation direction.
		if ( this.$el.attr( 'aria-orientation' ) === 'vertical' ) {
			orientation = 'vertical';
		}

		// Make Up and Down arrow keys do nothing with horizontal tabs.
		if ( orientation === 'horizontal' && [ 38, 40 ].indexOf( event.which ) !== -1 ) {
			return;
		}

		// Make Left and Right arrow keys do nothing with vertical tabs.
		if ( orientation === 'vertical' && [ 37, 39 ].indexOf( event.which ) !== -1 ) {
			return;
		}

		this.switchTabs( event, this.tabs );
	},

	/**
	 * Switches tabs in the ARIA tabbed interface.
	 *
	 * @since 5.3.0
	 *
	 * @param {object} event jQuery event object.
	 *
	 * @return {void}
	 */
	switchTabs: function( event ) {
		var key   = event.which,
			index = this.tabs.index( $( event.target ) ),
			newIndex;

		switch ( key ) {
			// Space bar: Activate current targeted tab.
			case 32: {
				this.activateTab( this.tabs[ index ] );
				break;
			}
			// End key: Activate last tab.
			case 35: {
				event.preventDefault();
				this.activateTab( this.tabs[ this.tabs.length - 1 ] );
				break;
			}
			// Home key: Activate first tab.
			case 36: {
				event.preventDefault();
				this.activateTab( this.tabs[ 0 ] );
				break;
			}
			// Left and up keys: Activate previous tab.
			case 37:
			case 38: {
				event.preventDefault();
				newIndex = ( index - 1 ) < 0 ? this.tabs.length - 1 : index - 1;
				this.activateTab( this.tabs[ newIndex ] );
				break;
			}
			// Right and down keys: Activate next tab.
			case 39:
			case 40: {
				event.preventDefault();
				newIndex = ( index + 1 ) === this.tabs.length ? 0 : index + 1;
				this.activateTab( this.tabs[ newIndex ] );
				break;
			}
		}
	},

	/**
	 * Sets a single tab to be focusable and semantically selected.
	 *
	 * @since 5.3.0
	 *
	 * @param {object} tab The tab DOM element.
	 *
	 * @return {void}
	 */
	activateTab: function( tab ) {
		if ( ! tab ) {
			return;
		}

		// The tab is a DOM element: no need for jQuery methods.
		tab.focus();

		// Handle automatic activation.
		if ( this.tabsAutomaticActivation ) {
			tab.removeAttribute( 'tabindex' );
			tab.setAttribute( 'aria-selected', 'true' );
			tab.click();

			return;
		}

		// Handle manual activation.
		$( tab ).on( 'click', function() {
			tab.removeAttribute( 'tabindex' );
			tab.setAttribute( 'aria-selected', 'true' );
		} );
 	}
});

module.exports = FocusManager;
