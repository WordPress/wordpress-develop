/* global jQuery, QUnit */
jQuery( function( $ ) {
	'use strict';

	QUnit.module( 'user-profile' );

	QUnit.test( 'display name options exclude email login when data-is-email is true', function( assert ) {
		var $login    = $( '#user_login' ),
			$nickname = $( '#nickname' ),
			$select   = $( '#display_name' );

		assert.ok( $select.length, '#display_name select exists' );

		// Set an email username and trigger nickname blur.
		$login.attr( 'data-is-email', 'true' ).val( 'test.profile@example.com' );
		$nickname.val( 'CustomNick' ).trigger( 'blur.user_profile' );

		var options = $select.find( 'option' ).map( function() {
			return $( this ).val();
		} ).get();

		assert.ok( $.inArray( 'CustomNick', options ) !== -1, 'Custom nickname was added to display name options.' );
		assert.ok( $.inArray( 'test.profile@example.com', options ) === -1, 'Email username is excluded from display name options.' );
	} );

	QUnit.test( 'display name options include non-email username when data-is-email is false', function( assert ) {
		var $login    = $( '#user_login' ),
			$nickname = $( '#nickname' ),
			$select   = $( '#display_name' );

		// Clear data-is-email and test standard user.
		$login.removeAttr( 'data-is-email' ).val( 'standarduser' );
		$nickname.val( 'StandardNick' ).trigger( 'blur.user_profile' );

		var options = $select.find( 'option' ).map( function() {
			return $( this ).val();
		} ).get();

		assert.ok( $.inArray( 'StandardNick', options ) !== -1, 'Nickname was added to display name options.' );
		assert.ok( $.inArray( 'standarduser', options ) !== -1, 'Standard username is included in display name options.' );
	} );
} );
