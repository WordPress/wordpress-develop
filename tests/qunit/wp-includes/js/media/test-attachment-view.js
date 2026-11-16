/* globals wp, jQuery, Backbone, plupload, mOxie */
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

( function() {
	'use strict';

	QUnit.module( 'wp.media.view.UploaderWindow: contextual file types', {
		beforeEach: function() {
			this.originalMimeTypes = wp.Uploader.mimeTypes;
			wp.Uploader.mimeTypes = {
				'jpg|jpeg': 'image/jpeg',
				png: 'image/png',
				pdf: 'application/pdf',
				mp4: 'video/mp4',
				custom: 'image/x-custom'
			};

			this.library = new wp.media.model.Attachments( [], { props: { type: [ 'image' ] } } );
			this.state = new wp.media.controller.Library( {
				library: this.library,
				contentUserSetting: false
			} );
			this.controller = _.extend( {
				state: _.bind( function() { return this.state; }, this ),
				router: { get: function() {} },
				content: { mode: function() { return 'browse'; } }
			}, Backbone.Events );
			this.state.frame = this.controller;
			this.state.activate();
			this.activatedState = this.state;

			this.view = new wp.media.view.UploaderWindow( {
				controller: this.controller,
				uploader: {
					container: jQuery( '#qunit-fixture' ),
					plupload: {
						filters: {
							max_file_size: '100b',
							mime_types: [ { extensions: 'jpg,jpeg,png,pdf,mp4,custom' } ]
						}
					}
				}
			} );
			this.controller.uploader = this.view;
			this.view.$el.appendTo( '#qunit-fixture' );
			this.view.ready();
			this.uploader = this.view.uploader.uploader;
			this.uploader.start = function() {};

			return new Promise( _.bind( function( resolve ) {
				if ( this.uploader.runtime ) {
					resolve();
				} else {
					this.uploader.bind( 'PostInit', resolve );
				}
			}, this ) );
		},
		afterEach: function() {
			this.activatedState.deactivate();
			if ( ! this.uploaderDestroyed ) {
				this.uploader.destroy();
			}
			this.view.$browser.remove();
			this.view.remove();
			wp.Uploader.queue.reset();
			wp.Uploader.errors.reset();
			wp.Uploader.mimeTypes = this.originalMimeTypes;
		}
	} );

	QUnit.test( 'image context restricts the picker and rejects PDFs before upload (Trac #48710)', function( assert ) {
		var done = assert.async(),
			selection = this.state.get( 'selection' ),
			accept = jQuery( '#qunit-fixture input[type="file"]' ).attr( 'accept' ) || '',
			file = new plupload.File( new mOxie.File( null, { name: 'document.pdf', type: 'application/pdf', size: 10 } ) );

		assert.ok( /png/.test( accept ) && ! /pdf/.test( accept ), 'The native picker includes images and excludes PDFs.' );
		assert.ok( this.view.uploader.isFileTypeSupported( { name: 'photo.JPG' } ), 'Valid image types remain supported.' );
		this.uploader.bind( 'Error', function( up, error ) {
			assert.strictEqual( error.code, plupload.FILE_EXTENSION_ERROR, 'The PDF is rejected as an unsupported type.' );
			assert.strictEqual( up.files.length, 0, 'The PDF does not enter the upload queue.' );
			assert.strictEqual( selection.length, 0, 'The PDF is not selected.' );
			assert.strictEqual( wp.Uploader.errors.at( 0 ).get( 'message' ), 'This file type is not supported here.', 'The uploader reports the contextual error.' );
			done();
		} );
		this.uploader.addFile( file );
	} );

	QUnit.test( 'changing context updates validation and the existing native picker (Trac #48710)', function( assert ) {
		this.library.props.set( 'type', 'video' );
		assert.ok( this.view.uploader.isFileTypeSupported( { name: 'movie.mp4' } ), 'The video context allows videos.' );
		assert.notOk( this.view.uploader.isFileTypeSupported( { name: 'photo.png' } ), 'The video context excludes images.' );
		assert.notOk( /png/.test( jQuery( '#qunit-fixture input[type="file"]' ).attr( 'accept' ) ), 'The existing picker excludes images.' );

		this.library.props.unset( 'type' );
		assert.ok( this.view.uploader.isFileTypeSupported( { name: 'document.pdf' } ), 'Removing the context allows PDFs again.' );
		assert.ok( /pdf/.test( jQuery( '#qunit-fixture input[type="file"]' ).attr( 'accept' ) ), 'The picker restores unrestricted media types.' );
	} );

	QUnit.test( 'a context switch rejects PDFs while retaining valid batch members (Trac #48710)', function( assert ) {
		var done = assert.async(),
			library = this.library,
			selection = this.state.get( 'selection' ),
			uploader = this.uploader,
			pipelineFiles,
			pdf = new plupload.File( new mOxie.File( null, { name: 'document.pdf', type: 'application/pdf', size: 10 } ) ),
			image = new plupload.File( new mOxie.File( null, { name: 'photo.png', type: 'image/png', size: 10 } ) );

		library.props.unset( 'type' );
		uploader.bind( 'FileFiltered', function() {
			library.props.set( 'type', 'image' );
		} );
		uploader.bind( 'FilesAdded', function( up, files ) {
			pipelineFiles = files.slice();
		}, null, 100 );
		uploader.bind( 'FilesAdded', function( up ) {
			assert.deepEqual( pipelineFiles, [ image ], 'Only the image reaches the media pipeline.' );
			assert.strictEqual( up.files.length, 1, 'The PDF is removed from the upload queue.' );
			assert.strictEqual( wp.Uploader.queue.length, 1, 'Only the valid file creates an upload model.' );
			assert.strictEqual( selection.at( 0 ).get( 'filename' ), 'photo.png', 'Only the valid image is selected.' );
			assert.strictEqual( wp.Uploader.errors.length, 1, 'The PDF rejection is reported.' );
			done();
		} );
		uploader.addFile( [ pdf, image ] );
		this.clock.tick( 10 );
	} );

	QUnit.test( 'plugin filters remain independent when they match the current context (Trac #48710)', function( assert ) {
		var filters = jQuery.extend( true, {}, this.uploader.getOption( 'filters' ) );

		this.library.props.set( 'type', 'image/png' );
		filters.mime_types = [ { extensions: 'png' } ];
		filters.max_file_size = '10b';
		filters.prevent_duplicates = true;
		this.uploader.setOption( 'filters', filters );
		this.view.refresh();
		this.library.props.set( 'type', 'video' );
		assert.notOk( this.view.uploader.isFileTypeSupported( { name: 'photo.png' } ), 'The context still applies to plugin-allowed files.' );
		this.library.props.unset( 'type' );
		assert.ok( this.view.uploader.isFileTypeSupported( { name: 'photo.png' } ), 'The plugin still allows PNGs.' );
		assert.notOk( this.view.uploader.isFileTypeSupported( { name: 'document.pdf' } ), 'Removing context does not widen the explicit plugin filter.' );
		assert.strictEqual( jQuery( '#qunit-fixture input[type="file"]' ).attr( 'accept' ), '.png', 'The picker retains the plugin restriction.' );
		assert.strictEqual( this.uploader.getOption( 'filters' ).max_file_size, '10b', 'The runtime size limit survives refresh and context changes.' );
		assert.true( this.uploader.getOption( 'filters' ).prevent_duplicates, 'Other plugin filters remain intact.' );
	} );

	QUnit.test( 'uploads from other contexts cannot enter the active selection (Trac #48710)', function( assert ) {
		var selection = this.state.get( 'selection' ),
			pdf = new wp.media.model.Attachment( {
				filename: 'document.pdf', uploading: true,
				file: { name: 'document.pdf', type: 'application/pdf' }
			} ),
			image = new wp.media.model.Attachment( {
				filename: 'photo.custom', uploading: true,
				file: { name: 'photo.custom', type: '' }
			} );

		wp.Uploader.queue.add( pdf );
		assert.strictEqual( selection.length, 0, 'A PDF from another uploader is excluded from the image selection.' );
		wp.Uploader.queue.add( image );
		assert.strictEqual( selection.get( image ), image, 'An allowed custom extension is accepted even without a browser MIME type.' );
		assert.strictEqual( wp.Uploader.errors.length, 0, 'An upload valid in another context does not produce a global error.' );
	} );

	QUnit.test( 'removed frames stop observing uploads after their uploader is destroyed (Trac #48710)', function( assert ) {
		var frame = new wp.media.view.Frame(),
			selection = this.state.get( 'selection' ),
			upload = new wp.media.model.Attachment( {
				filename: 'photo.png', uploading: true,
				file: { name: 'photo.png', type: 'image/png' }
			} ),
			error;

		// Exercise the real state machine and frame removal without rendering regions.
		frame.title = { render: function() {} };
		frame.menu = { get: function() {}, mode: function() {} };
		frame.router = { get: function() {}, render: function() {} };
		frame.toolbar = { render: function() {} };
		frame.content = { mode: function() { return 'browse'; }, render: function() {} };
		frame.uploader = this.view;
		this.state.deactivate();
		frame.states.add( this.state );
		frame.setState( this.state.id );

		this.uploader.destroy();
		this.uploaderDestroyed = true;
		frame.remove();
		assert.false( this.state.active, 'Removing the frame deactivates its current state.' );
		try {
			wp.Uploader.queue.add( upload );
		} catch ( caught ) {
			error = caught;
		}
		assert.notOk( error, 'A later upload does not access the destroyed uploader.' );
		assert.strictEqual( selection.length, 0, 'The removed frame does not select uploads from the shared queue.' );
		frame.remove();
		assert.false( this.state.active, 'Repeated removal leaves the state inactive.' );
	} );

} )();
