/**
 * @file Functionality for the plugin upload screen.
 *
 * @output wp-admin/js/plugin-upload.js
 */

/* global  plugin_upload_intl, plupload, pluginUploaderInit, ajaxurl, upload_plugin_nonce, activate_plugin_nonce, cancel_overwrite_nonce */

function pluginFileQueued( fileObj ) {
	jQuery( '#plugin-upload-list' ).append( `
				<div class="plugin-card" id="plugin-item-${ fileObj.id }">
					<div class="plugin-card-top">
						<div class="name column-name">
							<h3>${ fileObj.name }</h3>
							<div class="media-item"><div class="progress"><div class="percent">0%</div><div class="bar"></div></div></div>
						</div>
						<div class="action-links"><ul class="plugin-action-buttons"></ul></div>
						<div class="desc column-description" style="text-align: left"><p class="description"></p><p class="authors"></p></div>
					</div>
				</div>
				` );
}

function buildComparisonTable( fileObj, data ) {
	return `
					<div id="plugin-modal-window-${ fileObj.id }" style="display:none;">
						<table class="update-from-upload-comparison">
							<tbody>
							<tr>
								<th></th>
								<th>${ plugin_upload_intl.current }</th>
								<th>${ plugin_upload_intl.uploaded }</th>
							</tr>
							<tr>
								<td class="name-label">${ plugin_upload_intl.plugin_name }</td>
								<td>${ data.Name[ 0 ] }</td>
								<td>${ data.Name[ 1 ] }</td>
							</tr>
							<tr>
								<td class="name-label">${ plugin_upload_intl.version }</td>
								<td>${ data.Version[ 0 ] }</td>
								<td>${ data.Version[ 1 ] }</td>
							</tr>
							<tr>
								<td class="name-label">${ plugin_upload_intl.author }</td>
								<td>${ data.Author[ 0 ] }</td>
								<td>${ data.Author[ 1 ] }</td>
							</tr>
							<tr>
								<td class="name-label">${ plugin_upload_intl.required_wordpress_version }</td>
								<td>${ data.RequiresWP[ 0 ] }</td>
								<td>${ data.RequiresWP[ 1 ] }</td>
							</tr>
							<tr>
								<td class="name-label">${ plugin_upload_intl.required_php_version }</td>
								<td>${ data.RequiresPHP[ 0 ] }</td>
								<td>${ data.RequiresPHP[ 1 ] }</td>
							</tr>
							</tbody>
						</table>
					</div>

				`;
}
function pluginUploadProgress( up, file ) {
	const item = jQuery( '#plugin-item-' + file.id );

	jQuery( '.bar', item ).width( ( 200 * file.loaded ) / file.size );
	if ( 100 == file.percent ) {
		jQuery( '.percent', item ).html(
			plugin_upload_intl.processing + '...'
		);
	} else {
		jQuery( '.percent', item ).html( file.percent + '%' );
	}
}

function pluginUploadSuccess( fileObj, serverData ) {
	const item = jQuery( '#plugin-item-' + fileObj.id );
	const action_selector = jQuery( '.plugin-action-buttons', item );
	const description_selector = jQuery(
		'.column-description .description',
		item
	);
	const response_json = JSON.parse( serverData );
	const data = response_json.data;
	description_selector.text( data.plugin.Description );
	jQuery( '.column-description .authors', item ).text(
		'By ' + data.plugin.Author
	);
	// https://s.w.org/plugins/geopattern-icon/cf7-kofi.svg
	const temp_slug = data.plugin.Title.toLowerCase().replace( ' ', '-' );
	jQuery( '.name h3', item )
		.html(
			`${ data.plugin.Name } <img src="https://s.w.org/plugins/geopattern-icon/${ temp_slug }.svg" class="plugin-icon" alt="">`
		)
		.css( { textAlign: 'left' } );
	jQuery( '.media-item', item ).hide();
	if ( 'can_override' === data.successCode ) {
		if ( data.comparisonMessage.Downgrade ) {
			action_selector.append(
				`<li><button class="button activate-button overwrite-plugin" data-attachment="${ data.attachment_id }"> ${ plugin_upload_intl.downgrade } </button></li>`
			);
		} else {
			action_selector.append(
				`<li><button class="button activate-button overwrite-plugin" data-attachment="${ data.attachment_id }"> ${ plugin_upload_intl.upgrade } </button></li>`
			);
		}
		action_selector.append(
			`<li><button class="button activate-button cancel-overwrite" data-file="${ fileObj.id }" data-attachment="${ data.attachment_id }"> ${ plugin_upload_intl.cancel } </button></li>`
		);
		action_selector.append(
			`<li><a href="#TB_inline?&inlineId=plugin-modal-window-${ fileObj.id }&width=700" class="thickbox" data-title="${ data.plugin.Name } ${ data.plugin.Version }">${ plugin_upload_intl.more_details }</a></li>`
		);
		description_selector.append(
			buildComparisonTable( fileObj, data.comparisonMessage )
		);
		jQuery( '.button.overwrite-plugin' )
			.unbind()
			.click( function () {
				const button = jQuery( this );
				const attachment_id = button.data( 'attachment' );
				overwritePlugin( attachment_id, button );
			} );
		jQuery( '.button.cancel-overwrite' )
			.unbind()
			.click( function () {
				const button = jQuery( this );
				const attachment_id = button.data( 'attachment' );
				const file_id = button.data( 'file' );
				cancelOverwritePlugin( file_id, attachment_id, button );
			} );
	} else {
		action_selector.append(
			`<li><button class="button activate-button activate-plugin button-primary" data-name="${ data.plugin.Name }" data-path="${ data.path }"> ${ plugin_upload_intl.activate } </button></li>`
		);

		jQuery( '.button.activate-plugin' )
			.unbind()
			.click( function () {
				const button = jQuery( this );
				const path = button.data( 'path' );
				const name = button.data( 'name' );
				activatePlugin( path, name, button );
			} );
	}
}

