/**
 * @output wp-admin/js/auth-app.js
 */

/* global authApp, ClipboardJS */

( function( $, authApp ) {
	var $appNameField = $( '#app_name' ),
		$approveBtn = $( '#approve' ),
		$rejectBtn = $( '#reject' ),
		$form = $appNameField.closest( 'form' ),
		context = {
			userLogin: authApp.user_login,
			successUrl: authApp.success
		};

	// If redirecting to an external site, gate the approve button behind the confirmation checkbox.
	if ( authApp.successHost ) {
		var $checkbox = $( 'input[name="confirm_external_redirect"]' );

		// Start the approve button in a disabled state.
		$approveBtn.prop( 'aria-disabled', true ).addClass( 'disabled' );

		// Toggle the approve button when the checkbox state changes.
		$checkbox.on( 'change', function() {
			if ( $checkbox.prop( 'checked' ) ) {
				$approveBtn.removeProp( 'aria-disabled' ).removeClass( 'disabled' );
			} else {
				$approveBtn.prop( 'aria-disabled', true ).addClass( 'disabled' );
			}
		} );
	}

	$approveBtn.on( 'click', function( e ) {
		var name = $appNameField.val(),
			appId = $( 'input[name="app_id"]', $form ).val();

		e.preventDefault();

		if ( $approveBtn.prop( 'aria-disabled' ) ) {
			return;
		}

		if ( ! $form[ 0 ].checkValidity() ) {
			$form[ 0 ].reportValidity();
			return;
		}

		$approveBtn.prop( 'aria-disabled', true ).addClass( 'disabled' );

		var request = {
			name: name
		};

		if ( appId.length > 0 ) {
			request.app_id = appId;
		}

		/**
		 * Filters the request data used to Authorize an Application Password request.
		 *
		 * @since 5.6.0
		 * @since x.y.z A reject URL is no longer supported or used.
		 *
		 * @param {Object} request            The request data.
		 * @param {Object} context            Context about the Application Password request.
		 * @param {string} context.userLogin  The user's login username.
		 * @param {string} context.successUrl The URL the user will be redirected to after approving the request.
		 */
		request = wp.hooks.applyFilters( 'wp_application_passwords_approve_app_request', request, context );

		wp.apiRequest( {
			path: '/wp/v2/users/me/application-passwords?_locale=user',
			method: 'POST',
			data: request
		} ).done( function( response, textStatus, jqXHR ) {

			/**
			 * Fires when an Authorize Application Password request has been successfully approved.
			 *
			 * In most cases, this should be used in combination with the {@see 'wp_authorize_application_password_form_approved_no_js'}
			 * action to ensure that both the JS and no-JS variants are handled.
			 *
			 * @since 5.6.0
			 *
			 * @param {Object} response          The response from the REST API.
			 * @param {string} response.password The newly created password.
			 * @param {string} textStatus        The status of the request.
			 * @param {jqXHR}  jqXHR             The underlying jqXHR object that made the request.
			 */
			wp.hooks.doAction( 'wp_application_passwords_approve_app_request_success', response, textStatus, jqXHR );

			var raw = authApp.success,
				url, message, $notice;

			if ( raw ) {
				url = raw + ( -1 === raw.indexOf( '?' ) ? '?' : '&' ) +
					'site_url=' + encodeURIComponent( authApp.site_url ) +
					'&user_login=' + encodeURIComponent( authApp.user_login ) +
					'&password=' + encodeURIComponent( response.password );

				window.location = url;
			} else {
				message = wp.i18n.sprintf(
					/* translators: %s: Application name. */
					'<label for="new-application-password-value">' + wp.i18n.__( 'Your new password for %s is:' ) + '</label>',
					'<strong></strong>'
				);
				$notice = $( '<div></div>' )
					.attr( 'role', 'alert' )
					.attr( 'tabindex', -1 )
					.addClass( 'notice notice-success notice-alt' )
					.append( $( '<p></p>' ).addClass( 'application-password-display' ).html( message ) )
					.append(
						$( '<p></p>' )
							.addClass( 'application-password-display' )
							.append( '<input id="new-application-password-value" type="text" class="code" readonly="readonly" value="" />' )
							.append( ' <button type="button" class="button copy-button">' + wp.i18n.__( 'Copy' ) + '</button>' )
							.append( '<span class="success hidden" aria-hidden="true"> ' + wp.i18n.__( 'Copied!' ) + '</span>' )
					)
					.append( '<p>' + wp.i18n.__( 'Be sure to save this in a safe location. You will not be able to retrieve it.' ) + '</p>' );

				// We're using .text() to write the variables to avoid any chance of XSS.
				$( 'strong', $notice ).text( response.name );
				$( 'input', $notice ).val( response.password );
				$( '.copy-button', $notice ).attr( 'data-clipboard-text', response.password );

				$form.replaceWith( $notice );
				$notice.trigger( 'focus' );

				// Initialize clipboard functionality for the copy button.
				var clipboard = new ClipboardJS( '.copy-button' );
				clipboard.on( 'success', function( e ) {
					var $successElement = $( '.success', $( e.trigger ).parent() );

					e.clearSelection();
					$successElement.removeClass( 'hidden' );

					setTimeout( function() {
						$successElement.addClass( 'hidden' );
					}, 3000 );

					wp.a11y.speak( wp.i18n.__( 'Application password has been copied to your clipboard.' ) );
				} );
			}
		} ).fail( function( jqXHR, textStatus, errorThrown ) {
			var errorMessage = errorThrown,
				error = null;

			if ( jqXHR.responseJSON ) {
				error = jqXHR.responseJSON;

				if ( error.message ) {
					errorMessage = error.message;
				}
			}

			var $notice = $( '<div></div>' )
				.attr( 'role', 'alert' )
				.addClass( 'notice notice-error' )
				.append( $( '<p></p>' ).text( errorMessage ) );

			$( 'h1' ).after( $notice );

			$approveBtn.removeProp( 'aria-disabled', false ).removeClass( 'disabled' );

			/**
			 * Fires when an Authorize Application Password request encountered an error when trying to approve the request.
			 *
			 * @since 5.6.0
			 * @since 5.6.1 Corrected action name and signature.
			 *
			 * @param {Object|null} error       The error from the REST API. May be null if the server did not send proper JSON.
			 * @param {string}      textStatus  The status of the request.
			 * @param {string}      errorThrown The error message associated with the response status code.
			 * @param {jqXHR}       jqXHR       The underlying jqXHR object that made the request.
			 */
			wp.hooks.doAction( 'wp_application_passwords_approve_app_request_error', error, textStatus, errorThrown, jqXHR );
		} );
	} );

	$rejectBtn.on( 'click', function( e ) {
		e.preventDefault();

		/**
		 * Fires when an Authorize Application Password request has been rejected by the user.
		 *
		 * @since 5.6.0
		 * @since x.y.z A reject URL is no longer supported or used.
		 *
		 * @param {Object} context            Context about the Application Password request.
		 * @param {string} context.userLogin  The user's login username.
		 * @param {string} context.successUrl The URL the user will be redirected to after approving the request.
		 */
		wp.hooks.doAction( 'wp_application_passwords_reject_app', context );

		var $notice = $( '<div></div>' )
			.attr( 'role', 'alert' )
			.attr( 'tabindex', -1 )
			.addClass( 'notice notice-info' )
			.append( $( '<p></p>' ).text( wp.i18n.__( 'You have not approved this connection. No data has been shared with the application.' ) ) );

		$form.replaceWith( $notice );
		$notice.trigger( 'focus' );
	} );

	$form.on( 'submit', function( e ) {
		e.preventDefault();
	} );
}( jQuery, authApp ) );
