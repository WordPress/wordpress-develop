/**
 * @output wp-admin/js/options.js
 */

/**
 * Detects unsaved changes on settings forms and warns users before navigating away.
 *
 * @since 7.0.0
 */
( function( $ ) {
	var $form,
		originalFormContent,
		isSubmitting = false,
		__ = wp.i18n.__;

	$( function() {
		// Target all settings forms on options pages.
		$form = $( 'form[action="options.php"]' );

		// Exit if no form is found.
		if ( ! $form.length ) {
			return;
		}

		// Store the original form state.
		originalFormContent = $form.serialize();

		// Track form submission to avoid false warnings.
		$form.on( 'submit', function() {
			isSubmitting = true;
		} );
	} );

	/**
	 * Warn the user if they have unsaved changes.
	 *
	 * The browser will show a native confirmation dialog when the user
	 * attempts to leave the page with unsaved changes.
	 */
	$( window ).on( 'beforeunload', function() {
		// Skip warning if form is being submitted or content hasn't changed.
		if ( isSubmitting || ! $form || ! $form.length ) {
			return;
		}

		if ( originalFormContent !== $form.serialize() ) {
			return __( 'The changes you made will be lost if you navigate away from this page.' );
		}
	} );

}( jQuery ) );
