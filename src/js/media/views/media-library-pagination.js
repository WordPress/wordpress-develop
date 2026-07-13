var View = wp.media.View,
	$ = jQuery,
	l10n = wp.media.view.l10n,
	settings = wp.media.view.settings,
	MediaLibraryPagination;

/**
 * wp.media.view.MediaLibraryPagination
 *
 * Allows users to choose how additional Media Library items are loaded.
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
MediaLibraryPagination = View.extend(/** @lends wp.media.view.MediaLibraryPagination.prototype */{
	tagName: 'select',
	className: 'attachment-filters media-library-pagination',
	id: 'media-library-pagination',

	events: {
		change: 'change'
	},

	initialize: function() {
		this.$el.append(
			$( '<option>' ).val( '1' ).text( l10n.infiniteScrollingEnabled ),
			$( '<option>' ).val( '0' ).text( l10n.infiniteScrollingDisabled )
		);

		this.listenTo( wp.media.events, 'infinite-scrolling:change', this.update );
		this.update( settings.infiniteScrolling );
	},

	/**
	 * Saves the user preference and updates all active attachment browsers.
	 *
	 * @return {void}
	 */
	change: function() {
		var view = this,
			previousValue = !! settings.infiniteScrolling,
			infiniteScrolling = '1' === this.el.value;

		this.$el.prop( 'disabled', true );

		wp.ajax.post( 'save-media-library-infinite-scrolling', {
			infinite_scrolling: infiniteScrolling ? 'true' : 'false',
			nonce: settings.nonce.saveInfiniteScrollingSetting
		} ).done( function( response ) {
			var effectiveSetting = !! response.infiniteScrolling;

			wp.media.events.trigger( 'infinite-scrolling:change', effectiveSetting );

			if ( response.overridden ) {
				wp.a11y.speak( l10n.infiniteScrollingOverridden );
			} else {
				wp.a11y.speak( effectiveSetting ? l10n.infiniteScrollingEnabled : l10n.infiniteScrollingDisabled );
			}
		} ).fail( function() {
			view.update( previousValue );
			wp.a11y.speak( l10n.infiniteScrollingError );
		} ).always( function() {
			view.$el.prop( 'disabled', false );
		} );
	},

	/**
	 * Reflects the effective setting in the select control.
	 *
	 * @param {boolean|number} infiniteScrolling Whether infinite scrolling is enabled.
	 * @return {void}
	 */
	update: function( infiniteScrolling ) {
		settings.infiniteScrolling = infiniteScrolling ? 1 : 0;
		this.$el.val( infiniteScrolling ? '1' : '0' );
	}
});

module.exports = MediaLibraryPagination;
