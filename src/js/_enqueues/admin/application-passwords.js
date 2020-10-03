/**
 * @output wp-admin/js/application-passwords.js
 */

( function( $ ) {
	var $appPassSection = $( '#application-passwords-section' ),
		$newAppPassForm = $appPassSection.find( '.create-application-password' ),
		$newAppPassField = $newAppPassForm.find( '.input' ),
		$newAppPassButton = $newAppPassForm.find( '.button' ),
		$appPassTwrapper = $appPassSection.find( '.application-passwords-list-table-wrapper' ),
		$appPassTbody = $appPassSection.find( 'tbody' ),
		$appPassTrNoItems = $appPassTbody.find( '.no-items' ),
		$removeAllBtn = $( '#revoke-all-application-passwords' ),
		tmplNewAppPass = wp.template( 'new-application-password' ),
		tmplAppPassRow = wp.template( 'application-password-row' ),
		dateFormat = wp.date.__experimentalGetSettings().formats.date,
		userId = $( '#user_id' ).val();

	$newAppPassButton.click( function( e ) {
		e.preventDefault();
		var name = $newAppPassField.val();

		if ( 0 === name.length ) {
			$newAppPassField.focus();
			return;
		}

		clearErrors();
		$newAppPassField.prop( 'disabled', true );
		$newAppPassButton.prop( 'disabled', true );

		wp.apiRequest( {
			path: '/wp/v2/users/' + userId + '/application-passwords',
			method: 'POST',
			data: {
				name: name
			}
		} ).always( function() {
			$newAppPassField.prop( 'disabled', false );
			$newAppPassButton.prop( 'disabled', false );
		} ).done( function( response ) {
			$newAppPassField.val( '' );
			$newAppPassButton.prop( 'disabled', false );

			$newAppPassForm.after( tmplNewAppPass( {
				name: name,
				password: response.password
			} ) );

			$appPassTbody.prepend( tmplAppPassRow( {
				name: response.name,
				uuid: response.uuid,
				created: wp.date.dateI18n( dateFormat, response.created ),
				last_used: response.last_used ? wp.date.dateI18n( dateFormat, response.last_used ) : '—',
				last_ip: response.last_ip ? response.last_ip : '—'
			} ) );

			$appPassTwrapper.show();
			$appPassTrNoItems.remove();
		} ).fail( handleErrorResponse );
	} );

	$appPassTbody.on( 'click', '.delete', function( e ) {
		e.preventDefault();

		if ( ! confirm( wp.i18n.__( 'Are you sure you want to revoke this password? This action cannot be undone.' ) ) ) {
			return;
		}

		var $submitButton = $( this ),
			$tr = $submitButton.closest( 'tr' ),
			uuid = $tr.data( 'uuid' );

		clearErrors();
		$submitButton.prop( 'disabled', true );

		wp.apiRequest( {
			path: '/wp/v2/users/' + userId + '/application-passwords/' + uuid,
			method: 'DELETE'
		} ).always( function() {
			$submitButton.prop( 'disabled', false );
		} ).done( function( response ) {
			if ( response.deleted ) {
				if ( 0 === $tr.siblings().length ) {
					$appPassTwrapper.hide();
				}
				$tr.remove();
			}
		} ).fail( handleErrorResponse );
	} );

	$removeAllBtn.on( 'click', function( e ) {
		e.preventDefault();

		if ( ! confirm( wp.i18n.__( 'Are you sure you want to revoke all passwords? This action cannot be undone.' ) ) ) {
			return;
		}

		var $submitButton = $( this );

		clearErrors();
		$submitButton.prop( 'disabled', true );

		wp.apiRequest( {
			path: '/wp/v2/users/' + userId + '/application-passwords',
			method: 'DELETE'
		} ).always( function() {
			$submitButton.prop( 'disabled', false );
		} ).done( function( response ) {
			if ( response.deleted ) {
				$appPassTbody.children().remove();
				$appPassSection.children( '.new-application-password' ).remove();
				$appPassTwrapper.hide();
			}
		} ).fail( handleErrorResponse );
	} );

	$( document ).on( 'click', '.application-password-modal-dismiss', function( e ) {
		e.preventDefault();

		$( '.new-application-password.notification-dialog-wrap' ).hide();
	} );

	// If there are no items, don't display the table yet.  If there are, show it.
	if ( 0 === $appPassTbody.children( 'tr' ).not( $appPassTrNoItems ).length ) {
		$appPassTwrapper.hide();
	}

	/**
	 * Handles an error response from the REST API.
	 *
	 * @since ?.?.0
	 *
	 * @param {jqXHR} xhr The XHR object from the ajax call.
	 * @param {string} textStatus The string categorizing the ajax request's status.
	 * @param {string} errorThrown The HTTP status error text.
	 */
	function handleErrorResponse( xhr, textStatus, errorThrown ) {
		var errorMessage = errorThrown;

		if ( xhr.responseJSON && xhr.responseJSON.message ) {
			errorMessage = xhr.responseJSON.message;
		}

		addError( errorMessage );
	}

	/**
	 * Displays an error message in the Application Passwords section.
	 *
	 * @since ?.?.0
	 *
	 * @param {string} message The error message to display.
	 */
	function addError( message ) {
		var $notice = $( '<div></div>' )
			.addClass( 'notice notice-error' )
			.append( $( '<p></p>' ).text( message ) );

		$newAppPassForm.after( $notice );
	}

	/**
	 * Clears error messages from the Application Passwords section.
	 *
	 * @since ?.?.0
	 */
	function clearErrors() {
		$( '.notice', $appPassSection ).remove();
	}
}( jQuery ) );
