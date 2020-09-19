/**
 * @output wp-admin/js/auth-app.js
 */

/* global authApp */

( function( $, authApp ) {
	var $appNameField = $( '#app_name' ),
		$approveBtn = $( '#approve' ),
		$rejectBtn = $( '#reject' ),
		$form = $appNameField.closest( 'form' );

	$approveBtn.click( function( e ) {
		var name = $appNameField.val();

		e.preventDefault();

		if ( 0 === name.length ) {
			$appNameField.focus();
			return;
		}

		$appNameField.prop( 'disabled', true );
		$approveBtn.prop( 'disabled', true );

		wp.apiRequest( {
			path: '/wp/v2/users/me/application-passwords',
			method: 'POST',
			data: {
				name: name
			}
		} ).done( function( response ) {
			var raw = authApp.success,
				url, $display;

			if ( raw ) {
				url = raw + ( -1 === raw.indexOf( '?' ) ? '?' : '&' ) +
					'user_login=' + encodeURIComponent( authApp.user_login ) +
					'&password=' + encodeURIComponent( response.password );

				window.location = url;
			} else {
				// Should we maybe just reuse the js template modal from the profile page?
				$form.replaceWith( '<p class="js-password-display">' +
					/* translators: 1: Application Name, 2: Password */
					wp.i18n.sprintf(
						wp.i18n.__( 'Your new password for %1$s is: %2$s' ),
						'<strong></strong>',
						'<kbd></kbd>'
					) + '</p>' );

				$display = $( '.js-password-display' );

				// We're using .text() to write the variables to avoid any chance of XSS.
				$display.find( 'strong' ).text( name );
				$display.find( 'kbd' ).text( response.password );
			}
		} );
	} );

	$rejectBtn.click( function( e ) {
		e.preventDefault();

		// @todo: Make a better way to do this so it feels like less of a semi-open redirect.
		window.location = authApp.reject;
	} );

	$form.on( 'submit', function( e ) {
		e.preventDefault();
	} );
}( jQuery, authApp ) );
