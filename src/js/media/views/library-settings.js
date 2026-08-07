var View = wp.media.View,
	settings = wp.media.view.settings,
	$ = jQuery,
	__ = wp.i18n.__,
	LibrarySettings;

/**
 * wp.media.view.LibrarySettings
 *
 * A toolbar control opening a modal dialog with the personal options for the
 * Media Library. Each toggle is saved over Ajax, so there is no submit button.
 *
 * @since 7.2.0
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
LibrarySettings = View.extend(/** @lends wp.media.view.LibrarySettings.prototype */{
	tagName:   'button',
	className: 'button button-compact media-library-settings__toggle',
	template:  wp.template( 'media-library-settings-toggle' ),

	attributes: {
		type: 'button',
		'aria-haspopup': 'dialog'
	},

	events: {
		'click': 'open'
	},

	/**
	 * Identifies the most recent request, so a slow response cannot overwrite the
	 * outcome of a later toggle.
	 *
	 * @type {number}
	 */
	requestId: 0,

	initialize: function() {
		// Several media frames can be attached at once, so IDs are per instance.
		this.uid = _.uniqueId( 'media-library-settings-' );
	},

	prepare: function() {
		return {
			infiniteScrolling:   !! settings.librarySettings.infiniteScrolling,
			titleId:             this.uid + '-title',
			infiniteScrollingId: this.uid + '-infinite-scrolling'
		};
	},

	/**
	 * Removes the dialog along with the view.
	 *
	 * @return {wp.media.view.LibrarySettings} Returns itself to allow chaining.
	 */
	dispose: function() {
		if ( this.dialog ) {
			$( this.dialog ).remove();
		}

		return View.prototype.dispose.apply( this, arguments );
	},

	/**
	 * Inserts the dialog next to the toggle.
	 *
	 * Done on the first open, when the toggle is known to be in the document. A
	 * closed dialog is `display: none`, so it does not take part in the layout.
	 *
	 * @return {void}
	 */
	createDialog: function() {
		var $dialog = $( wp.template( 'media-library-settings-dialog' )( this.prepare() ) );

		this.$el.after( $dialog );

		this.dialog = $dialog[0];
		this.status = $dialog.find( '.media-library-settings__status' )[0];

		$dialog.on( 'change', '.media-library-settings__checkbox', _.bind( this.updateInfiniteScrolling, this ) );

		/*
		 * In the media modal, `wp.media.view.Modal` closes on Escape and
		 * `wp.media.view.FocusManager` constrains Tab. Neither must run while the
		 * dialog is open: it handles both itself, and the rest of the modal is inert.
		 */
		$dialog.on( 'keydown', function( event ) {
			event.stopPropagation();
		} );
	},

	/**
	 * Opens the dialog.
	 *
	 * `showModal()` moves it to the top layer, out of the toolbar's clipping, and
	 * brings the focus trap, Escape handling and focus restore a modal needs.
	 *
	 * @return {void}
	 */
	open: function() {
		if ( ! this.dialog ) {
			this.createDialog();
		}

		this.setStatus( '' );
		this.dialog.showModal();
	},

	/**
	 * Saves the "Infinite scrolling" personal option for the current user.
	 *
	 * @param {Event} event The change event of the checkbox.
	 * @return {void}
	 */
	updateInfiniteScrolling: function( event ) {
		var view = this,
			checkbox = event.target,
			enabled = checkbox.checked,
			requestId = ++this.requestId;

		this.setStatus( __( 'Saving…' ) );

		wp.ajax.post( 'set-media-library-settings', {
			_ajax_nonce: settings.librarySettings.nonce,
			infinite_scrolling: enabled ? 'true' : 'false'
		} ).done( function() {
			if ( requestId !== view.requestId ) {
				return;
			}

			settings.librarySettings.infiniteScrolling = enabled ? 1 : 0;
			settings.infiniteScrolling = enabled ? 1 : 0;

			// Applied to the browser this toggle belongs to, without a reload.
			view.controller.trigger( 'library:infinite-scrolling', enabled );

			view.setStatus( enabled ?
				__( 'Infinite scrolling is on.' ) :
				__( 'Infinite scrolling is off.' )
			);
		} ).fail( function( response ) {
			if ( requestId !== view.requestId ) {
				return;
			}

			// Put the checkbox back in sync with the stored value.
			checkbox.checked = ! enabled;

			view.setStatus( ( response && response.message ) || __( 'The setting could not be saved.' ) );
		} );
	},

	/**
	 * Updates the message below the controls.
	 *
	 * It sits in a `role="status"` region, so it is shown and announced at once.
	 *
	 * @param {string} message The message to display. An empty string clears it.
	 * @return {void}
	 */
	setStatus: function( message ) {
		this.status.textContent = message;
	}
});

module.exports = LibrarySettings;