function pluginUploadError( fileObj, errorCode, message ) {
	if ( message ) {
		const item = jQuery( '#plugin-item-' + fileObj.id );
		const description_selector = jQuery(
			'.column-description .description',
			item
		);

		jQuery( '.media-item', item ).hide();
		const responseJSON = JSON.parse( message );
		if (
			responseJSON &&
			responseJSON.data &&
			responseJSON.data.errorMessage
		) {
			description_selector.html(
				`<div class="notice notice-error update-nag inline">${ responseJSON.data.errorMessage }</div>`
			);
		} else {
			description_selector.html(
				`<div class="notice notice-error update-nag inline">${ plugin_upload_intl.generic_error }</div>`
			);
		}
	}
}

function overwritePlugin( attachment_id, button ) {
 	const formData = new FormData();
	formData.append( '_wpnonce', upload_plugin_nonce );
	formData.append( 'action', 'upload-plugin' );
 	button.prop( 'disabled', true );
	button.text( plugin_upload_intl.processing + '...' );
	const cancel_overwrite_button = button.parent().parent().find( '.cancel-overwrite' );
	cancel_overwrite_button.prop( 'disabled', true );
	jQuery.ajax( {
		type: 'POST',
		url: ajaxurl + '?package=' + attachment_id + '&overwrite=update-plugin',
		data: formData,
		processData: false, // Important: tell jQuery not to process the data.
		contentType: false, // Important: tell jQuery not to set contentType.

		success: function () {
			button.text( plugin_upload_intl.updated );
		},
		error: function () {
			button.prop( 'disabled', false );
			cancel_overwrite_button.prop( 'disabled', false );
			button.text( plugin_upload_intl.activation_failed );
		},
	} );
}

function cancelOverwritePlugin( file_id, attachment_id, button ) {
	const formData = new FormData();
	formData.append( '_wpnonce', cancel_overwrite_nonce );
	formData.append( 'action', 'cancel-plugin-overwrite' );
	button.prop( 'disabled', true );
	button.text( plugin_upload_intl.processing + '...' );
	const overwrite_button = button.parent().parent().find( '.overwrite-plugin' );
	overwrite_button.prop( 'disabled', true );
	jQuery.ajax( {
		type: 'POST',
		url: ajaxurl + '?package=' + attachment_id,
		data: formData,
		processData: false, // Important: tell jQuery not to process the data.
		contentType: false, // Important: tell jQuery not to set contentType.

		success: function () {
			// Remove element from the dom.
			jQuery( '#plugin-item-' + file_id ).remove();
		},
		error: function () {
			button.prop( 'disabled', false );
			overwrite_button.prop( 'disabled', false );
			button.text( plugin_upload_intl.cancel_failed );
		},
	} );
}
function activatePlugin( path, name, button ) {
	const formData = new FormData();
	formData.append( 'plugin', path );
	formData.append( 'name', name );
	formData.append( 'slug', name.toLowerCase().replace( / /g, '-' ) );
	formData.append( '_wpnonce', activate_plugin_nonce );
	formData.append( 'action', 'activate-plugin' );
	button.prop( 'disabled', true );
	button.text( plugin_upload_intl.processing + '...' );
	jQuery.ajax( {
		type: 'POST',
		url: ajaxurl,
		data: formData,
		processData: false, // Important: tell jQuery not to process the data
		contentType: false, // Important: tell jQuery not to set contentType

		success: function () {
			button.text( plugin_upload_intl.activated );
		},
		error: function () {
			button.prop( 'disabled', false );
			button.text( plugin_upload_intl.failed );
		},
	} );
}
jQuery( function ( $ ) {
	const uploader_init = function () {
		const uploader = new plupload.Uploader( pluginUploaderInit );

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

				pluginFileQueued( file );
			} );

			up.refresh();
			up.start();
		} );

		uploader.bind( 'UploadProgress', function ( up, file ) {
			pluginUploadProgress( up, file );
		} );

		uploader.bind( 'Error', function ( up, error ) {
			pluginUploadError( error.file, error.code, error.response );
			up.refresh();
		} );

		uploader.bind( 'FileUploaded', function ( up, file, response ) {
			pluginUploadSuccess( file, response.response );
		} );
	};

	uploader_init();
} );
