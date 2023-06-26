/**
 * @output wp-includes/js/wp-image-mime-fallback-loader.js
 */

( function( document, settings ) {

	// Base64 representation of a WebP image.
	var data  = 'data:image/webp;base64,UklGR';
	var image = document.createElement( 'img' );

	// Lossless representation of a white point image.
	image.src = data + 'jIAAABXRUJQVlA4ICYAAACyAgCdASoCAAEALmk0mk0iIiIiIgBoSygABc6zbAAA/v56QAAAAA==';
	image.onload = function() {
		image.onload = undefined;
		// lossy representation of a white point image.
		image.src = data + 'h4AAABXRUJQVlA4TBEAAAAvAQAAAAfQ//73v/+BiOh/AAA=';
	};
	image.onerror = function() {
		var script_src = settings.imageMimeFallbackScript;
		if ( script_src ) {
			var script = document.createElement( 'script' );

			script.src = script_src;
			script.type = 'text/javascript';

			document.body.appendChild( script );
		}
	};
} )( document, window._wpImageMimeFallbackSettings );
