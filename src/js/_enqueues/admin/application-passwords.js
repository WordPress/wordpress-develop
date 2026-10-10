/**
 * @output wp-admin/js/application-passwords.js
 */

/**
 * Handles the Application Passwords functionality in the user profile screen.
 *
 * @param {JQueryStatic} $ The jQuery object.
 */
( function( $ ) {
	var $appPassSection = $( '#application-passwords-section' ),
		$newAppPassForm = $appPassSection.find( '.create-application-password' ),
		$newAppPassField = $newAppPassForm.find( '#new_application_password_name' ),
		$newAppPassPresetField = $newAppPassForm.find( '#new_application_password_expiration_preset' ),
		$newAppPassCustomWrap = $newAppPassForm.find( '#new_application_password_custom_expires_wrap' ),
		$newAppPassExpiresField = $newAppPassForm.find( '#new_application_password_expires' ),
		$newAppPassButton = $newAppPassForm.find( '.button' ),
		$appPassTwrapper = $appPassSection.find( '.application-passwords-list-table-wrapper' ),
		$appPassTbody = $appPassSection.find( 'tbody' ),
		$appPassTrNoItems = $appPassTbody.find( '.no-items' ),
		$removeAllBtn = $( '#revoke-all-application-passwords' ),
		tmplNewAppPass = wp.template( 'new-application-password' ),
		tmplAppPassRow = wp.template( 'application-password-row' ),
		userId = $( '#user_id' ).val(),
		settings = window.wpApplicationPasswordsSettings || {};

	$newAppPassPresetField.on( 'change', function() {
		if ( 'custom' === $( this ).val() ) {
			$newAppPassCustomWrap.removeClass( 'hidden' );
			$newAppPassExpiresField.trigger( 'focus' );
		} else {
			$newAppPassCustomWrap.addClass( 'hidden' );
			$newAppPassExpiresField.val( '' );
		}
	} );

	/**
	 * Calculates the ISO string for expiration based on selected preset or custom date.
	 *
	 * Expiration is set to 23:59:59 of the target day in the site's local timezone.
	 *
	 * @since 7.2.0
	 * @return {string|null} The ISO 8601 date string, or null if no expiration or invalid.
	 */
	function calculateExpiresIso() {
		var preset = $newAppPassPresetField.val(),
			days = 0,
			baseDateIso = settings.todayEndIso,
			baseDate;

		if ( 'no_expiry' === preset ) {
			return null;
		}

		if ( 'custom' === preset ) {
			var customVal = $newAppPassExpiresField.val();
			if ( ! customVal ) {
				return null;
			}

			// Format custom date string into ISO for end of day in site timezone.
			var siteTimezone = wp.date.getSettings().timezone;
			var offsetString = '+00:00';
			if ( siteTimezone && siteTimezone.offsetFormatted ) {
				offsetString = siteTimezone.offsetFormatted;
				// Format to standard ISO offset like +06:00 or -05:00.
				if ( ! /^[-+]\d{2}:\d{2}$/.test( offsetString ) ) {
					var numOffset = parseFloat( siteTimezone.offset || 0 );
					var sign = numOffset >= 0 ? '+' : '-';
					var absH = Math.floor( Math.abs( numOffset ) );
					var absM = Math.round( ( Math.abs( numOffset ) - absH ) * 60 );
					offsetString = sign + ( '0' + absH ).slice( -2 ) + ':' + ( '0' + absM ).slice( -2 );
				}
			}

			var customIsoDate = new Date( customVal + 'T23:59:59' + offsetString );
			if ( ! isNaN( customIsoDate.getTime() ) ) {
				return customIsoDate.toISOString();
			}

			return null;
		}

		if ( '7_days' === preset ) {
			days = 7;
		} else if ( '30_days' === preset ) {
			days = 30;
		} else if ( '90_days' === preset ) {
			days = 90;
		}

		if ( days > 0 ) {
			baseDate = baseDateIso ? new Date( baseDateIso ) : new Date();
			baseDate.setUTCDate( baseDate.getUTCDate() + days );
			return baseDate.toISOString();
		}

		return null;
	}

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

		var expiresIso = calculateExpiresIso();
		if ( expiresIso ) {
			request.expires = expiresIso;
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
			$newAppPassPresetField.val( '30_days' );
			$newAppPassCustomWrap.addClass( 'hidden' );
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
	 * @param {jqXHR}  xhr         The XHR object from the ajax call.
	 * @param {string} textStatus  The string categorizing the ajax request's status.
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
