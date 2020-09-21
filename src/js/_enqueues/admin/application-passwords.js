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

		$newAppPassField.prop( 'disabled', true );
		$newAppPassButton.prop( 'disabled', true );

		wp.apiRequest( {
			path: '/wp/v2/users/' + userId + '/application-passwords',
			method: 'POST',
			data: {
				name: name
			}
		} ).done( function( response ) {
			$newAppPassField.prop( 'disabled', false ).val( '' );
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
		} );
	} );

	$appPassTbody.on( 'click', '.delete', function( e ) {
		e.preventDefault();
		var $tr = $( e.target ).closest( 'tr' ),
			uuid = $tr.data( 'uuid' );

		wp.apiRequest( {
			path: '/wp/v2/users/' + userId + '/application-passwords/' + uuid,
			method: 'DELETE'
		} ).done( function( response ) {
			if ( response.deleted ) {
				if ( 0 === $tr.siblings().length ) {
					$appPassTwrapper.hide();
				}
				$tr.remove();
			}
		} );
	} );

	$removeAllBtn.on( 'click', function( e ) {
		e.preventDefault();

		wp.apiRequest( {
			path: '/wp/v2/users/' + userId + '/application-passwords',
			method: 'DELETE'
		} ).done( function( response ) {
			if ( response.deleted ) {
				$appPassTbody.children().remove();
				$appPassSection.children( '.new-application-password' ).remove();
				$appPassTwrapper.hide();
			}
		} );
	} );

	$( document ).on( 'click', '.application-password-modal-dismiss', function( e ) {
		e.preventDefault();

		$( '.new-application-password.notification-dialog-wrap' ).hide();
	} );

	// If there are no items, don't display the table yet.  If there are, show it.
	if ( 0 === $appPassTbody.children( 'tr' ).not( $appPassTrNoItems ).length ) {
		$appPassTwrapper.hide();
	}
}( jQuery ) );
