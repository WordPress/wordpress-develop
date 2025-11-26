/**
 * @output wp-includes/js/wp-sanitize.js
 */

/* eslint-env es6 */

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
		 * @param {string} text - Text to strip the HTML tags from.
		 *
		 * @return {string} Stripped text.
		 */
		stripTags: function( text ) {
			let _text = text || '';

			const domParser = new DOMParser();
			const htmlDocument = domParser.parseFromString( _text, 'text/html' );
			htmlDocument.body.innerText = htmlDocument.body.innerText || '';

			// Return the text with stripped tags.
			return htmlDocument.body.innerHTML;
		},

		/**
		 * Strip HTML tags and convert HTML entities.
		 *
		 * @param {string} text - Text to strip tags and convert HTML entities.
		 *
		 * @return {string} Sanitized text.
		 */
		stripTagsAndEncodeText: function( text ) {
			let _text = wp.sanitize.stripTags( text ),
				textarea = document.createElement( 'textarea' );

			try {
				textarea.textContent = _text;
				_text = wp.sanitize.stripTags( textarea.value );
			} catch ( er ) {}

			return _text;
		}
	};
}() );
