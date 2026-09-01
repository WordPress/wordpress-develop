/**
 * @output wp-admin/js/application-passwords.js
 */

/**
 * @param {JQueryStatic} $ The jQuery object.
 */
( function( $ ) {
	var $appPassSection = $( '#application-passwords-section' ),
		$newAppPassForm = $appPassSection.find( '.create-application-password' ),
		$newAppPassField = $newAppPassForm.find( '#new_application_password_name' ),
		$newAppPassExpiresField = $newAppPassForm.find( '#new_application_password_expires' ),
		$newAppPassButton = $newAppPassForm.find( '.button' ),
		$appPassTwrapper = $appPassSection.find( '.application-passwords-list-table-wrapper' ),
		$appPassTbody = $appPassSection.find( 'tbody' ),
		$appPassTrNoItems = $appPassTbody.find( '.no-items' ),
		$removeAllBtn = $( '#revoke-all-application-passwords' ),
		tmplNewAppPass = wp.template( 'new-application-password' ),
		tmplAppPassRow = wp.template( 'application-password-row' ),
		userId = $( '#user_id' ).val();

	$newAppPassButton.on( 'click', function( e ) {
		e.preventDefault();

		if ( $newAppPassButton.prop( 'aria-disabled' ) ) {
			return;
		}

		var name = $newAppPassField.val();

		if ( 0 === name.length ) {
			$newAppPassField.trigger( 'focus' );
			return;
		}

		clearNotices();
		$newAppPassButton.prop( 'aria-disabled', true ).addClass( 'disabled' );

		var request = {
			name: name
		};

		var expires = $newAppPassExpiresField.val();
		if ( expires ) {
		    var expiresDate = new Date( expires );

		    if ( ! isNaN( expiresDate.getTime() ) ) {
		        request.expires = expiresDate.toISOString();
		    }
		}

		/**
		 * Filters the request data used to create a new Application Password.
		 *
		 * @since 5.6.0
		 *
		 * @param {Object} request The request data.
		 * @param {number} userId  The id of the user the password is added for.
		 */
		request = wp.hooks.applyFilters( 'wp_application_passwords_new_password_request', request, userId );

		wp.apiRequest( {
			path: '/wp/v2/users/' + userId + '/application-passwords?_locale=user',
			method: 'POST',
			data: request
		} ).always( function() {
			$newAppPassButton.removeProp( 'aria-disabled' ).removeClass( 'disabled' );
		} ).done( function( response ) {
			$newAppPassField.val( '' );
			$newAppPassExpiresField.val( '' );
			$newAppPassButton.prop( 'disabled', false );

			$newAppPassForm.after( tmplNewAppPass( {
				name: response.name,
				password: response.password
			} ) );
			$( '.new-application-password-notice' ).attr( 'tabindex', '-1' ).trigger( 'focus' );

			$appPassTbody.prepend( tmplAppPassRow( response ) );

			$appPassTwrapper.show();
			$appPassTrNoItems.remove();

			/**
			 * Fires after an application password has been successfully created.
			 *
			 * @since 5.6.0
			 *
			 * @param {Object} response The response data from the REST API.
			 * @param {Object} request  The request data used to create the password.
			 */
			wp.hooks.doAction( 'wp_application_passwords_created_password', response, request );
		} ).fail( handleErrorResponse );
	});

	/**
	 * Handles the inline editing of an application password expiration date.
	 * * @since 7.1.0
	 */
	$appPassTbody.on( 'click', '.edit-expires', function( e ) {
		e.preventDefault();

		var $button = $( this ),
			$tr = $button.closest( 'tr' ),
			uuid = $tr.data( 'uuid' ),
			currentExpires = $tr.data( 'expires' ),
			$td = $button.closest( 'td' );

		if ( $td.find( '.edit-expires-form' ).length ) {
			return;
		}

		var $form = $( '<div class="edit-expires-form"></div>' );
		var $input = $( '<input type="date" class="edit-expires-input" />' );

		if ( currentExpires ) {
		    $input.val( currentExpires.split( 'T' )[0] );
		}

		var $buttonContainer = $( '<div class="edit-expires-button-group"></div>' );
		var $saveBtn = $( '<button type="button" class="button button-small button-primary">' + wp.i18n.__( 'Save' ) + '</button>' );
		var $cancelBtn = $( '<button type="button" class="button button-small">' + wp.i18n.__( 'Cancel' ) + '</button>' );

		$buttonContainer.append( $saveBtn ).append( $cancelBtn );
		$form.append( $input ).append( $buttonContainer );

		$td.append( $form );
		$button.hide();
		$input.trigger( 'focus' );

		// Close form on Escape key.
		$input.on( 'keydown', function( e ) {
			if ( 27 === e.which ) {
				$cancelBtn.trigger( 'click' );
			}
			if ( 13 === e.which ) {
				e.preventDefault();
				$saveBtn.trigger( 'click' );
			}
		});

		$cancelBtn.on( 'click', function() {
			$form.remove();
			$button.show().trigger( 'focus' );
		} );

		$saveBtn.on( 'click', function() {
			var newExpires = $input.val();
			var expiresDate = newExpires ? new Date( newExpires ) : null;
			var requestData = {
				expires: ( expiresDate && ! isNaN( expiresDate.getTime() ) ) ? expiresDate.toISOString() : null
			};

			clearNotices();
			$saveBtn.prop( 'disabled', true );
			$cancelBtn.prop( 'disabled', true );

			wp.apiRequest( {
				path: '/wp/v2/users/' + userId + '/application-passwords/' + uuid + '?_locale=user',
				method: 'PUT',
				data: JSON.stringify( requestData ),
				contentType: 'application/json'
			} ).always( function() {
				$saveBtn.prop( 'disabled', false );
				$cancelBtn.prop( 'disabled', false );
			} ).done( function( response ) {
				var $newRow = $( tmplAppPassRow( response ) );
				$tr.replaceWith( $newRow );
				$newRow.find( '.edit-expires' ).trigger( 'focus' );
				addNotice( wp.i18n.__( 'Application password expiration updated.' ), 'success' );
			} ).fail( handleErrorResponse );
		} );
	} );

	$appPassTbody.on( 'click', '.delete', function( e ) {
		e.preventDefault();

		if ( ! window.confirm( wp.i18n.__( 'Are you sure you want to revoke this password? This action cannot be undone.' ) ) ) {
			return;
		}

		var $submitButton = $( this ),
			$tr = $submitButton.closest( 'tr' ),
			uuid = $tr.data( 'uuid' );

		clearNotices();
		$submitButton.prop( 'disabled', true );

		wp.apiRequest( {
			path: '/wp/v2/users/' + userId + '/application-passwords/' + uuid + '?_locale=user',
			method: 'DELETE'
		} ).always( function() {
			$submitButton.prop( 'disabled', false );
		} ).done( function( response ) {
			if ( response.deleted ) {
				if ( 0 === $tr.siblings().length ) {
					$appPassTwrapper.hide();
				}
				$tr.remove();

				addNotice( wp.i18n.__( 'Application password revoked.' ), 'success' ).trigger( 'focus' );
			}
		} ).fail( handleErrorResponse );
	} );

	$removeAllBtn.on( 'click', function( e ) {
		e.preventDefault();

		if ( ! window.confirm( wp.i18n.__( 'Are you sure you want to revoke all passwords? This action cannot be undone.' ) ) ) {
			return;
		}

		var $submitButton = $( this );

		clearNotices();
		$submitButton.prop( 'disabled', true );

		wp.apiRequest( {
			path: '/wp/v2/users/' + userId + '/application-passwords?_locale=user',
			method: 'DELETE'
		} ).always( function() {
			$submitButton.prop( 'disabled', false );
		} ).done( function( response ) {
			if ( response.deleted ) {
				$appPassTbody.children().remove();
				$appPassSection.children( '.new-application-password' ).remove();
				$appPassTwrapper.hide();

				addNotice( wp.i18n.__( 'All application passwords revoked.' ), 'success' ).trigger( 'focus' );
			}
		} ).fail( handleErrorResponse );
	} );

	$appPassSection.on( 'click', '.notice-dismiss', function( e ) {
		e.preventDefault();
		var $el = $( this ).parent();
		$el.removeAttr( 'role' );
		$el.fadeTo( 100, 0, function () {
			$el.slideUp( 100, function () {
				$el.remove();
				$newAppPassField.trigger( 'focus' );
			} );
		} );
	} );

	$newAppPassField.on( 'keypress', function ( e ) {
		if ( 13 === e.which ) {
			e.preventDefault();
			$newAppPassButton.trigger( 'click' );
		}
	} );

	// If there are no items, don't display the table yet.  If there are, show it.
	if ( 0 === $appPassTbody.children( 'tr' ).not( $appPassTrNoItems ).length ) {
		$appPassTwrapper.hide();
	}

	/**
	 * Handles an error response from the REST API.
	 *
	 * @since 5.6.0
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

		addNotice( errorMessage, 'error' );
	}

	/**
	 * Displays a message in the Application Passwords section.
	 *
	 * @since 5.6.0
	 *
	 * @param {string} message The message to display.
	 * @param {string} type    The notice type. Either 'success' or 'error'.
	 * @return {jQuery} The notice element.
	 */
	function addNotice( message, type ) {
		var $notice = $( '<div></div>' )
			.attr( 'role', 'alert' )
			.attr( 'tabindex', '-1' )
			.addClass( 'is-dismissible notice notice-' + type )
			.append( $( '<p></p>' ).text( message ) )
			.append(
				$( '<button></button>' )
					.attr( 'type', 'button' )
					.addClass( 'notice-dismiss' )
					.append( $( '<span></span>' ).addClass( 'screen-reader-text' ).text( wp.i18n.__( 'Dismiss this notice.' ) ) )
			);

		$newAppPassForm.after( $notice );

		return $notice;
	}

	/**
	 * Clears notice messages from the Application Passwords section.
	 *
	 * @since 5.6.0
	 */
	function clearNotices() {
		$( '.notice', $appPassSection ).remove();
	}
}( jQuery ) );
