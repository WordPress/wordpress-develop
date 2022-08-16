/**
 * @output wp-includes/js/wp-image-mime-fallback-loader.js
 */

( function( document, image_src, settings ) {

	var image = document.createElement( 'image' );
	image.src = image_src + 'jIAAABXRUJQVlA4ICYAAACyAgCdASoCAAEALmk0mk0iIiIiIgBoSygABc6zbAAA/v56QAAAAA==';
	image.onload = function() {
		image.onload = undefined;
		image.src = image_src + 'h4AAABXRUJQVlA4TBEAAAAvAQAAAAfQ//73v/+BiOh/AAA=';
	};
	image.onerror = function() {
		var script_src = settings.imagemimefallback || {};
		if ( script_src ) {
			var script = document.createElement( 'script' );

			script.src = script_src;
			script.type = 'text/javascript';

			document.body.appendChild( script );
		}
	};
} )( document, 'data:image/webp;base64,UklGR', window._wpImageMimeFallbackSettings );
