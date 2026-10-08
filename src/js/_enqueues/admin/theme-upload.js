/**
 * @file Functionality for the theme upload screen.
 *
 * @output wp-admin/js/theme-upload.js
 */

/* global  theme_upload_intl, plupload, themeUploaderInit, ajaxurl, upload_theme_nonce, cancel_theme_overwrite_nonce, customize_url*/

function themeFileQueued( fileObj ) {
	jQuery( '#theme-upload-list' ).append( `
			<div class="theme" id="theme-item-${ fileObj.id }">
				<div class="theme-screenshot uploading">
					<div class="media-item"><div class="progress"><div class="percent">0%</div><div class="bar"></div></div></div>
				</div>
 				<div class="theme-author"> </div>
				<div class="theme-id-container">
					<h3 class="theme-name">${ fileObj.name }</h3>
					<div class="theme-actions"></div>
				</div>
			</div>
				` );
}

function buildComparisonTable( fileObj, data ) {
	return `
					<div id="theme-modal-window-${ fileObj.id }" style="display:none;">
						<table class="update-from-upload-comparison">
							<tbody>
							<tr>
								<th></th>
								<th>${ theme_upload_intl.current }</th>
								<th>${ theme_upload_intl.uploaded }</th>
							</tr>
							<tr>
								<td class="name-label">${ theme_upload_intl.theme_name }</td>
								<td>${ data.Name[ 0 ] }</td>
								<td>${ data.Name[ 1 ] }</td>
							</tr>
							<tr>
								<td class="name-label">${ theme_upload_intl.version }</td>
								<td>${ data.Version[ 0 ] }</td>
								<td>${ data.Version[ 1 ] }</td>
							</tr>
							<tr>
								<td class="name-label">${ theme_upload_intl.author }</td>
								<td>${ data.Author[ 0 ] }</td>
								<td>${ data.Author[ 1 ] }</td>
							</tr>
							<tr>
								<td class="name-label">${ theme_upload_intl.required_wordpress_version }</td>
								<td>${ data.RequiresWP[ 0 ] }</td>
								<td>${ data.RequiresWP[ 1 ] }</td>
							</tr>
							<tr>
								<td class="name-label">${ theme_upload_intl.required_php_version }</td>
								<td>${ data.RequiresPHP[ 0 ] }</td>
								<td>${ data.RequiresPHP[ 1 ] }</td>
							</tr>
							</tbody>
						</table>
					</div>

				`;
}
function themeUploadProgress( up, file ) {
	const item = jQuery( '#theme-item-' + file.id );

	jQuery( '.bar', item ).width( ( 200 * file.loaded ) / file.size );
	if ( 100 === file.percent ) {
		jQuery( '.percent', item ).html( theme_upload_intl.processing + '...' );
	} else {
		jQuery( '.percent', item ).html( file.percent + '%' );
	}
}

function themeUploadSuccess( fileObj, serverData ) {
	const item = jQuery( '#theme-item-' + fileObj.id );
	const action_selector = jQuery( '.theme-actions', item );

	const response_json = JSON.parse( serverData );
	const data = response_json.data;

	jQuery( '.theme-author', item ).text( 'By ' + data.theme.Author );

	jQuery( '.theme-name', item ).text( data.theme.Name );
	jQuery( '.theme-screenshot', item ).html( '' ).removeClass( 'uploading' );

	item.append(
		`<div class="notice notice-success notice-alt"><p>Installed</p></div>`
	);
	if ( 'can_override' === data.successCode ) {
		if ( data.comparisonMessage.Downgrade ) {
			action_selector.append(
				`<button class="button activate-button overwrite-theme" data-attachment="${ data.attachment_id }"> ${ theme_upload_intl.downgrade } </button>`
			);
		} else {
			action_selector.append(
				`<button class="button activate-button overwrite-theme" data-attachment="${ data.attachment_id }"> ${ theme_upload_intl.upgrade } </button>`
			);
		}
		action_selector.append(
			buildComparisonTable( fileObj, data.comparisonMessage )
		);
		action_selector.append(
			`<button class="button activate-button cancel-overwrite" data-file="${ fileObj.id }" data-attachment="${ data.attachment_id }"> ${ theme_upload_intl.cancel } </button>`
		);

		item.append(
			`<a href="#TB_inline?&inlineId=theme-modal-window-${ fileObj.id }&width=700" class="thickbox" ><span class="more-details">${theme_upload_intl.details}</span></a>`
		);
		jQuery( '.button.overwrite-theme' )
			.unbind()
			.click( function () {
				const button = jQuery( this );
				const attachment_id = button.data( 'attachment' );
 				overwriteTheme( attachment_id, button );
			} );
		jQuery( '.button.cancel-overwrite' )
			.unbind()
			.click( function () {
				const button = jQuery( this );
				const attachment_id = button.data( 'attachment' );
				const file_id = button.data( 'file' );
				cancelOverwriteTheme( file_id, attachment_id, button );
			} );
	} else {
		if ( data.screenshot ) {
			jQuery( '.theme-screenshot', item ).html(
				`<img src="${ data.screenshot }" class="theme-icon" alt="">`
			);
		}
		// The activate nonce must be in the format 'switch-theme_' . $_GET['stylesheet']. It is a bit tricky to generate this dynamically via javascript. So I will comment this out till I find a switable solution
 		// action_selector.append(`<a class="button activate" href="${theme_url}?action=activate&amp;stylesheet=${data.stylesheet}&amp;_wpnonce=${activate_theme_nonce}" aria-label="${ theme_upload_intl.activate } ${ data.theme.Name }">${ theme_upload_intl.activate } </a>`);

		action_selector.append(
			`<a class="button load-customize" href="${customize_url}?theme=${data.stylesheet}&amp;return=%2Fwp-admin%2Ftheme-install.php">${theme_upload_intl.live_preview}</a>`
		);
	}
}

