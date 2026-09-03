/**
 * Routes Media Library grid uploads through the client-side media pipeline.
 *
 * On wp-admin/upload.php (grid mode) WordPress uploads via wp.Uploader /
 * plupload to async-upload.php. When the browser is cross-origin isolated
 * and supports the client-side pipeline, this script intercepts the
 * uploader's FilesAdded handler and hands files to wp.mediaUploadPipeline
 * (media-upload-pipeline.js) instead: the original image is uploaded via
 * the REST API and thumbnails are generated in the browser (wasm-vips),
 * then sideloaded and finalized.
 *
 * The grid's own UI is reused: the same placeholder tiles, progress bars,
 * "Uploading n/m" status, and error sidebar that wp-plupload.js drives.
 *
 * When client-side support is unavailable the script cleanly no-ops and
 * the classic plupload flow is left untouched.
 *
 * @output wp-admin/js/media-library-upload.js
 */

/* global plupload */

( function () {
	// Guard against double execution (e.g. duplicate enqueues).
	if ( window.__wpMediaLibraryUpload ) {
		return;
	}

	var pipeline = window.wp && wp.mediaUploadPipeline;

	// Bail unless the browser actually supports client-side processing. This
	// is the clean no-op: when the isolation headers did not land, classic
	// plupload keeps handling uploads.
	if (
		! pipeline ||
		typeof plupload === 'undefined' ||
		! wp.Uploader ||
		! wp.media ||
		! pipeline.configure()
	) {
		return;
	}

	window.__wpMediaLibraryUpload = true;

	/**
	 * Resets the upload queue once every attachment has finished uploading.
	 *
	 * Parity with wp-plupload.js so browse mode flips back when done.
	 */
	function maybeResetQueue() {
		var complete = wp.Uploader.queue.all( function ( attachment ) {
			return ! attachment.get( 'uploading' );
		} );

		if ( complete ) {
			wp.Uploader.queue.reset();
		}
	}

	/**
	 * Handles a completed upload by syncing the grid tile with the server data.
	 *
	 * @param {Object} wpUploader The wp.Uploader instance that queued the file.
	 * @param {Object} model      The placeholder Attachment model.
	 * @param {Object} attachment The finalized attachment from the pipeline.
	 */
	function handleSuccess( wpUploader, model, attachment ) {
		model.set( { id: attachment.id }, { silent: true } );

		// Register the model in Attachments.all (parity with wp-plupload.js).
		wp.media.model.Attachment.get( attachment.id, model );

		model
			.fetch()
			.done( function () {
				[ 'file', 'loaded', 'size', 'percent' ].forEach( function (
					key
				) {
					model.unset( key, { silent: true } );
				} );
				model.set( { uploading: false } );
			} )
			.fail( function () {
				// Fetch failed, but the upload succeeded: clear the uploading
				// state with what the pipeline gave us so no tile is stuck.
				[ 'file', 'loaded', 'size', 'percent' ].forEach( function (
					key
				) {
					model.unset( key, { silent: true } );
				} );
				model.set( attachment );
				model.set( { uploading: false } );
			} )
			.always( function () {
				maybeResetQueue();

				// Parity with wp-plupload.js, which exposes this callback so
				// other code can react to a finished upload.
				wpUploader.success( model );
			} );
	}

	/**
	 * Handles an upload error by removing the tile and surfacing the message.
	 *
	 * The error goes into wp.Uploader.errors exactly like a classic upload
	 * error, so the grid's error sidebar renders it, announces it, and
	 * moves focus to its Dismiss button.
	 *
	 * @param {Object} wpUploader The wp.Uploader instance that queued the file.
	 * @param {Object} model      The placeholder Attachment model.
	 * @param {Error}  error      The upload error.
	 * @param {File}   nativeFile The original file (for the error label).
	 */
	function handleError( wpUploader, model, error, nativeFile ) {
		var message = pipeline.getErrorText( error, nativeFile.name );
		var file = { name: nativeFile.name };

		model.destroy();

		wp.Uploader.errors.unshift( {
			message: message,
			data: {},
			file: file,
		} );

		maybeResetQueue();

		// Parity with wp-plupload.js, which exposes this callback so other
		// code can react to a failed upload.
		wpUploader.error( message, {}, file );
	}

	/**
	 * Intercepts files added to a plupload uploader.
	 *
	 * Returns undefined (not false) when the pipeline cannot take the batch
	 * so the built-in handler runs and uploads server-side - a degradation,
	 * never data loss. Otherwise builds the same placeholder tiles as
	 * wp-plupload, routes each file through the pipeline, and returns false
	 * to suppress the built-in handler.
	 *
	 * @param {Object} wpUploader The wp.Uploader instance.
	 * @param {Object} up         The plupload uploader instance.
	 * @param {Array}  files      Files added to the queue.
	 * @return {boolean|undefined} False to suppress the built-in handler.
	 */
	function handleFilesAdded( wpUploader, up, files ) {
		if ( ! pipeline.isReady() || ! pipeline.canHandleBatch( files ) ) {
			return;
		}

		// The classic flow posts plupload's multipart params to
		// async-upload.php; forward them so plugins reading $_POST see the
		// same fields, with `post_id` spelled `post` for the REST API.
		var params = ( up.settings && up.settings.multipart_params ) || {};
		var additionalData = pipeline.additionalDataFromParams(
			params,
			parseInt( params.post_id, 10 ) || 0
		);

		files.forEach( function ( file ) {
			// Ignore failed uploads.
			if ( plupload.FAILED === file.status ) {
				return;
			}

			// Build the same placeholder attributes as wp-plupload.js so the
			// grid's progress tiles and "Uploading n/m" status work unchanged.
			var attributes = {
				file: file,
				uploading: true,
				date: new Date(),
				filename: file.name,
				menuOrder: 0,
				uploadedTo: wp.media.model.settings.post.id,
				loaded: file.loaded,
				size: file.size,
				percent: file.percent,
			};

			var image = /(?:jpe?g|png|gif)$/i.exec( file.name );
			if ( image ) {
				attributes.type = 'image';
				// `jpg` is not a valid subtype, so map it to `jpeg`.
				attributes.subtype = 'jpg' === image[ 0 ] ? 'jpeg' : image[ 0 ];
			}

			var model = wp.media.model.Attachment.create( attributes );
			wp.Uploader.queue.add( model );
			wpUploader.added( model );

			var nativeFile = file.getNative();

			// Remove the file from plupload so it is not uploaded twice.
			up.removeFile( file );

			pipeline.queueFile( nativeFile, additionalData, {
				onSuccess: function ( attachment ) {
					handleSuccess( wpUploader, model, attachment );
				},
				onError: function ( error ) {
					handleError( wpUploader, model, error, nativeFile );
				},
				onProgress: function ( percent ) {
					model.set( { percent: percent } );
				},
			} );
		} );

		up.refresh();

		return false;
	}

	// Wrap wp.Uploader.prototype.init (an empty stub called once per instance
	// after plupload is initialized) to bind a higher-priority FilesAdded
	// handler on every uploader instance, including the Media Library grid's.
	var originalInit = wp.Uploader.prototype.init;
	wp.Uploader.prototype.init = function () {
		originalInit.apply( this, arguments );

		var wpUploader = this;
		var up = this.uploader;

		if ( ! up || up.__wpMediaLibraryUploadBound ) {
			return;
		}
		up.__wpMediaLibraryUploadBound = true;

		// plupload sorts handlers by priority (descending) and a `false`
		// return breaks the chain, so priority 100 runs before and suppresses
		// the built-in FilesAdded handler.
		up.bind(
			'FilesAdded',
			function ( uploader, files ) {
				return handleFilesAdded( wpUploader, uploader, files );
			},
			this,
			100
		);
	};
} )();
