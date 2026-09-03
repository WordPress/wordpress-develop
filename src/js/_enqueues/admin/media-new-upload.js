/**
 * Routes "Add New Media File" uploads through the client-side media pipeline.
 *
 * On wp-admin/media-new.php WordPress uploads via a raw plupload.Uploader
 * (created by plupload-handlers) posting to async-upload.php. When the
 * browser is cross-origin isolated and supports the client-side pipeline,
 * this script intercepts the uploader's FilesAdded handler and routes files
 * through @wordpress/upload-media instead: the original image is uploaded
 * via the REST API and thumbnails are generated in the browser (wasm-vips),
 * then sideloaded and finalized.
 *
 * The screen's existing UI helpers from plupload-handlers are reused:
 * fileQueued() builds the progress item, uploadSuccess() renders the
 * finished attachment row (via the async-upload.php markup endpoint),
 * itemAjaxError() surfaces per-file errors, and uploadComplete() runs when
 * the queue drains. The screen therefore looks and behaves unchanged.
 *
 * When client-side support is unavailable the script cleanly no-ops and
 * the classic plupload flow is left untouched.
 *
 * @output wp-admin/js/media-new-upload.js
 */

/* global plupload, uploader, fileQueued, uploadStart, uploadSuccess, itemAjaxError, uploadComplete */

