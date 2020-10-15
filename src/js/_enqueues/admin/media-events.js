/**
 * @output wp-admin/js/media-events.js
 */
/**
 * Admin media event handlers.
 *
 * @since 5.6.0
 * 
 * @requires image-edit
 * @requires gallery
 * @requires set-post-thumbnail
 *
 * @param {Object} document  The document object.
 * @param {Object} window    The window object.
 *
 * @return {void}
 */

 /* global  addExtImage, imageEdit, wpgallery, WPSetAsThumbnail */
( function ( document, window ) {
	document.addEventListener( 'DOMContentLoaded', function () {
		document
			.querySelectorAll( '.imgedit-open-btn' )
			.forEach( function ( item ) {
				item.addEventListener( 'click', function () {
					imageEdit.open(
						parseInt( item.dataset.postId, 10 ),
						item.dataset.nonce
					);
				} );
			} );

		document.querySelectorAll( '.del-link' ).forEach( function ( item ) {
			item.addEventListener( 'click', function ( event ) {
				document.getElementById(
					'del_attachment_' + item.dataset.attachmentId
				).style.display = 'block';
				event.preventDefault();
			} );
		} );

		document
			.querySelectorAll( '.button.cancel-del-link' )
			.forEach( function ( item ) {
				item.addEventListener( 'click', function ( event ) {
					this.parentNode.style.display = 'none';
					event.preventDefault();
				} );
			} );

		document
			.querySelectorAll( '.wp-post-thumbnail' )
			.forEach( function ( item ) {
				item.addEventListener( 'click', function ( event ) {
					WPSetAsThumbnail(
						item.dataset.attachmentId,
						item.dataset.ajaxNonce
					);
					event.preventDefault();
				} );
			} );

		var cancelAsyncUpload = document.getElementById(
			'cancel-async-upload'
		);
		if ( cancelAsyncUpload !== null ) {
			cancelAsyncUpload.addEventListener( 'click', function ( event ) {
				try {
					window.top.tb_remove();
				} catch ( e ) {}
				event.preventDefault();
			} );
		}

		document
			.querySelectorAll( '.button.gallery-actions' )
			.forEach( function ( item ) {
				item.addEventListener( 'mousedown', function () {
					wpgallery.update();
				} );
			} );

		var srcInput = document.getElementById( 'src' );
		if ( srcInput !== null ) {
			// wp_media_insert_url_form was called.
			srcInput.addEventListener( 'blur', function () {
				addExtImage.getImageData();
			} );

			document
				.querySelectorAll( 'td.field > input[name="align"]' )
				.forEach( function ( item ) {
					item.addEventListener( 'click', function ( event ) {
						addExtImage.align = 'align' + event.target.value;
					} );
				} );

			document
				.getElementById( 'image-only-none' )
				.addEventListener( 'click', function () {
					document.forms[ 0 ].url.value = null;
				} );

			document
				.getElementById( 'image-only-link' )
				.addEventListener( 'click', function () {
					document.forms[ 0 ].url.value =
						document.forms[ 0 ].src.value;
				} );

			document
				.getElementById( 'go_button' )
				.addEventListener( 'click', function () {
					addExtImage.insert();
				} );
		}
	} );
} )( document, window );
