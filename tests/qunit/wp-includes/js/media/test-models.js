/* globals wp */
/* jshint qunit: true */
/* eslint-env qunit */

( function () {
	'use strict';

	QUnit.module( 'wp.media.model.Attachments' );

	/*
	 * Trac #65053: opening the media modal via "Set featured image" or
	 * an Image block reported an incorrect "Showing X of Y" total because
	 * Attachments#observe() bound the totalAttachments trackers to every
	 * observed collection, including the Selection. Selection mutations
	 * therefore mutated the mirrored Query's totalAttachments.
	 *
	 * The library mirrors a Query (which owns totalAttachments) AND
	 * additionally observes a Selection (so a pre-existing featured image
	 * stays visible in the grid). Only the Query should drive the count.
	 */
	QUnit.test(
		'observing a selection does not corrupt totalAttachments (Trac #65053)',
		function ( assert ) {
			var Attachments = wp.media.model.Attachments,
				Selection = wp.media.model.Selection,
				Attachment = wp.media.model.Attachment,
				query,
				library,
				selection;

			// Stand-in for a Query collection: the source of truth for totalAttachments.
			query = new Attachments();
			query.totalAttachments = 8;

			// The library is what AttachmentsBrowser renders. It mirrors the query.
			library = new Attachments();
			library.mirror( query );

			assert.strictEqual(
				library.getTotalAttachments(),
				8,
				'precondition: getTotalAttachments() reflects the mirrored query total'
			);

			// FeaturedImage / ReplaceImage controllers additionally observe the selection.
			selection = new Selection( [], { multiple: false } );
			library.observe( selection );

			// Adding to the selection must not mutate the mirrored query's total.
			selection.add( new Attachment( { id: 42 } ) );
			assert.strictEqual(
				library.getTotalAttachments(),
				8,
				'add to observed selection leaves totalAttachments untouched'
			);

			// Single-select swap: Selection#add internally remove()s prior models
			// before adding the new one. Both events must be ignored by the count.
			selection.add( new Attachment( { id: 99 } ) );
			assert.strictEqual(
				library.getTotalAttachments(),
				8,
				'single-select swap on observed selection leaves totalAttachments untouched'
			);

			// Removing from the selection must also leave the total alone.
			selection.remove( selection.models );
			assert.strictEqual(
				library.getTotalAttachments(),
				8,
				'remove from observed selection leaves totalAttachments untouched'
			);
		}
	);

	QUnit.test(
		'mirrored query add/remove still drives totalAttachments',
		function ( assert ) {
			var Attachments = wp.media.model.Attachments,
				Attachment = wp.media.model.Attachment,
				query,
				library,
				attachment;

			query = new Attachments();
			query.totalAttachments = 5;

			library = new Attachments();
			library.mirror( query );

			attachment = new Attachment( { id: 1 } );
			query.add( attachment );
			assert.strictEqual(
				library.getTotalAttachments(),
				6,
				'adding to the mirrored query increments totalAttachments'
			);

			query.remove( attachment );
			assert.strictEqual(
				library.getTotalAttachments(),
				5,
				'removing from the mirrored query decrements totalAttachments'
			);
		}
	);
} )();
