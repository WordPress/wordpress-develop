/* globals wp, jQuery */
/* jshint qunit: true */
/* eslint-env qunit */

( function() {
	'use strict';

	var $template;

	QUnit.module( 'wp.media.view.Attachment', {
		beforeEach: function() {
			$template = jQuery( '<script type="text/html" id="tmpl-attachment"><div class="attachment-preview"></div></script>' )
				.appendTo( 'body' );
		},
		afterEach: function() {
			$template.remove();
		}
	} );

	/**
	 * Creates an attachment view for a new model, with a minimal controller.
	 *
	 * @since 7.2.0
	 *
	 * @param {Object}                   attributes  Attributes for the attachment model.
	 * @param {Function}                 [ViewClass] View class to instantiate. Defaults to wp.media.view.Attachment.
	 * @param {wp.media.model.Selection} [selection] Selection the view should reflect.
	 * @return {wp.media.view.Attachment} The created view.
	 */
	function createView( attributes, ViewClass, selection ) {
		var controller = {
				state: function() {
					return { get: function() {} };
				},
				isModeActive: function() {
					return false;
				},
				trigger: function() {},
				states: new wp.media.model.Attachments()
			},
			model = new wp.media.model.Attachment( attributes );

		ViewClass = ViewClass || wp.media.view.Attachment;

		return new ViewClass( { controller: controller, model: model, selection: selection } );
	}

	/*
	 * Trac #65852: Backbone evaluates attributes() only when the element is
	 * created, so a view built for an id-only model kept a stale aria-label
	 * after the model received its data.
	 */
	QUnit.test( 'aria-label is refreshed when the model title loads (Trac #65852)', function( assert ) {
		var view = createView( { id: 123 } );

		assert.strictEqual( view.el.getAttribute( 'aria-label' ), '(no title)', 'Initial label is the no-title fallback.' );

		view.model.set( { title: 'Sunset' } );

		assert.strictEqual( view.el.getAttribute( 'aria-label' ), 'Sunset', 'Label is updated to the loaded title.' );
	} );

	QUnit.test( 'aria-label stays "uploading…" until the upload completes (Trac #65852)', function( assert ) {
		var view = createView( { uploading: true, filename: 'sunset.jpg', percent: 0 } );

		assert.strictEqual( view.el.getAttribute( 'aria-label' ), 'uploading…', 'Initial label is the uploading label.' );

		view.model.set( { percent: 50 } );

		assert.strictEqual( view.el.getAttribute( 'aria-label' ), 'uploading…', 'Label is unchanged while progress is reported.' );

		view.model.set( { uploading: false, title: 'Sunset' } );

		assert.strictEqual( view.el.getAttribute( 'aria-label' ), 'Sunset', 'Label is updated once the upload completes.' );
	} );

	QUnit.test( 'untitled attachments keep the no-title label (Trac #65852)', function( assert ) {
		var view = createView( { id: 123, title: '' } );

		view.model.set( { caption: 'A caption' } );

		assert.strictEqual( view.el.getAttribute( 'aria-label' ), '(no title)', 'Label remains the no-title fallback.' );
	} );

	QUnit.test( 'aria-checked is not reset by re-rendering (Trac #65852)', function( assert ) {
		var selection = new wp.media.model.Selection(),
			view = createView( { id: 123 }, null, selection );

		selection.add( view.model );
		view.render();

		assert.strictEqual( view.el.getAttribute( 'aria-checked' ), 'true', 'The selected tile is checked.' );

		view.model.set( { title: 'Sunset' } );

		assert.strictEqual( view.el.getAttribute( 'aria-checked' ), 'true', 'Selection state is preserved after the label refresh.' );
	} );

	QUnit.test( 'Attachment.Details does not receive an aria-label (Trac #65852)', function( assert ) {
		var view = createView( { id: 123 }, wp.media.view.Attachment.Details );

		view.model.set( { title: 'Sunset' } );

		assert.notOk( view.el.hasAttribute( 'aria-label' ), 'No aria-label is added.' );
		assert.notOk( view.el.hasAttribute( 'role' ), 'No role is added.' );
	} );
} )();
