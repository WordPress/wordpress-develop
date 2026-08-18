/**
 * Hides the admin easter egg behind the command palette.
 *
 * The palette is used as the doorway, but the egg deliberately does not register a
 * command. A registered command would be listed the moment its phrase matched, which
 * gives the thing away to anyone who happens to type near it. Instead this watches for
 * Enter while the palette is open and acts on the phrase itself, so the palette shows
 * its ordinary "No results found." state throughout and looks exactly like a search
 * that found nothing.
 *
 * The phrase is compared in its Dvorak/QWERTY-substituted form, using the same cipher
 * the 2013 original used, so it is not a readable string in the admin bundle. That is
 * the one piece of deliberate obfuscation here and it is aimed at users, not at other
 * developers: the mystery is meant to live in the trigger. Everything else, including
 * the payload in teletype.js, ships as readable source.
 *
 * The files are named for what they do rather than for what they are. This one is
 * enqueued by name on every admin screen that loads the palette, so a src ending in
 * easter-egg.js would answer the question before anyone thought to ask it.
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
