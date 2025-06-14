/**
 * @output wp-includes/js/wp-sanitize.js
 */

( function () {

	window.wp = window.wp || {};

	/**
	 * wp.sanitize
	 *
	 * Helper functions to sanitize strings.
	 */
	wp.sanitize = {

		/**
		 * Strip HTML tags.
		 *
		 * @param {string} text Text to strip the HTML tags from.
		 *
		 * @return  Stripped text.
		 */
		stripTags: function( text ) {
			var _text = text || '';

			 // Do the search-replace until there is nothing to be replaced.
			 do {
                // Keep pre-replace text for comparison.
				text = _text;

				// Do the replacement.
				_text = text
					.replace( /<!--[\s\S]*?(-->|$)/g, '' )
					.replace( /<(script|style)[^>]*>[\s\S]*?(<\/\1>|$)/ig, '' )
					.replace( /<\/?[a-z][\s\S]*?(>|$)/ig, '' );
			} while ( _text !== text );

			// Return the text with stripped tags.
			return _text;
		},

		/**
		 * Strip HTML tags and convert HTML entities.
		 *
		 * @param {string} text Text to strip tags and convert HTML entities.
		 *
		 * @return Sanitized text. False on failure.
		 */
		stripTagsAndEncodeText: function( text ) {
			var _text = wp.sanitize.stripTags( text ),
				textarea = document.createElement( 'textarea' );

			try {
				textarea.textContent = _text;
				_text = wp.sanitize.stripTags( textarea.value );
			} catch ( er ) {}

			return _text;
		}
	};
}() );
