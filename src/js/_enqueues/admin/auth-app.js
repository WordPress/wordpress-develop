/**
 * @output wp-admin/js/auth-app.js
 */

/* global authApp */

( function( $, authApp ) {
	var $appNameField = $( '#app_name' ),
		$approveBtn = $( '#approve' ),
		$rejectBtn = $( '#reject' ),
		$form = $appNameField.closest( 'form' ),
		context = {
			userLogin: authApp.user_login,
			successUrl: authApp.success,
			rejectUrl: authApp.reject
		};

	$approveBtn.click( function( e ) {
		var name = $appNameField.val();

		e.preventDefault();

		if ( 0 === name.length ) {
			$appNameField.focus();
			return;
		}

		$appNameField.prop( 'disabled', true );
		$approveBtn.prop( 'disabled', true );

		var request = {
			name: name
		};

		/**
		 * Filters the request data used to Authorize an Application Password request.
		 *
		 * @since ?.?.?
		 *
		 * @param {Object} request            The request data.
		 * @param {Object} context            Context about the Application Password request.
		 * @param {string} context.userLogin  The user's login username.
		 * @param {string} context.successUrl The URL the user will be redirected to after approving the request.
		 * @param {string} context.rejectUrl  The URL the user will be redirected to after rejecting the request.
		 */
		request = wp.hooks.applyFilters( 'wp_application_passwords_approve_app_request', request, context );

		wp.apiRequest( {
			path: '/wp/v2/users/me/application-passwords',
			method: 'POST',
			data: request
		} ).done( function( response, textStatus, jqXHR ) {

			/**
			 * Fires when an Authorize Application Password request has been successfully approved.
			 *
			 * @since ?.?.?
			 *
			 * @param {Object} response          The response from the REST API.
			 * @param {string} response.password The newly created password.
			 * @param {string} textStatus        The status of the request.
			 * @param {jqXHR}  jqXHR             The underlying jqXHR object that made the request.
			 */
			wp.hooks.doAction( 'wp_application_passwords_approve_app_request_success', response, textStatus, jqXHR );

			var raw = authApp.success,
				url, $display;

			if ( raw ) {
				url = raw + ( -1 === raw.indexOf( '?' ) ? '?' : '&' ) +
					'user_login=' + encodeURIComponent( authApp.user_login ) +
					'&password=' + encodeURIComponent( response.password );

				window.location = url;
			} else {
				// Should we maybe just reuse the js template modal from the profile page?
				$form.replaceWith( '<p class="password-display">' +
					/* translators: 1: Application Name, 2: Password */
					wp.i18n.sprintf(
						wp.i18n.__( 'Your new password for %1$s is: %2$s.' ),
						'<strong></strong>',
						'<kbd></kbd>'
					) + '</p>' );

				$display = $( '.password-display' );

				// We're using .text() to write the variables to avoid any chance of XSS.
				$display.find( 'strong' ).text( name );
				$display.find( 'kbd' ).text( response.password );
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
				.addClass( 'notice notice-error' )
				.append( $( '<p></p>' ).text( errorMessage ) );

			$( 'h1' ).after( $notice );

			$appNameField.prop( 'disabled', false );
			$approveBtn.prop( 'disabled', false );

			/**
			 * Fires when an Authorize Application Password request encountered an error when trying to approve the request.
			 *
			 * @since ?.?.?
			 *
			 * @param {Object|null} error       The error from the REST API. May be null if the server did not send proper JSON.
			 * @param {string}      textStatus  The status of the request.
			 * @param {string}      errorThrown The error message associated with the response status code.
			 * @param {jqXHR}       jqXHR       The underlying jqXHR object that made the request.
			 */
			wp.hooks.doAction( 'wp_application_passwords_approve_app_request_success', error, textStatus, jqXHR );
		} );
	} );

	$rejectBtn.click( function( e ) {
		e.preventDefault();

		/**
		 * Fires when an Authorize Application Password request has been rejected by the user.
		 *
		 * @since ?.?.?
		 *
		 * @param {Object} context            Context about the Application Password request.
		 * @param {string} context.userLogin  The user's login username.
		 * @param {string} context.successUrl The URL the user will be redirected to after approving the request.
		 * @param {string} context.rejectUrl  The URL the user will be redirected to after rejecting the request.
		 */
		wp.hooks.doAction( 'wp_application_passwords_reject_app', context );

		// @todo: Make a better way to do this so it feels like less of a semi-open redirect.
		window.location = authApp.reject;
	} );

	$form.on( 'submit', function( e ) {
		e.preventDefault();
	} );
}( jQuery, authApp ) );
