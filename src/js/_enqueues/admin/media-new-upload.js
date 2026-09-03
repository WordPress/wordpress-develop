/**
 * Routes "Add New Media File" uploads through the client-side media pipeline.
 *
 * On wp-admin/media-new.php WordPress uploads via a raw plupload.Uploader
 * (created by plupload-handlers) posting to async-upload.php. When the
 * browser is cross-origin isolated and supports the client-side pipeline,
 * this script intercepts the uploader's FilesAdded handler and hands files
 * to wp.mediaUploadPipeline (media-upload-pipeline.js) instead: the
 * original image is uploaded via the REST API and thumbnails are generated
 * in the browser (wasm-vips), then sideloaded and finalized.
 *
 * The screen's existing UI helpers from plupload-handlers are reused:
 * fileQueued() builds the progress item, uploadSuccess() renders the
 * finished attachment row (via the async-upload.php markup endpoint), and
 * uploadComplete() runs when the queue drains. Failed uploads render the
 * same notice async-upload.php returns for a server-side failure, so the
 * screen looks and behaves unchanged.
 *
 * When client-side support is unavailable the script cleanly no-ops and
 * the classic plupload flow is left untouched.
 *
 * @output wp-admin/js/media-new-upload.js
 */

/* global plupload, pluploadL10n, uploader, fileQueued, uploadStart, uploadSuccess, uploadComplete */

( function () {
	// Guard against double execution (e.g. duplicate enqueues).
	if ( window.__wpMediaNewUpload ) {
		return;
	}

	var pipeline = window.wp && wp.mediaUploadPipeline;

	// Bail unless the browser actually supports client-side processing. This
	// is the clean no-op: when the isolation headers did not land, classic
	// plupload keeps handling uploads.
	if (
		! pipeline ||
		typeof plupload === 'undefined' ||
		typeof jQuery === 'undefined' ||
		! wp.a11y ||
		! pipeline.configure()
	) {
		return;
	}

	window.__wpMediaNewUpload = true;

	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	// Number of pipeline uploads currently in flight, used to fire
	// uploadComplete() when the queue drains.
	var inFlightCount = 0;

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
	 * Returns the post the screen attaches uploads to, or 0.
	 *
	 * media-new.php validates `post_id` (the post must exist and be
	 * editable by the user) before printing it into the form, whereas the
	 * raw request value is what plupload carries in its multipart params.
	 * async-upload.php re-validates that raw value and silently uploads
	 * unattached; the REST API rejects it instead, so read the validated one.
	 *
	 * @return {number} The parent post ID, or 0.
	 */
	function getParentPostId() {
		var input = document.getElementById( 'post_id' );
		var postId = input ? parseInt( input.value, 10 ) : 0;
		return postId > 0 ? postId : 0;
	}

	/**
	 * Escapes text for insertion into HTML.
	 *
	 * @param {string} text The text to escape.
	 * @return {string} The escaped text.
	 */
	function escapeHtml( text ) {
		return jQuery( '<div>' ).text( text ).html();
	}

	/**
	 * Renders a failed upload the way async-upload.php does for a
	 * server-side failure: an error notice with a real Dismiss button
	 * described by the notice, a screen reader announcement, and focus
	 * returned to the browse button once dismissed.
	 *
	 * @param {Object} file    The plupload file that failed.
	 * @param {string} message The reason the upload failed.
	 */
	function renderError( file, message ) {
		var item = jQuery( '#media-item-' + file.id );
		var buttonId = 'dismiss-' + file.id;
		var descriptionId = 'error-description-' + file.id;

		var button = jQuery( '<button>', {
			type: 'button',
			id: buttonId,
			'class': 'dismiss button-link',
			'aria-describedby': descriptionId,
			text: pluploadL10n.dismiss,
		} );

		var notice = jQuery( '<div>', {
			id: descriptionId,
			'class': 'notice notice-error error-div error',
		} )
			.append( button )
			.append( ' ' )
			.append(
				jQuery( '<strong>' ).html(
					pluploadL10n.error_uploading.replace(
						'%s',
						escapeHtml( file.name )
					)
				)
			)
			.append( '<br />' )
			.append( document.createTextNode( message ) );

		item.empty().append( notice ).data( 'last-err', file.id );

		setTimeout( function () {
			wp.a11y.speak(
				sprintf(
					/* translators: %s: Name of the file that failed to upload. */
					__( '%s has failed to upload.' ),
					file.name
				)
			);
		}, 1500 );

		button.on( 'click', function () {
			jQuery( this )
				.parents( 'div.media-item' )
				.slideUp( 200, function () {
					jQuery( this ).remove();
					wp.a11y.speak( __( 'Error dismissed.' ) );
					jQuery( '#plupload-browse-button' ).trigger( 'focus' );
				} );
		} );
	}

	/**
	 * Reflects pipeline progress onto the screen's progress bar for a file.
	 *
	 * The bar is 200px wide at 100%, matching uploadProgress() in
	 * plupload-handlers.
	 *
	 * @param {Object} file    The plupload file being uploaded.
	 * @param {number} percent Progress percentage.
	 */
	function renderProgress( file, percent ) {
		var item = jQuery( '#media-item-' + file.id );
		item.find( '.bar' ).width( 2 * percent );
		item.find( '.percent' ).html( percent + '%' );
	}

	/**
	 * Intercepts files added to the plupload uploader.
	 *
	 * Returns undefined (not false) when the pipeline cannot take the batch
	 * so the built-in handler runs and uploads server-side - a degradation,
	 * never data loss. Otherwise builds the screen's progress items, routes
	 * each file through the pipeline, and returns false to suppress the
	 * built-in handler (which would otherwise queue and start a classic
	 * upload).
	 *
	 * @param {Object} up    The plupload uploader instance.
	 * @param {Array}  files Files added to the queue.
	 * @return {boolean|undefined} False to suppress the built-in handler.
	 */
	function handleFilesAdded( up, files ) {
		if ( ! pipeline.isReady() || ! pipeline.canHandleBatch( files ) ) {
			return;
		}

		// Parity with the built-in handler: clear stale queue errors and run
		// the shared upload-start housekeeping.
		jQuery( '#media-upload-error' ).empty();
		uploadStart();

		// The classic flow posts plupload's multipart params to
		// async-upload.php; forward them so plugins reading $_POST see the
		// same fields. Without the parent post a file uploaded from
		// media-new.php?post_id=N would land unattached even though the
		// screen counts it against that post.
		var params = ( up.settings && up.settings.multipart_params ) || {};
		var additionalData = pipeline.additionalDataFromParams(
			params,
			getParentPostId()
		);

		files.forEach( function ( file ) {
			// Ignore failed uploads.
			if ( plupload.FAILED === file.status ) {
				return;
			}

			// Build the screen's progress item for this file.
			fileQueued( file );

			var nativeFile = file.getNative();

			// Remove the file from plupload so it is not uploaded twice.
			up.removeFile( file );

			inFlightCount++;

			pipeline.queueFile( nativeFile, additionalData, {
				onSuccess: function ( attachment ) {
					// uploadSuccess() renders the finished attachment row via
					// the existing async-upload.php markup endpoint; the
					// server normally returns the ID as a string.
					uploadSuccess( file, String( attachment.id ) );
					finishUpload();
				},
				onError: function ( error ) {
					renderError(
						file,
						pipeline.getErrorText( error, nativeFile.name )
					);
					finishUpload();
				},
				onProgress: function ( percent ) {
					renderProgress( file, percent );
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
} )();
