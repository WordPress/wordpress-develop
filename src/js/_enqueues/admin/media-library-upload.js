/**
 * Routes Media Library grid uploads through the client-side media pipeline.
 *
 * On wp-admin/upload.php (grid mode) WordPress uploads via wp.Uploader /
 * plupload to async-upload.php. When the browser is cross-origin isolated
 * and supports the client-side pipeline, this script intercepts the
 * uploader's FilesAdded handler and routes files through
 * @wordpress/upload-media instead: the original image is uploaded via the
 * REST API and thumbnails are generated in the browser (wasm-vips), then
 * sideloaded and finalized.
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

	// Require every dependency the integration relies on.
	if (
		typeof wp === 'undefined' ||
		typeof plupload === 'undefined' ||
		! wp.Uploader ||
		! wp.uploadMedia ||
		! wp.mediaUtils ||
		! wp.data ||
		! wp.element ||
		! wp.apiFetch ||
		! wp.media
	) {
		return;
	}

	// Bail unless the browser actually supports client-side processing. This
	// is the clean no-op: when the isolation headers did not land, classic
	// plupload keeps handling uploads.
	if (
		! wp.uploadMedia.detectClientSideMediaSupport ||
		! wp.uploadMedia.detectClientSideMediaSupport().supported
	) {
		return;
	}

	window.__wpMediaLibraryUpload = true;

	/*
	 * @wordpress/media-utils branches on this flag: without it uploadMedia()
	 * creates and revokes a throwaway blob URL per file and emits an extra
	 * onFileChange carrying it. The block editor sets the same flag before
	 * configuring the same pipeline.
	 */
	window.__clientSideMediaProcessing = true;

	var __ = wp.i18n.__;
	var settings = window._wpMediaLibraryUploadSettings || {};
	var uploadStore = wp.uploadMedia.store;

	// Map from a file identity key to the placeholder Attachment models
	// (an array: concurrent uploads of an identical file share a key), used
	// to reflect pipeline progress back onto the grid tiles.
	var progressModels = new Map();

	/**
	 * Builds a stable identity key for a File.
	 *
	 * The queue item's `sourceFile` is a clone of the original file, so it
	 * cannot be matched by reference. The clone preserves name, size, and
	 * last-modified time, which together identify a file within one session.
	 * Two in-flight uploads of the same file collide on this key, so keys
	 * map to arrays of models and progress is mirrored to all of them.
	 *
	 * @param {File} file The file to key.
	 * @return {string} Identity key.
	 */
	function fileKey( file ) {
		return file.name + '::' + file.size + '::' + file.lastModified;
	}

	/**
	 * Recursively appends data to a FormData object, supporting nested objects.
	 *
	 * Mirrors flattenFormData() in @wordpress/media-utils.
	 *
	 * @param {FormData}      formData The form data to append to.
	 * @param {string}        key      The key to append under.
	 * @param {string|Object} data     The value to append.
	 */
	function flattenFormData( formData, key, data ) {
		if (
			data !== null &&
			typeof data === 'object' &&
			Object.getPrototypeOf( data ) === Object.prototype
		) {
			Object.keys( data ).forEach( function ( name ) {
				flattenFormData( formData, key + '[' + name + ']', data[ name ] );
			} );
		} else if ( data !== undefined ) {
			formData.append( key, String( data ) );
		}
	}

	/**
	 * Sideloads a client-generated thumbnail to an existing attachment.
	 *
	 * Reimplements the private sideloadMedia() helper from
	 * @wordpress/media-utils as a thin apiFetch wrapper.
	 *
	 * @param {Object} args The sideload arguments.
	 */
	function mediaSideload( args ) {
		var file = args.file;
		var additionalData = args.additionalData || {};

		var data = new FormData();
		data.append( 'file', file, file.name || file.type.replace( '/', '.' ) );
		Object.keys( additionalData ).forEach( function ( key ) {
			flattenFormData( data, key, additionalData[ key ] );
		} );

		wp.apiFetch( {
			path: '/wp/v2/media/' + args.attachmentId + '/sideload',
			body: data,
			method: 'POST',
			signal: args.signal,
		} )
			.then( function ( subSize ) {
				if ( args.onSuccess ) {
					args.onSuccess( subSize );
				}
			} )
			.catch( function ( error ) {
				if ( args.onError ) {
					var normalized = error;
					if ( ! ( error instanceof Error ) ) {
						normalized = new Error(
							error && error.message ? error.message : String( error )
						);
					}
					args.onError( normalized );
				}
			} );
	}

	/**
	 * Finalizes an upload once all client-side processing is complete.
	 *
	 * Reimplements the private mediaFinalize() helper. The returned
	 * attachment is load-bearing: it carries the post-finalize (scaled)
	 * URL used for srcset.
	 *
	 * @param {number} id       The parent attachment ID.
	 * @param {Array}  subSizes Accumulated sub-size data.
	 * @return {Promise} Resolves with the transformed attachment.
	 */
	function mediaFinalize( id, subSizes ) {
		return wp
			.apiFetch( {
				path: '/wp/v2/media/' + id + '/finalize',
				method: 'POST',
				data: { sub_sizes: subSizes || [] },
			} )
			.then( function ( response ) {
				if ( ! response ) {
					return undefined;
				}
				return wp.mediaUtils.transformAttachment( response );
			} );
	}

	/**
	 * Deletes an attachment whose client-side processing failed outright.
	 *
	 * The queue calls this when every sub-size sideload for an upload fails:
	 * without it the original file is left behind as an attachment with no
	 * metadata, visible in the Media Library after the next page load. The
	 * block editor passes the same setting.
	 *
	 * @param {number} id The attachment ID to delete.
	 * @return {Promise} Resolves once the attachment is deleted.
	 */
	function mediaDelete( id ) {
		return wp.apiFetch( {
			path: '/wp/v2/media/' + id + '?force=true',
			method: 'DELETE',
		} );
	}

	/**
	 * Builds the display text for a failed upload.
	 *
	 * wp.uploadMedia.getErrorMessage() maps an error *code* and a file name
	 * to a { title, description, action } object, so it can neither be handed
	 * the Error itself nor used as a string. Only codes it actually maps are
	 * worth using: its fallback (and the GENERAL code) says nothing the
	 * error's own message does not, and preferring the message there keeps a
	 * server-supplied reason instead of replacing it with "Please try again."
	 *
	 * @param {Error}  error    The upload error.
	 * @param {string} fileName Name of the file that failed to upload.
	 * @return {string} A human-readable message.
	 */
	function getErrorText( error, fileName ) {
		var errorCodes = wp.uploadMedia.ErrorCode || {};
		var code = error && error.code;
		var details;

		if (
			code &&
			code !== errorCodes.GENERAL &&
			Object.prototype.hasOwnProperty.call( errorCodes, code ) &&
			wp.uploadMedia.getErrorMessage
		) {
			details = wp.uploadMedia.getErrorMessage( code, fileName );

			if ( details && details.description ) {
				return details.action ?
					details.description + ' ' + details.action :
					details.description;
			}
		}

		return (
			( error && error.message ) ||
			__( 'An error occurred while uploading the file.' )
		);
	}

	// The pipeline never reports a numeric `progress` on its queue items
	// (nothing dispatches updateItemProgress), so estimate one from the
	// item's operation queue instead: each finished operation (prepare,
	// transcode, upload, thumbnails, finalize) advances the bar, and the
	// sub-sizes sideloaded so far advance it within thumbnail generation.
	// `item.progress` is preferred whenever it is present.
	var operationTotals = new Map();
	var imageSizeCount = Object.keys( settings.allImageSizes || {} ).length;

	/**
	 * Estimates the progress (0-100) of a queue item.
	 *
	 * @param {Object} item The upload-media queue item.
	 * @return {number} Estimated progress.
	 */
	function estimateProgress( item ) {
		if ( typeof item.progress === 'number' ) {
			return item.progress;
		}

		var remaining = item.operations ? item.operations.length : 0;
		var totals = operationTotals.get( item.id );
		if ( ! totals ) {
			totals = { total: remaining, remaining: remaining };
			operationTotals.set( item.id, totals );
		}
		// Operations are appended after preparation, so grow the total.
		if ( remaining > totals.remaining ) {
			totals.total += remaining - totals.remaining;
		}
		totals.remaining = remaining;

		if ( totals.total === 0 ) {
			return 0;
		}

		var completed = totals.total - remaining;
		var fraction = 0;
		if (
			'THUMBNAIL_GENERATION' === item.currentOperation &&
			imageSizeCount > 0
		) {
			fraction = Math.min(
				1,
				( item.subSizes || [] ).length / imageSizeCount
			);
		}

		return ( ( completed + fraction ) / totals.total ) * 100;
	}

	/**
	 * Drops progress bookkeeping for items that left the queue.
	 *
	 * @param {Array} items The current queue items.
	 */
	function pruneOperationTotals( items ) {
		var ids = {};
		items.forEach( function ( item ) {
			ids[ item.id ] = true;
		} );
		operationTotals.forEach( function ( totals, id ) {
			if ( ! ids[ id ] ) {
				operationTotals.delete( id );
			}
		} );
	}

	// Configure the default-registry upload-media store once. Rendering the
	// provider with useSubRegistry: false wires the settings into the store
	// that wp.data.dispatch/select address (the block editor does the same).
	var pipelineSettings = {
		mediaUpload: wp.mediaUtils.uploadMedia,
		mediaSideload: mediaSideload,
		mediaFinalize: mediaFinalize,
		mediaDelete: mediaDelete,
		maxUploadFileSize: settings.maxUploadFileSize,
		allowedMimeTypes: settings.allowedMimeTypes,
		allImageSizes: settings.allImageSizes,
		bigImageSizeThreshold: settings.bigImageSizeThreshold,
		imageStripMeta: settings.imageStripMeta,
		imageMaxBitDepth: settings.imageMaxBitDepth,
	};

	wp.element
		.createRoot( document.createElement( 'div' ) )
		.render(
			wp.element.createElement( wp.uploadMedia.MediaUploadProvider, {
				settings: pipelineSettings,
				useSubRegistry: false,
			} )
		);

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
	 * Removes a model from the progress map.
	 *
	 * @param {Object} model The Attachment model to stop tracking.
	 */
	function stopTrackingProgress( model ) {
		progressModels.forEach( function ( models, key ) {
			var index = models.indexOf( model );
			if ( index !== -1 ) {
				models.splice( index, 1 );
			}
			if ( models.length === 0 ) {
				progressModels.delete( key );
			}
		} );
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
				stopTrackingProgress( model );
				maybeResetQueue();

				// Parity with wp-plupload.js, which exposes this callback so
				// other code can react to a finished upload.
				wpUploader.success( model );
			} );
	}

	/**
	 * Handles an upload error by removing the tile and surfacing the message.
	 *
	 * @param {Object} wpUploader The wp.Uploader instance that queued the file.
	 * @param {Object} model      The placeholder Attachment model.
	 * @param {Error}  error      The upload error.
	 * @param {File}   nativeFile The original file (for the error label).
	 */
	function handleError( wpUploader, model, error, nativeFile ) {
		var message = getErrorText( error, nativeFile.name );
		var file = { name: nativeFile.name };

		model.destroy();

		wp.Uploader.errors.unshift( {
			message: message,
			data: {},
			file: file,
		} );

		stopTrackingProgress( model );
		maybeResetQueue();

		// Parity with wp-plupload.js, which exposes this callback so other
		// code can react to a failed upload.
		wpUploader.error( message, {}, file );
	}

	/**
	 * Dispatches a single file into the client-side pipeline.
	 *
	 * @param {Object} wpUploader     The wp.Uploader instance that queued the file.
	 * @param {File}   nativeFile     The original file to upload.
	 * @param {Object} model          The placeholder Attachment model.
	 * @param {Object} additionalData Extra fields to send with the attachment.
	 */
	function uploadFile( wpUploader, nativeFile, model, additionalData ) {
		wp.data.dispatch( uploadStore ).addItems( {
			files: [ nativeFile ],
			additionalData: additionalData,
			onSuccess: function ( attachments ) {
				handleSuccess( wpUploader, model, attachments[ 0 ] );
			},
			onError: function ( error ) {
				handleError( wpUploader, model, error, nativeFile );
			},
		} );
	}

	/**
	 * Intercepts files added to a plupload uploader.
	 *
	 * Returns undefined (not false) when the store is not yet configured so
	 * the built-in handler runs and uploads server-side - a degradation, never
	 * data loss. Otherwise builds the same placeholder tiles as wp-plupload,
	 * routes each file through the pipeline, and returns false to suppress the
	 * built-in handler.
	 *
	 * @param {Object} wpUploader The wp.Uploader instance.
	 * @param {Object} up         The plupload uploader instance.
	 * @param {Array}  files      Files added to the queue.
	 * @return {boolean|undefined} False to suppress the built-in handler.
	 */
	function handleFilesAdded( wpUploader, up, files ) {
		var storeSettings = wp.data.select( uploadStore ).getSettings();

		// Safety valve: if settings never landed, defer to classic plupload.
		if ( ! storeSettings || ! storeSettings.mediaUpload ) {
			return;
		}

		// The pipeline works on native File objects, and plupload returns
		// null for sources it cannot expose as one (the html4 runtime, say).
		// Hand the whole batch back to classic plupload rather than strand
		// part of it: suppressing the built-in handler is all-or-nothing.
		var unusable = files.some( function ( file ) {
			return (
				plupload.FAILED !== file.status &&
				( ! file.getNative || ! file.getNative() )
			);
		} );

		if ( unusable ) {
			return;
		}

		// The classic flow attaches uploads to the post the uploader was
		// opened for by posting `post_id` to async-upload.php; the REST API
		// spells the same thing `post`.
		var params = ( up.settings && up.settings.multipart_params ) || {};
		var parentPostId = parseInt( params.post_id, 10 ) || 0;
		var additionalData = parentPostId ? { post: parentPostId } : {};

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
			var key = fileKey( nativeFile );
			var models = progressModels.get( key );
			if ( models ) {
				models.push( model );
			} else {
				progressModels.set( key, [ model ] );
			}

			// Remove the file from plupload so it is not uploaded twice.
			up.removeFile( file );

			uploadFile( wpUploader, nativeFile, model, additionalData );
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

	// Warn before leaving while pipeline uploads are in flight: thumbnails
	// that have not been sideloaded yet are lost and the attachment is left
	// unfinalized, unlike classic uploads that complete server-side once
	// the bytes arrive. Models stay in the progress map from interception
	// until success or error, so the map doubles as the in-flight signal.
	window.addEventListener( 'beforeunload', function ( event ) {
		if ( progressModels.size > 0 ) {
			event.preventDefault();
			// Some Chromium versions only show the prompt for returnValue.
			event.returnValue = '';
		}
	} );

	// Reflect pipeline progress onto the placeholder tiles. Hold at 99
	// until the model is marked done so the tile does not appear finished
	// before the sync completes.
	wp.data.subscribe( function () {
		if ( progressModels.size === 0 ) {
			return;
		}

		var items = wp.data.select( uploadStore ).getItems();
		pruneOperationTotals( items );
		items.forEach( function ( item ) {
			// Sub-size children carry the parent's file; only top-level
			// items drive the tile.
			if ( ! item.sourceFile || item.parentId ) {
				return;
			}

			var models = progressModels.get( fileKey( item.sourceFile ) );
			if ( ! models ) {
				return;
			}

			var percent = Math.min( 99, Math.round( estimateProgress( item ) ) );
			models.forEach( function ( model ) {
				if ( model.get( 'percent' ) !== percent ) {
					model.set( { percent: percent } );
				}
			} );
		} );
	} );
} )();
