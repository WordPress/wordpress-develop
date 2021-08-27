/* global twentytwentyoneIsDarkMode, setTimeout */

// Check the color scheme preference and inject the classes if necessary.
if ( document.body.classList.contains( 'twentytwentyone-supports-dark-theme' ) ) {
	twentytwentyoneDarkModeEditorInit();
}

/**
 * Once the editor loads, add the dark mode class.
 *
 * Wait for the editor to load by periodically checking for an element, then we add the classes.
 *
 * @since Twenty Twenty-One 1.0
 *
 * @param {number} attempt Track the number of tries
 * @return {void}
 */
function twentytwentyoneDarkModeEditorInit( attempt ) {
	var container = document.querySelector( '.block-editor__typewriter' ),
		template_editor_container = document.querySelector( '[name=editor-canvas]' ),
		maxAttempts = 8;

	// Set the initial attempt if it's undefined.
	attempt = attempt || 0;

	if ( twentytwentyoneIsDarkMode() ) {
		if ( null === container && null === template_editor_container ) {
			// Try again.
			if ( attempt < maxAttempts ) {
				setTimeout(
					function() {
						twentytwentyoneDarkModeEditorInit( attempt + 1 );
					},
					// Double the delay, give the server some time to breathe.
					25 * Math.pow( 2, attempt )
				);
			}
			return;
		}

		document.body.classList.add( 'is-dark-theme' );
		document.documentElement.classList.add( 'is-dark-theme' );
		if ( container) {
			container.classList.add( 'is-dark-theme' );
		}
		if ( template_editor_container ) {
			var template_editor_body = template_editor_container.contentWindow.document.querySelector( '.editor-styles-wrapper' );
			template_editor_body ? template_editor_body.classList.add( 'is-dark-theme' ) : '';
		}
	}
}
