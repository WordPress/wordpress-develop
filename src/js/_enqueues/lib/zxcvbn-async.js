/**
 * @output wp-includes/js/zxcvbn-async.js
 */

/* global _zxcvbnSettings */

/**
 * Loads zxcvbn asynchronously by inserting an async script tag before the first
 * script tag on the page.
 *
 * This makes sure zxcvbn isn't blocking loading the page as it is a big
 * library. The source for zxcvbn is read from the `_zxcvbnSettings` global.
 */
( () => {
	const asyncLoad = () => {
		if ( typeof _zxcvbnSettings === 'undefined' || ! _zxcvbnSettings.src ) {
			return;
		}

		const script = document.createElement( 'script' );
		script.src = _zxcvbnSettings.src;
		script.type = 'text/javascript';
		script.async = true;

		const first = document.getElementsByTagName( 'script' )[ 0 ];
		if ( ! first || ! first.parentNode ) {
			return;
		}

		first.parentNode.insertBefore( script, first );
	};

	window.addEventListener( 'load', asyncLoad );
} )();
