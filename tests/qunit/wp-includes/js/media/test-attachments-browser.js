/* globals wp, jQuery */
/* jshint qunit: true */
/* eslint-env qunit */

( function() {
	'use strict';

	QUnit.module( 'wp.media.view.AttachmentsBrowser' );

	/**
	 * Creates the parts of an attachments browser that updateLoadMoreView() reads and updates.
	 *
	 * @since 7.2.0
	 *
	 * @param {number} displayed Number of media items shown in the browser.
	 * @param {number} total     Number of media items in the library.
	 * @return {Object} The browser parts.
	 */
	function createBrowserParts( displayed, total ) {
		return {
			$el: jQuery( '<div></div>' ),
			loadMoreButton: { $el: jQuery( '<button></button>' ) },
			loadMoreCount: { $el: jQuery( '<p></p>' ) },
			loadMoreJumpToFirst: { $el: jQuery( '<button></button>' ) },
			collection: {
				length: displayed,
				getTotalAttachments: function() {
					return total;
				},
				hasMore: function() {
					return displayed < total;
				}
			}
		};
	}

	QUnit.test( 'The load more count uses the plural form for the total number of media items.', function( assert ) {
		var done = assert.async(),
			browser = wp.media.view.AttachmentsBrowser.prototype,
			one = createBrowserParts( 1, 1 ),
			two = createBrowserParts( 1, 2 );

		/*
		 * updateLoadMoreView() is an underscore debounce, which measures its wait
		 * with the real Date.now(), so it cannot run on this suite's fake timers.
		 * Use real timers, clear any pending call, and run one update at a time,
		 * since the debounced function is shared by all browsers.
		 */
		this.clock.restore();
		browser.updateLoadMoreView.cancel();

		browser.updateLoadMoreView.call( one );
		setTimeout( function() {
			assert.strictEqual( one.loadMoreCount.$el.text(), 'Showing 1 of 1 media item', 'One media item in total uses the singular form.' );

			browser.updateLoadMoreView.call( two );
			setTimeout( function() {
				assert.strictEqual( two.loadMoreCount.$el.text(), 'Showing 1 of 2 media items', 'Two media items in total use the plural form.' );
				done();
			}, 50 );
		}, 50 );
	} );
} )();
