/**
 * @output wp-admin/js/password-strength-meter.js
 */

/* global zxcvbn */
window.wp = window.wp || {};

(function($){

	/**
	 * Contains functions to determine the password strength.
	 *
	 * @since 3.7.0
	 *
	 * @namespace
	 */
	wp.passwordStrength = {
		/**
		 * Determines the strength of a given password.
		 *
		 * Compares first password to the password confirmation.
		 *
		 * @since 3.7.0
		 *
		 * @param {string} password1 The subject password.
		 * @param {Array}  blocklist An array of words that will lower the entropy of
		 *                           the password.
		 * @param {string} password2 The password confirmation.
		 *
		 * @return {number} The password strength score.
		 */
		meter : function( password1, blocklist, password2 ) {
			if ( ! $.isArray( blocklist ) )
				blocklist = [ blocklist.toString() ];

			if (password1 != password2 && password2 && password2.length > 0)
				return 5;

			if ( 'undefined' === typeof window.zxcvbn ) {
				// Password strength unknown.
				return -1;
			}

			var result = zxcvbn( password1, blocklist );
			return result.score;
		},

		/**
		 * Builds an array of words that should be penalized.
		 *
		 * Certain words need to be penalized because it would lower the entropy of a
		 * password if they were used. The blocklist is based on user input fields such
		 * as username, first name, email etc.
		 *
		 * @since 3.7.0
		 *
		 * @return {string[]} The array of words to be blocklisted.
		 */
		userInputBlocklist : function() {
			var i, userInputFieldsLength, rawValuesLength, currentField,
				rawValues       = [],
				blocklist       = [],
				userInputFields = [ 'user_login', 'first_name', 'last_name', 'nickname', 'display_name', 'email', 'url', 'description', 'weblog_title', 'admin_email' ];

			// Collect all the strings we want to blocklist.
			rawValues.push( document.title );
			rawValues.push( document.URL );

			userInputFieldsLength = userInputFields.length;
			for ( i = 0; i < userInputFieldsLength; i++ ) {
				currentField = $( '#' + userInputFields[ i ] );

				if ( 0 === currentField.length ) {
					continue;
				}

				rawValues.push( currentField[0].defaultValue );
				rawValues.push( currentField.val() );
			}

			/*
			 * Strip out non-alphanumeric characters and convert each word to an
			 * individual entry.
			 */
			rawValuesLength = rawValues.length;
			for ( i = 0; i < rawValuesLength; i++ ) {
				if ( rawValues[ i ] ) {
					blocklist = blocklist.concat( rawValues[ i ].replace( /\W/g, ' ' ).split( ' ' ) );
				}
			}

			/*
			 * Remove empty values, short words and duplicates. Short words are likely to
			 * cause many false positives.
			 */
			blocklist = $.grep( blocklist, function( value, key ) {
				if ( '' === value || 4 > value.length ) {
					return false;
				}

				return $.inArray( value, blocklist ) === key;
			});

			return blocklist;
		}
	};

	// Backward compatibility.

	/**
	 * Password strength meter function.
	 *
	 * @since 2.5.0
	 * @deprecated 3.7.0 Use wp.passwordStrength.meter instead.
	 *
	 * @global
	 *
	 * @type {wp.passwordStrength.meter}
	 */
	window.passwordStrength = wp.passwordStrength.meter;
})(jQuery);