( function () {
	// Guard against double execution (e.g. duplicate enqueues).
	if ( window.__wpMediaNewUpload ) {
		return;
	}

	// Require every dependency the integration relies on.
	if (
		typeof wp === 'undefined' ||
		typeof plupload === 'undefined' ||
		typeof jQuery === 'undefined' ||
		! wp.uploadMedia ||
		! wp.mediaUtils ||
		! wp.data ||
		! wp.element ||
		! wp.apiFetch
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

	window.__wpMediaNewUpload = true;

	/*
	 * @wordpress/media-utils branches on this flag: without it uploadMedia()
	 * creates and revokes a throwaway blob URL per file and emits an extra
	 * onFileChange carrying it. The block editor sets the same flag before
	 * configuring the same pipeline.
	 */
	window.__clientSideMediaProcessing = true;

	var __ = wp.i18n.__;
	var settings = window._wpMediaNewUploadSettings || {};
	var uploadStore = wp.uploadMedia.store;

	// Number of pipeline uploads currently in flight, used for the
	// beforeunload guard and to fire uploadComplete() when the queue drains.
	var inFlightCount = 0;

	// Map from a file identity key to the plupload file IDs sharing it
	// (an array: concurrent uploads of an identical file share a key), used
	// to reflect pipeline progress onto the screen's progress bars.
	var progressIds = new Map();

	/**
	 * Builds a stable identity key for a File.
	 *
	 * The queue item's `sourceFile` is a clone of the original file, so it
	 * cannot be matched by reference. The clone preserves name, size, and
	 * last-modified time, which together identify a file within one session.
	 * Two in-flight uploads of the same file collide on this key, so keys
	 * map to arrays of IDs and progress is mirrored to all of them.
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
	 * Removes a plupload file ID from the progress map.
	 *
	 * @param {string} fileId The plupload file ID to stop tracking.
	 */
	function stopTrackingProgress( fileId ) {
		progressIds.forEach( function ( ids, key ) {
			var index = ids.indexOf( fileId );
			if ( index !== -1 ) {
				ids.splice( index, 1 );
			}
			if ( ids.length === 0 ) {
				progressIds.delete( key );
			}
		} );
	}

	/**
	 * Marks one pipeline upload as finished, firing uploadComplete() when
	 * the queue drains.
	 *
	 * The built-in UploadComplete binding never fires for pipeline uploads
	 * because every file is removed from plupload before its queue starts.
	 */
	function finishUpload() {
		inFlightCount--;
		if ( inFlightCount === 0 ) {
			uploadComplete();
		}
	}

	/**
	 * Intercepts files added to the plupload uploader.
	 *
	 * Returns undefined (not false) when the store is not yet configured so
	 * the built-in handler runs and uploads server-side - a degradation, never
	 * data loss. Otherwise builds the screen's progress items, routes each
	 * file through the pipeline, and returns false to suppress the built-in
	 * handler (which would otherwise queue and start a classic upload).
	 *
	 * @param {Object} up    The plupload uploader instance.
	 * @param {Array}  files Files added to the queue.
	 * @return {boolean|undefined} False to suppress the built-in handler.
	 */
	function handleFilesAdded( up, files ) {
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

		// Parity with the built-in handler: clear stale queue errors and run
		// the shared upload-start housekeeping.
		jQuery( '#media-upload-error' ).empty();
		uploadStart();

		// The classic flow attaches uploads to the post named by `post_id` in
		// the query string by posting it to async-upload.php; the REST API
		// spells the same thing `post`. Without it a file uploaded from
		// media-new.php?post_id=N lands unattached even though the screen
		// counts it against that post.
		var params = ( up.settings && up.settings.multipart_params ) || {};
		var parentPostId = parseInt( params.post_id, 10 ) || 0;
		var additionalData = parentPostId ? { post: parentPostId } : {};

		files.forEach( function ( file ) {
			// Ignore failed uploads.
			if ( plupload.FAILED === file.status ) {
				return;
			}

			// Build the screen's progress item for this file.
			fileQueued( file );

			var nativeFile = file.getNative();
			var key = fileKey( nativeFile );
			var ids = progressIds.get( key );
			if ( ids ) {
				ids.push( file.id );
			} else {
				progressIds.set( key, [ file.id ] );
			}

			// Remove the file from plupload so it is not uploaded twice.
			up.removeFile( file );

			inFlightCount++;

			wp.data.dispatch( uploadStore ).addItems( {
				files: [ nativeFile ],
				additionalData: additionalData,
				onSuccess: function ( attachments ) {
					// uploadSuccess() renders the finished attachment row via
					// the existing async-upload.php markup endpoint; the
					// server normally returns the ID as a string.
					uploadSuccess( file, String( attachments[ 0 ].id ) );
					stopTrackingProgress( file.id );
					finishUpload();
				},
				onError: function ( error ) {
					/*
					 * itemAjaxError() writes the message straight into the
					 * item's HTML, and the message carries the file name, so
					 * escape it here rather than hand a user-supplied name to
					 * an HTML sink.
					 */
					var message = jQuery( '<div>' )
						.text( getErrorText( error, nativeFile.name ) )
						.html();

					itemAjaxError( file.id, message );
					stopTrackingProgress( file.id );
					finishUpload();
				},
			} );
		} );

		up.refresh();

		return false;
	}

	jQuery( function () {
		// plupload-handlers creates the global `uploader` in its own ready
		// callback, which runs before this one: ready callbacks run in
		// registration order and this script loads after plupload-handlers.
		// The global stays undefined when wpUploaderInit is missing (the
		// html-uploader fallback), in which case there is nothing to bind.
		if (
			typeof uploader !== 'object' ||
			! uploader ||
			uploader.__wpMediaNewUploadBound
		) {
			return;
		}
		uploader.__wpMediaNewUploadBound = true;

		// plupload sorts handlers by priority (descending) and a `false`
		// return breaks the chain, so priority 100 runs before and suppresses
		// the built-in FilesAdded handler.
		uploader.bind(
			'FilesAdded',
			function ( up, files ) {
				return handleFilesAdded( up, files );
			},
			null,
			100
		);
	} );

	// Warn before leaving while pipeline uploads are in flight: thumbnails
	// that have not been sideloaded yet are lost and the attachment is left
	// unfinalized, unlike classic uploads that complete server-side once
	// the bytes arrive.
	window.addEventListener( 'beforeunload', function ( event ) {
		if ( inFlightCount > 0 ) {
			event.preventDefault();
			// Some Chromium versions only show the prompt for returnValue.
			event.returnValue = '';
		}
	} );

	// Reflect pipeline progress onto the screen's progress bars. Hold at 99
	// until the finished row is rendered so the bar does not appear done
	// before the markup fetch completes. The bar is 200px wide at 100%,
	// matching uploadProgress() in plupload-handlers.
	var lastPercent = new Map();
	wp.data.subscribe( function () {
		if ( progressIds.size === 0 ) {
			lastPercent.clear();
			return;
		}

		var items = wp.data.select( uploadStore ).getItems();
		pruneOperationTotals( items );
		items.forEach( function ( item ) {
			// Sub-size children carry the parent's file; only top-level
			// items drive the progress bar.
			if ( ! item.sourceFile || item.parentId ) {
				return;
			}

			var ids = progressIds.get( fileKey( item.sourceFile ) );
			if ( ! ids ) {
				return;
			}

			var percent = Math.min( 99, Math.round( estimateProgress( item ) ) );
			ids.forEach( function ( id ) {
				if ( lastPercent.get( id ) === percent ) {
					return;
				}
				lastPercent.set( id, percent );
				var mediaItem = jQuery( '#media-item-' + id );
				mediaItem.find( '.bar' ).width( 2 * percent );
				mediaItem.find( '.percent' ).html( percent + '%' );
			} );
		} );
	} );
} )();
