/**
 * Warns users about unsaved changes on settings pages.
 *
 * @output wp-admin/js/settings.js
 * @since 7.1.0
 */

/* global wp */
( function( $ ) {
	var __ = wp.i18n.__;

	// Target only the main settings form, not search or other forms.
	var $form = $( 'form[action="options.php"]' );
	var originalData;
	var isSubmitting = false;

	/**
	 * Attaches the beforeunload listener. Called once on the first user
	 * change so that bfcache is not blocked on pages with no edits.
	 */
	function startWatchingForUnload() {
		// Remove this as a one-shot listener.
		$form.off( 'change.settings input.settings', startWatchingForUnload );

		$( window ).on( 'beforeunload.settings', function() {
			if ( ! isSubmitting && originalData !== $form.serialize() ) {
				return __( 'The changes you made will be lost if you navigate away from this page.' );
			}
		} );
	}

	$( function() {
		if ( ! $form.length ) {
			return;
		}

		// Snapshot the original form state.
		originalData = $form.serialize();

		// Suppress the warning when the form is intentionally submitted (settings saved).
		$form.on( 'submit.settings', function() {
			isSubmitting = true;
			$( window ).off( 'beforeunload.settings' );
		} );

		// Attach the beforeunload listener lazily on the first user interaction
		// to preserve bfcache for pages where no changes are made.
		$form.on( 'change.settings input.settings', startWatchingForUnload );
	} );
} )( jQuery );
