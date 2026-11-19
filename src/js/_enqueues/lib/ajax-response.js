/**
 * Handles and displays the responses of admin Ajax requests.
 *
 * Parses the XML responses created by the WP_Ajax_Response PHP class and
 * provides basic form validation helpers.
 *
 * @since 2.5.0
 *
 * @output wp-includes/js/wp-ajax-response.js
 */

 /* global wpAjax */

/**
 * Helper functions to handle the responses of admin Ajax requests.
 *
 * The `noPerm` and `broken` error messages are localized from PHP.
 *
 * @since 2.5.0
 *
 * @namespace wpAjax
 */
window.wpAjax = jQuery.extend( {
	/**
	 * Converts a query string into an object of key/value pairs.
	 *
	 * If a full URL is passed, only the part after the question mark is used.
	 * The values are not URL-decoded.
	 *
	 * @since 2.5.0
	 *
	 * @param {string} s The query string or URL to convert.
	 * @return {Object} The key/value pairs of the query string.
	 */
	unserialize: function( s ) {
		var r = {}, q, pp, i, p;
		if ( !s ) { return r; }
		q = s.split('?'); if ( q[1] ) { s = q[1]; }
		pp = s.split('&');
		for ( i in pp ) {
			if ( typeof pp.hasOwnProperty === 'function' && !pp.hasOwnProperty(i) ) { continue; }
			p = pp[i].split('=');
			r[p[0]] = p[1];
		}
		return r;
	},
	/**
	 * Parses the response of an admin Ajax request and displays its messages.
	 *
	 * An XML response created by the WP_Ajax_Response PHP class is parsed into
	 * an object. Its error or notice messages are shown in the response element
	 * and announced to screen readers. A numeric response of -1 means the user
	 * lacks permission, and 0 means the request failed. Any other non-numeric
	 * response is shown as an error message.
	 *
	 * @since 2.5.0
	 *
	 * @param {(XMLDocument|string|number)} x   The response of the Ajax request.
	 * @param {string}                      r   The ID of the element where the messages are displayed.
	 * @param {string}                      [e] Optional. The ID of the element containing the fields
	 *                                          to mark as invalid when the response contains errors.
	 * @return {(Object|boolean)} The parsed response for an XML response. For other responses,
	 *                            false when an error was displayed, true otherwise.
	 */
	parseAjaxResponse: function( x, r, e ) {
		var parsed = {}, re = jQuery('#' + r).empty(), err = '', noticeMessage = '';

		if ( x && typeof x === 'object' && x.getElementsByTagName('wp_ajax') ) {
			parsed.responses = [];
			parsed.errors = false;
			jQuery('response', x).each( function() {
				var th = jQuery(this), child = jQuery(this.firstChild), response;
				response = { action: th.attr('action'), what: child.get(0).nodeName, id: child.attr('id'), oldId: child.attr('old_id'), position: child.attr('position') };
				response.data = jQuery( 'response_data', child ).text();
				response.supplemental = {};
				if ( !jQuery( 'supplemental', child ).children().each( function() {

					if ( this.nodeName === 'notice' ) {
						noticeMessage += jQuery(this).text();
						return;
					}

					response.supplemental[this.nodeName] = jQuery(this).text();
				} ).length ) { response.supplemental = false; }
				response.errors = [];
				if ( !jQuery('wp_error', child).each( function() {
					var code = jQuery(this).attr('code'), anError, errorData, formField;
					anError = { code: code, message: this.firstChild.nodeValue, data: false };
					errorData = jQuery('wp_error_data[code="' + code + '"]', x);
					if ( errorData ) { anError.data = errorData.get(); }
					formField = jQuery( 'form-field', errorData ).text();
					if ( formField ) { code = formField; }
					if ( e ) { wpAjax.invalidateForm( jQuery('#' + e + ' :input[name="' + code + '"]' ).parents('.form-field:first') ); }
					err += '<p>' + anError.message + '</p>';
					response.errors.push( anError );
					parsed.errors = true;
				} ).length ) { response.errors = false; }
				parsed.responses.push( response );
			} );
			if ( err.length ) {
				re.html( '<div class="notice notice-error" role="alert">' + err + '</div>' );
				wp.a11y.speak( err );
			} else if ( noticeMessage.length ) {
				re.html( '<div class="notice notice-success is-dismissible" role="alert"><p>' + noticeMessage + '</p></div>');
				jQuery(document).trigger( 'wp-updates-notice-added' );
				wp.a11y.speak( noticeMessage );
			}
			return parsed;
		}
		if ( isNaN( x ) ) {
			wp.a11y.speak( x );
			return ! re.html( '<div class="notice notice-error" role="alert"><p>' + x + '</p></div>' );
		}
		x = parseInt( x, 10 );
		if ( -1 === x ) {
			wp.a11y.speak( wpAjax.noPerm );
			return ! re.html( '<div class="notice notice-error" role="alert"><p>' + wpAjax.noPerm + '</p></div>' );
		} else if ( 0 === x ) {
			wp.a11y.speak( wpAjax.broken );
			return ! re.html( '<div class="notice notice-error" role="alert"><p>' + wpAjax.broken  + '</p></div>' );
		}
		return true;
	},
	/**
	 * Marks form fields as invalid.
	 *
	 * Adds the `form-invalid` class to the matched elements. The class is
	 * removed as soon as one of their inputs changes.
	 *
	 * @since 2.5.0
	 *
	 * @param {(string|jQuery)} selector The form fields to mark as invalid.
	 * @return {jQuery} The inputs inside the invalid form fields.
	 */
	invalidateForm: function ( selector ) {
		return jQuery( selector ).addClass( 'form-invalid' ).find('input').one( 'change wp-check-valid-field', function() { jQuery(this).closest('.form-invalid').removeClass( 'form-invalid' ); } );
	},
	/**
	 * Validates the required fields of a form.
	 *
	 * Field wrappers with the `form-required` class whose first visible input is
	 * empty are marked as invalid.
	 *
	 * @since 2.5.0
	 *
	 * @param {(string|jQuery)} selector The form to validate.
	 * @return {boolean} True if all the required fields have a value, false otherwise.
	 */
	validateForm: function( selector ) {
		selector = jQuery( selector );
		return !wpAjax.invalidateForm( selector.find('.form-required').filter( function() { return jQuery('input:visible', this).val() === ''; } ) ).length;
	}
}, wpAjax || { noPerm: 'Sorry, you are not allowed to do that.', broken: 'An error occurred while processing your request. Please refresh the page and try again.' } );

// Basic form validation.
jQuery( function($){
	$('form.validate').on( 'submit', function() { return wpAjax.validateForm( $(this) ); } );
});
