var View = wp.media.View,
	settings = wp.media.view.settings,
	$ = jQuery,
	__ = wp.i18n.__,
	LibrarySettings;

/**
 * wp.media.view.LibrarySettings
 *
 * A toolbar control opening a modal dialog with the personal options for the
 * Media Library.
 *
 * @since 7.1.0
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
	 * Whether a request is in progress.
	 *
	 * @type {boolean}
	 */
	isSaving: false,

	initialize: function() {
		// Several media frames can be attached at once, so IDs are per instance.
		this.uid = _.uniqueId( 'media-library-settings-' );
	},

	prepare: function() {
		return {
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
	 * Inserts the dialog into the frame.
	 *
	 * @return {void}
	 */
	createDialog: function() {
		var $dialog = $( wp.template( 'media-library-settings-dialog' )( this.prepare() ) );

		this.controller.$el.append( $dialog );

		this.dialog   = $dialog[0];
		this.status   = $dialog.find( '.media-library-settings__status' )[0];
		this.checkbox = $dialog.find( '.media-library-settings__checkbox' )[0];

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
	 * @return {void}
	 */
	open: function() {
		if ( ! this.dialog ) {
			this.createDialog();
		}

		if ( ! this.isSaving ) {
			this.checkbox.checked = !! settings.librarySettings.infiniteScrolling;
			this.setStatus( '' );
		}

		this.dialog.showModal();
	},

	/**
	 * Whether a `media_library_infinite_scrolling` filter callback overrides the
	 * personal option.
	 *
	 * @type {boolean}
	 */
	isFiltered: !! settings.librarySettings && !! settings.librarySettings.isFiltered,

	/**
	 * Saves the "Infinite scrolling" personal option for the current user.
	 *
	 * @return {void}
	 */
	updateInfiniteScrolling: function() {
		if ( ! this.isSaving ) {
			this.save();
		}
	},

	/**
	 * Sends the state of the checkbox to the server, then whatever it was toggled
	 * to in the meantime.
	 *
	 * @return {void}
	 */
	save: function() {
		var view = this,
			enabled = this.checkbox.checked;

		this.isSaving = true;

		this.setStatus( __( 'Saving…' ) );

		wp.ajax.post( 'set-media-library-settings', {
			_ajax_nonce: settings.librarySettings.nonce,
			infinite_scrolling: enabled ? 'true' : 'false'
		} ).done( function() {
			view.isSaving = false;

			settings.librarySettings.infiniteScrolling = enabled ? 1 : 0;

			if ( enabled !== view.checkbox.checked ) {
				view.save();
				return;
			}

			/*
			 * A filter callback takes precedence over the preference, so the Media
			 * Library keeps the filtered behavior. Otherwise the browser this toggle
			 * belongs to is updated without a reload.
			 */
			if ( ! view.isFiltered ) {
				settings.infiniteScrolling = enabled ? 1 : 0;

				view.controller.trigger( 'library:infinite-scrolling', enabled );
			}

			view.setStatus( enabled ?
				__( 'Infinite scrolling is on.' ) :
				__( 'Infinite scrolling is off.' )
			);
		} ).fail( function( response ) {
			view.isSaving = false;

			// Put the checkbox back in sync with the stored value.
			view.checkbox.checked = !! settings.librarySettings.infiniteScrolling;

			view.setStatus( ( response && response.message ) || __( 'The setting could not be saved.' ) );
		} );
	},

	/**
	 * Updates the message below the controls.
	 *
	 * @param {string} message The message to display. An empty string clears it.
	 * @return {void}
	 */
	setStatus: function( message ) {
		this.status.textContent = message;
	}
});

module.exports = LibrarySettings;
