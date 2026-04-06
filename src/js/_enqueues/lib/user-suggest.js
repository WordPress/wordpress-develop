/**
 * Suggests users in site admin and multisite environments.
 *
 * For input fields where the admin can select a user by searching their name,
 * login, or email, this script shows an autocompletion menu. On multisite,
 * only users in the currently active site are shown for 'search' type requests.
 *
 * @since 3.4.0
 * @output wp-admin/js/user-suggest.js
 */

/* global ajaxurl, current_site_id, isRtl */

(function( $ ) {
	var id = ( typeof current_site_id !== 'undefined' ) ? '&site_id=' + current_site_id : '';
	$( function() {
		var position = { offset: '0, -1' };
		if ( typeof isRtl !== 'undefined' && isRtl ) {
			position.my = 'right top';
			position.at = 'right bottom';
		}

		/**
		 * Adds an autocomplete function to input fields marked with the class
		 * 'wp-suggest-user'.
		 *
		 * A minimum of two characters is required to trigger the suggestions. The
		 * autocompletion menu is shown at the left bottom of the input field. On
		 * RTL installations, it is shown at the right top. Adds the class 'open' to
		 * the input field when the autocompletion menu is shown.
		 *
		 * Does a backend call to retrieve the users.
		 *
		 * Optional data-attributes:
		 * - data-autocomplete-type (add, search)
		 *   The action that is going to be performed: search for existing users
		 *   or add a new one. Default: add
		 * - data-autocomplete-field (user_login, user_email, user_id)
		 *   The field that is returned as the value for the suggestion.
		 *   When set to 'user_id', the input is expected to have an adjacent
		 *   '.wp-suggest-user-helper' hidden input that stores the numeric ID
		 *   while the visible input shows the display label.
		 *   Default: user_login
		 * - data-autocomplete-label
		 *   A template string with {{tokens}} to build each result's display label.
		 *   Supported tokens: {{user_login}}, {{user_email}}, {{display_name}}, {{user_id}}.
		 *   Default: empty (server returns display_name).
		 *
		 * @see wp-admin/includes/ajax-actions.php:wp_ajax_autocomplete_user()
		 */
		$( '.wp-suggest-user' ).each( function() {
			var $this           = $( this ),
				autocompleteType  = ( typeof $this.data( 'autocompleteType' )  !== 'undefined' ) ? $this.data( 'autocompleteType' )  : 'add',
				autocompleteField = ( typeof $this.data( 'autocompleteField' ) !== 'undefined' ) ? $this.data( 'autocompleteField' ) : 'user_login',
				autocompleteLabel = ( typeof $this.data( 'autocompleteLabel' ) !== 'undefined' ) ? $this.data( 'autocompleteLabel' ) : '',
				// True when using user_id field with a sibling helper input.
				hasHelper = ( 'user_id' === autocompleteField && $this.next( '.wp-suggest-user-helper' ).length > 0 );

			$this.autocomplete({
				source:    ajaxurl + '?action=autocomplete-user&autocomplete_type=' + autocompleteType + '&autocomplete_field=' + autocompleteField + '&autocomplete_label=' + encodeURIComponent( autocompleteLabel ) + id,
				delay:     500,
				minLength: 2,
				position:  position,
				open: function() {
					$( this ).addClass( 'open' );
				},
				close: function() {
					$( this ).removeClass( 'open' );
				},
				focus: function( e, ui ) {
					if ( hasHelper ) {
						// Show the display label while navigating, not the raw ID value.
						$( this ).val( ui.item.label );
						return false;
					}
				},
				select: function( e, ui ) {
					if ( hasHelper ) {
						// Store the user ID in the hidden helper; show the label in the text input.
						$( this ).next( '.wp-suggest-user-helper' ).val( ui.item.value );
						$( this ).val( ui.item.label );
						return false;
					}
				}
			});
		});
	});
})( jQuery );
