/* globals wp */
/* jshint qunit: true */
/* eslint-env qunit */

( function() {
	'use strict';

	QUnit.module( 'wp.media.view.Toolbar' );

	/**
	 * Creates a toolbar with one button that requires a selection.
	 *
	 * @param {wp.media.model.Selection} selection Selection of the controller's state.
	 * @return {wp.media.view.Toolbar} The created toolbar.
	 */
	function createToolbar( selection ) {
		var state = {
				get: function( key ) {
					return 'selection' === key ? selection : undefined;
				}
			},
			controller = {
				state: function() {
					return state;
				}
			};

		return new wp.media.view.Toolbar( {
			controller: controller,
			items: {
				select: {
					text: 'Select',
					requires: { selection: true }
				}
			}
		} );
	}

	/*
	 * Trac #54839: an upload the library query does not show still finishes
	 * in the selection, so the toolbar has to refresh on the selection's
	 * change:uploading.
	 */
	QUnit.test( 'refreshes when a selected attachment finishes uploading (Trac #54839)', function( assert ) {
		var attachment = new wp.media.model.Attachment( { uploading: true } ),
			toolbar = createToolbar( new wp.media.model.Selection( [ attachment ] ) ),
			button = toolbar.get( 'select' ).model;

		toolbar.refresh();
		assert.true( button.get( 'disabled' ), 'Button is disabled while the attachment uploads.' );

		attachment.set( { uploading: false } );
		assert.false( button.get( 'disabled' ), 'Button is enabled once the upload completes.' );
	} );

	QUnit.test( 'stops listening to change:uploading when disposed (Trac #54839)', function( assert ) {
		var attachment = new wp.media.model.Attachment( { uploading: false } ),
			toolbar = createToolbar( new wp.media.model.Selection( [ attachment ] ) ),
			button = toolbar.get( 'select' ).model;

		toolbar.dispose();
		attachment.set( { uploading: true } );
		assert.false( button.get( 'disabled' ), 'A disposed toolbar does not refresh.' );
	} );
}() );