function themeUploadError( fileObj, errorCode, message ) {
	if ( message ) {
		const item = jQuery( '#theme-item-' + fileObj.id );
		const selector = jQuery(
			'.theme-screenshot',
			item
		);

		const responseJSON = JSON.parse( message );
		if (
			responseJSON &&
			responseJSON.data &&
			responseJSON.data.errorMessage
		) {
			selector.html(
				`<div class="notice notice-error update-nag inline">${ responseJSON.data.errorMessage }</div>`
			);
		} else {
			selector.html(
				`<div class="notice notice-error update-nag inline">${ theme_upload_intl.generic_error }</div>`
			);
		}
	}
}

function overwriteTheme( attachment_id, button ) {
	const formData = new FormData();
	formData.append( '_wpnonce', upload_theme_nonce );
	formData.append( 'action', 'upload-theme' );
	button.prop( 'disabled', true );
	button.text( theme_upload_intl.processing + '...' );
	const cancel_button = button.parent().find('.cancel-overwrite');
	cancel_button.prop( 'disabled', true );


	jQuery.ajax( {
		type: 'POST',
		url: ajaxurl + '?package=' + attachment_id + '&overwrite=update-theme',
		data: formData,
		processData: false, // Important: tell jQuery not to process the data.
		contentType: false, // Important: tell jQuery not to set contentType.

		success: function () {
			button.text( theme_upload_intl.updated );
		},
		error: function () {
			button.prop( 'disabled', false );
			cancel_button.prop( 'disabled', false );
			button.text( theme_upload_intl.activation_failed );
		},
	} );
}

function cancelOverwriteTheme( file_id, attachment_id, button ) {
	const formData = new FormData();
	formData.append( '_wpnonce', cancel_theme_overwrite_nonce );
	formData.append( 'action', 'cancel-theme-overwrite' );
	button.prop( 'disabled', true );
	button.text( theme_upload_intl.processing + '...' );
	const overwrite_button = button.parent().find('.overwrite-theme');
	overwrite_button.prop( 'disabled', true );

	jQuery.ajax( {
		type: 'POST',
		url: ajaxurl + '?package=' + attachment_id,
		data: formData,
		processData: false, // Important: tell jQuery not to process the data.
		contentType: false, // Important: tell jQuery not to set contentType.

		success: function () {
			// Remove element from the dom.
			jQuery( '#theme-item-' + file_id ).remove();
		},
		error: function () {
			button.prop( 'disabled', false );
			overwrite_button.prop( 'disabled', false );
			button.text( theme_upload_intl.cancel_failed );
		},
	} );
}
jQuery( function ( $ ) {
	const uploader_init = function () {
		const uploader = new plupload.Uploader( themeUploaderInit );

		uploader.bind( 'Init', function ( up ) {
			var uploaddiv = $( '#plupload-upload-ui' );

			if (
				up.features.dragdrop &&
				! $( document.body ).hasClass( 'mobile' )
			) {
				uploaddiv.addClass( 'drag-drop' );

				$( '#drag-drop-area' )
					.on( 'dragover.wp-uploader', function () {
						// dragenter doesn't fire right :(
						uploaddiv.addClass( 'drag-over' );
					} )
					.on(
						'dragleave.wp-uploader, drop.wp-uploader',
						function () {
							uploaddiv.removeClass( 'drag-over' );
						}
					);
			} else {
				uploaddiv.removeClass( 'drag-drop' );
				$( '#drag-drop-area' ).off( '.wp-uploader' );
			}

			if ( up.runtime === 'html4' ) {
				$( '.upload-flash-bypass' ).hide();
			}
		} );

		uploader.bind( 'postinit', function ( up ) {
			up.refresh();
		} );

		uploader.init();

		uploader.bind( 'FilesAdded', function ( up, files ) {
			plupload.each( files, function ( file ) {
				if ( file.type !== 'application/zip' ) {
					// Ignore zip files
					return;
				}

				themeFileQueued( file );
			} );

			up.refresh();
			up.start();
		} );

		uploader.bind( 'UploadProgress', function ( up, file ) {
			themeUploadProgress( up, file );
		} );

		uploader.bind( 'Error', function ( up, error ) {
			themeUploadError( error.file, error.code, error.response );
			up.refresh();
		} );

		uploader.bind( 'FileUploaded', function ( up, file, response ) {
			themeUploadSuccess( file, response.response );
		} );
	};

	uploader_init();
} );
