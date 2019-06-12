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
	 */
	getTabbables: function() {
		// Skip the file input added by Plupload.
		return this.$( ':tabbable' ).not( '.moxie-shim input[type="file"]' );
	},

	/**
	 * Moves focus to the modal dialog.
	 */
	focus: function() {
		this.$( '.media-modal' ).focus();
	},

	/**
	 * @param {Object} event
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
	}

});

module.exports = FocusManager;
