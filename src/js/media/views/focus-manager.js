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
		'keydown': 'constrainTabbing'
	},

	/**
	 * Gets all the tabbable elements.
	 *
	 * @since 5.3.0
	 *
	 * @returns {object} A jQuery collection of tabbable elements.
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
	 * @returns {void}
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
	 * @returns {void}
	 */
	constrainTabbing: function( event ) {
		var tabbables;

		// Look for the tab key.
		if ( 9 !== event.keyCode ) {
			return;
		}

		tabbables = this.getTabbables();

		// Keep tab focus within media modal while it's open
		if ( tabbables.last()[0] === event.target && ! event.shiftKey ) {
			tabbables.first().focus();
			return false;
		} else if ( tabbables.first()[0] === event.target && event.shiftKey ) {
			tabbables.last().focus();
			return false;
		}
	},

	/**
	 * Hides from assistive technologies all the body children except the
	 * provided element and other elements that should not be hidden.
	 *
	 * The reason why we use `aria-hidden` is that `aria-modal="true"` is buggy
	 * in Safari 11.1 and support is spotty in other browsers. In the future we
	 * should consider to remove this helper function and only use `aria-modal="true"`.
	 *
	 * @since 5.2.3
	 *
	 * @param {object} visibleElement The jQuery object representing the element that should not be hidden.
	 *
	 * @returns {void}
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
	 * Makes visible again to assistive technologies all body children
	 * previously hidden and stored in this.ariaHiddenElements.
	 *
	 * @since 5.2.3
	 *
	 * @returns {void}
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
	 * @returns {boolean} Whether the element should not be hidden from assistive technologies.
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
	ariaHiddenElements: []
});

module.exports = FocusManager;
