/**
 * Shared glue for routing classic admin uploads through the client-side
 * media pipeline.
 *
 * The Media Library grid (media-library-upload.js) and the Add New Media
 * File screen (media-new-upload.js) both intercept plupload and hand files
 * to @wordpress/upload-media instead. Everything that is not specific to
 * one screen's UI lives here: feature detection, configuring the
 * upload-media store once, the REST sideload/finalize/delete helpers the
 * store needs, queueing a file with success, error, and progress
 * callbacks, the error text shown for a failed upload, and the guard that
 * warns before leaving while uploads are in flight.
 *
 * Exposed as wp.mediaUploadPipeline.
 *
 * @output wp-admin/js/media-upload-pipeline.js
 */

/* global plupload */

window.wp = window.wp || {};

( function ( wp ) {
	const __ = wp.i18n.__;
	const settings = window._wpMediaUploadPipelineSettings || {};
	/** @type {any} */
	let uploadStore;
	let configured = false;

	// Number of queued files that have not succeeded or failed yet.
	let inFlight = 0;

	// Uploads waiting to be matched to a queue item, keyed by file identity
	// (concurrent uploads of an identical file share a key and are matched
	// in order), and uploads already matched, keyed by queue item id. Items
	// are matched by id from then on because the store swaps `sourceFile`
	// for HEIC files once they are converted to JPEG.
	const pending = new Map();
	const active = new Map();

	const imageSizeCount = Object.keys( settings.allImageSizes || {} ).length;

	/**
	 * Builds a stable identity key for a File.
	 *
	 * The queue item's `sourceFile` is a clone of the original file, so it
	 * cannot be matched by reference. The clone preserves name, size, and
	 * last-modified time, which together identify a file within one session.
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
	 * Mirrors flattenFormData() in the media-utils package.
	 *
	 * @param {FormData} formData The form data to append to.
	 * @param {string}   key      The key to append under.
	 * @param {*}        data     The value to append.
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
	 * Reimplements the private sideloadMedia() helper from the media-utils
	 * package as a thin apiFetch wrapper.
	 *
	 * @param {Object}                      args                  The sideload arguments.
	 * @param {File}                        args.file             The sub-size to sideload.
	 * @param {number}                      args.attachmentId     The attachment to sideload it to.
	 * @param {Record<string, unknown>}     [args.additionalData] Extra fields to send with it.
	 * @param {AbortSignal}                 [args.signal]         Signal aborting the request.
	 * @param {( subSize: Object ) => void} [args.onSuccess]      Called with the sideloaded sub-size.
	 * @param {( error: Error ) => void}    [args.onError]        Called when the sideload failed.
	 */
	function mediaSideload( args ) {
		const file = args.file;
		const additionalData = args.additionalData || {};

		const data = new FormData();
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
			.then( function ( /** @type {Object} */ subSize ) {
				if ( args.onSuccess ) {
					args.onSuccess( subSize );
				}
			} )
			.catch( function ( /** @type {unknown} */ error ) {
				if ( args.onError ) {
					let normalized = error;
					if ( ! ( error instanceof Error ) ) {
						const rest = /** @type {UploadError} */ ( error );
						normalized = new Error(
							rest && rest.message ? rest.message : String( error )
						);
					}
					args.onError( /** @type {Error} */ ( normalized ) );
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
	 * @param {number}   id       The parent attachment ID.
	 * @param {Object[]} subSizes Accumulated sub-size data.
	 * @return {Promise<PipelineAttachment|undefined>} Resolves with the transformed attachment.
	 */
	function mediaFinalize( id, subSizes ) {
		return wp
			.apiFetch( {
				path: '/wp/v2/media/' + id + '/finalize',
				method: 'POST',
				data: { sub_sizes: subSizes || [] },
			} )
			.then( function ( /** @type {Object|undefined} */ response ) {
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
	 * @return {Promise<unknown>} Resolves once the attachment is deleted.
	 */
	function mediaDelete( id ) {
		return wp.apiFetch( {
			path: '/wp/v2/media/' + id + '?force=true',
			method: 'DELETE',
		} );
	}

	/**
	 * Whether every script the pipeline relies on is present.
	 *
	 * @return {boolean} True when the dependencies loaded.
	 */
	function hasDependencies() {
		return Boolean(
			typeof plupload !== 'undefined' &&
				wp.uploadMedia &&
				wp.mediaUtils &&
				wp.data &&
				wp.element &&
				wp.apiFetch
		);
	}

	/**
	 * Whether the browser can run the client-side pipeline on this page.
	 *
	 * False when the isolation headers did not land (the page is not
	 * cross-origin isolated) or the browser lacks the required features,
	 * in which case classic plupload keeps handling uploads.
	 *
	 * @return {boolean} True when client-side processing is available.
	 */
	function isSupported() {
		return Boolean(
			hasDependencies() &&
				wp.uploadMedia.detectClientSideMediaSupport &&
				wp.uploadMedia.detectClientSideMediaSupport().supported
		);
	}

	/**
	 * Estimates the progress (0-100) of a queue item.
	 *
	 * The pipeline never reports a numeric `progress` on its queue items
	 * (nothing dispatches updateItemProgress), so estimate one from the
	 * item's operation queue instead: each finished operation (prepare,
	 * transcode, upload, thumbnails, finalize) advances the bar, and the
	 * sub-sizes sideloaded so far advance it within thumbnail generation.
	 * `item.progress` is preferred whenever it is present.
	 *
	 * @param {QueueItem}   item  The upload-media queue item.
	 * @param {UploadEntry} entry The pipeline's bookkeeping for the upload.
	 * @return {number} Estimated progress.
	 */
	function estimateProgress( item, entry ) {
		if ( typeof item.progress === 'number' ) {
			return item.progress;
		}

		const remaining = item.operations ? item.operations.length : 0;
		let totals = entry.totals;
		if ( ! totals ) {
			totals = { total: remaining, remaining: remaining };
			entry.totals = totals;
		}
		// Operations are appended after preparation, so grow the total.
		if ( remaining > totals.remaining ) {
			totals.total += remaining - totals.remaining;
		}
		totals.remaining = remaining;

		if ( totals.total === 0 ) {
			return 0;
		}

		const completed = totals.total - remaining;
		let fraction = 0;
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
	 * Matches queue items to queued uploads and reports their progress.
	 *
	 * Runs on every change to the upload-media store. Sub-size children
	 * carry the parent's file and are skipped; only top-level items drive
	 * the screen's progress UI. Progress holds at 99 until the upload's
	 * success callback has run, so nothing looks finished before the
	 * screen has synced the result.
	 */
	function onStoreChange() {
		if ( inFlight === 0 ) {
			return;
		}

		const items = wp.data.select( uploadStore ).getItems();
		items.forEach( function ( /** @type {QueueItem} */ item ) {
			if ( item.parentId || ! item.sourceFile ) {
				return;
			}

			let entry = active.get( item.id );
			if ( ! entry ) {
				const key = fileKey( item.sourceFile );
				const list = pending.get( key );
				if ( ! list || ! list.length ) {
					return;
				}
				entry = list.shift();
				if ( ! list.length ) {
					pending.delete( key );
				}
				entry.itemId = item.id;
				active.set( item.id, entry );
			}

			if ( ! entry.onProgress ) {
				return;
			}

			const percent = Math.min(
				99,
				Math.round( estimateProgress( item, entry ) )
			);
			if ( percent !== entry.lastPercent ) {
				entry.lastPercent = percent;
				entry.onProgress( percent );
			}
		} );
	}

	/**
	 * Stops tracking an upload once it has succeeded or failed.
	 *
	 * @param {UploadEntry} entry The pipeline's bookkeeping for the upload.
	 */
	function release( entry ) {
		if ( entry.released ) {
			return;
		}
		entry.released = true;
		inFlight--;

		const list = pending.get( entry.key );
		if ( list ) {
			const index = list.indexOf( entry );
			if ( index !== -1 ) {
				list.splice( index, 1 );
			}
			if ( ! list.length ) {
				pending.delete( entry.key );
			}
		}

		if ( entry.itemId ) {
			active.delete( entry.itemId );
		}
	}

	/**
	 * Configures the upload-media store for this page, once.
	 *
	 * Rendering the provider with useSubRegistry: false wires the settings
	 * into the store that wp.data.dispatch/select address (the block editor
	 * does the same).
	 *
	 * @return {boolean} True when the pipeline is configured and usable.
	 */
	function configure() {
		if ( configured ) {
			return true;
		}

		if ( ! isSupported() ) {
			return false;
		}

		configured = true;
		uploadStore = wp.uploadMedia.store;

		/*
		 * The media-utils package branches on this flag: without it
		 * uploadMedia() creates and revokes a throwaway blob URL per file
		 * and emits an extra onFileChange carrying it. The block editor
		 * sets the same flag before configuring the same pipeline.
		 */
		window.__clientSideMediaProcessing = true;

		wp.element
			.createRoot( document.createElement( 'div' ) )
			.render(
				wp.element.createElement( wp.uploadMedia.MediaUploadProvider, {
					settings: {
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
					},
					useSubRegistry: false,
				} )
			);

		// Only the upload-media store can change what this listener reads.
		wp.data.subscribe( onStoreChange, uploadStore );

		// Warn before leaving while uploads are in flight: thumbnails that
		// have not been sideloaded yet are lost and the attachment is left
		// unfinalized, unlike classic uploads that complete server-side
		// once the bytes arrive.
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( inFlight > 0 ) {
				event.preventDefault();
				// Some Chromium versions only show the prompt for returnValue.
				event.returnValue = '';
			}
		} );

		return true;
	}

	/**
	 * Whether the store has received its settings.
	 *
	 * The provider renders asynchronously, so a file added before the
	 * settings land must be left to classic plupload - a degradation,
	 * never data loss.
	 *
	 * @return {boolean} True when the store is ready to accept files.
	 */
	function isReady() {
		if ( ! configured ) {
			return false;
		}
		const storeSettings = wp.data.select( uploadStore ).getSettings();
		return Boolean( storeSettings && storeSettings.mediaUpload );
	}

	/**
	 * Whether a batch of plupload files can be routed through the pipeline.
	 *
	 * Suppressing plupload's built-in FilesAdded handler is all-or-nothing,
	 * so the whole batch stays on the classic path when any file cannot be
	 * handled: plupload returns no native File for sources it cannot expose
	 * as one (the html4 runtime, say), and audio files are left to the
	 * classic upload so they keep the title and description that
	 * media_handle_upload() derives from their ID3 tags, which the REST
	 * endpoint does not do.
	 *
	 * @param {plupload.File[]} files Files added to the plupload queue.
	 * @return {boolean} True when every file can go through the pipeline.
	 */
	function canHandleBatch( files ) {
		return files.every( function ( file ) {
			if ( plupload.FAILED === file.status ) {
				return true;
			}
			if ( ! file.getNative || ! file.getNative() ) {
				return false;
			}
			return ! /^audio\//i.test( file.type || '' );
		} );
	}

	/**
	 * Builds the extra fields to send with an upload from plupload's
	 * multipart params.
	 *
	 * Anything a plugin added through the `plupload_default_params` filter
	 * or wp.Uploader.param() reached the classic upload as $_POST fields,
	 * so it is forwarded to the REST request the same way. The classic
	 * transport's own fields are dropped: `action` and `_wpnonce` belong to
	 * async-upload.php, and `post_id` is spelled `post` by the REST API and
	 * passed separately after the screen has validated it.
	 *
	 * @param {Record<string, string>} params Plupload's multipart_params.
	 * @param {number}                 postId The validated post to attach the upload to, or 0.
	 * @return {Record<string, unknown>} Additional data for the upload.
	 */
	function additionalDataFromParams( params, postId ) {
		/** @type {Record<string, unknown>} */
		const additionalData = {};
		Object.keys( params || {} ).forEach( function ( key ) {
			if ( 'action' === key || '_wpnonce' === key || 'post_id' === key ) {
				return;
			}
			additionalData[ key ] = params[ key ];
		} );
		if ( postId ) {
			additionalData.post = postId;
		}
		return additionalData;
	}

	/**
	 * Queues a file for client-side processing and upload.
	 *
	 * @param {File}                    nativeFile     The file to upload.
	 * @param {Record<string, unknown>} additionalData Extra fields to send with the attachment.
	 * @param {QueueFileCallbacks}      [callbacks]    Lifecycle callbacks.
	 */
	function queueFile( nativeFile, additionalData, callbacks ) {
		callbacks = callbacks || {};

		const entry = {
			key: fileKey( nativeFile ),
			itemId: null,
			onProgress: callbacks.onProgress,
			lastPercent: -1,
			totals: null,
			released: false,
		};

		const list = pending.get( entry.key );
		if ( list ) {
			list.push( entry );
		} else {
			pending.set( entry.key, [ entry ] );
		}
		inFlight++;

		wp.data.dispatch( uploadStore ).addItems( {
			files: [ nativeFile ],
			additionalData: additionalData || {},
			onSuccess: function (
				/** @type {PipelineAttachment[]} */ attachments
			) {
				release( entry );
				if ( callbacks.onSuccess ) {
					callbacks.onSuccess( attachments[ 0 ] );
				}
			},
			onError: function ( /** @type {UploadError} */ error ) {
				release( entry );
				if ( callbacks.onError ) {
					callbacks.onError( error );
				}
			},
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
	 * @param {UploadError} error    The upload error.
	 * @param {string}      fileName Name of the file that failed to upload.
	 * @return {string} A human-readable message.
	 */
	function getErrorText( error, fileName ) {
		const errorCodes = wp.uploadMedia.ErrorCode || {};
		const code = error && error.code;
		let details;

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

	/**
	 * Whether any queued upload has not finished yet.
	 *
	 * @return {boolean} True while uploads are in flight.
	 */
	function hasInFlight() {
		return inFlight > 0;
	}

	wp.mediaUploadPipeline = {
		isSupported: isSupported,
		configure: configure,
		isReady: isReady,
		canHandleBatch: canHandleBatch,
		additionalDataFromParams: additionalDataFromParams,
		queueFile: queueFile,
		getErrorText: getErrorText,
		hasInFlight: hasInFlight,
	};
} )( window.wp );
