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

		$.ajax( {
			url: authApp.root + authApp.namespace + '/application-passwords/' + authApp.user_id + '/add',
			method: 'POST',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', authApp.nonce );
			},
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
					authApp.strings.new_pass
						.replace( '%1$s', '<strong></strong>' )
						.replace( '%2$s', '<kbd></kbd>' ) +
					'</p>' );

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
