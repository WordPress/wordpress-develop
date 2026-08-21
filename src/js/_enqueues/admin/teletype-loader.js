/**
 * Hides the admin easter egg behind the command palette.
 *
 * No command is registered: a registered command would be listed as soon as its phrase
 * matched. This listens for Enter while the palette is open instead, so the palette shows
 * its ordinary "No results found." state throughout.
 *
 * The phrase is compared in its Dvorak/QWERTY-substituted form so that it is not a
 * readable string in the admin bundle.
 *
 * @output wp-admin/js/teletype-loader.js
 */

( function ( wp, settings ) {
	'use strict';

	if ( ! wp || ! wp.data || ! settings ) {
		return;
	}

	var KEY  = 'ishdg;rsdkot',
		FROM = '\',.pyfgcrl/=\\aoeuidhtns-;qjkxbmwvz"<>PYFGCRL?+|AOEUIDHTNS_:QJKXBMWVZ[]',
		TO   = 'qwertyuiop[]\\asdfghjkl;\'zxcvbnm,./QWERTYUIOP{}|ASDFGHJKL:"ZXCVBNM<>?-=';

	/**
	 * Applies the Dvorak/QWERTY substitution cipher.
	 *
	 * @param {string} value Text to substitute.
	 * @return {string} Substituted text.
	 */
	function dvortr( value ) {
		var map = {},
			i;

		for ( i = 0; i < FROM.length; i++ ) {
			map[ FROM.charAt( i ) ] = TO.charAt( i );
		}

		return value.replace( /[\s\S]/g, function ( character ) {
			return map[ character ] || character;
		} );
	}

	/**
	 * Fetches the payload if it is not already loaded, then plays it.
	 */
	function play() {
		var script;

		if ( wp.teletype ) {
			wp.teletype.run( settings.name );
			return;
		}

		script = document.createElement( 'script' );
		script.src = settings.src;

		script.onload = function () {
			if ( wp.teletype ) {
				wp.teletype.run( settings.name );
			}
		};

		document.head.appendChild( script );
	}

	/**
	 * Acts on the phrase when it is submitted from an open command palette.
	 *
	 * @param {KeyboardEvent} event The keydown event.
	 */
	function onKeydown( event ) {
		var store,
			value;

		if ( 'Enter' !== event.key || ! event.target || ! event.target.value ) {
			return;
		}

		store = wp.data.select( 'core/commands' );

		if ( ! store || ! store.isOpen() ) {
			return;
		}

		value = event.target.value.toLowerCase().replace( /[^a-z0-9]/g, '' );

		if ( ! value || dvortr( value ) !== KEY ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		wp.data.dispatch( 'core/commands' ).close();
		play();
	}

	document.addEventListener( 'keydown', onKeydown, true );
}( window.wp, window.wpTeletype ) );
