/**
 * Listens to the command palette for the teletype.
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
	 * Maps a string through the character tables.
	 *
	 * @param {string} value Text to map.
	 * @return {string} Mapped text.
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
	 * Loads the teletype if needed, then runs it.
	 */
	function play() {
		var script;

		if ( wp.teletype ) {
			wp.teletype.run( settings.name, settings.i18n );
			return;
		}

		script = document.createElement( 'script' );
		script.src = settings.src;

		script.onload = function () {
			if ( wp.teletype ) {
				wp.teletype.run( settings.name, settings.i18n );
			}
		};

		document.head.appendChild( script );
	}

	/**
	 * Handles Enter in an open command palette.
	 *
	 * @param {KeyboardEvent} event The keydown event.
	 */
	function onKeydown( event ) {
		var store,
			input = event.target,
			value;

		if ( 'Enter' !== event.key || ! input || ! input.value ) {
			return;
		}

		store = wp.data.select( 'core/commands' );

		if ( ! store || ! store.isOpen() ) {
			return;
		}

		// Leave Enter to the palette whenever one of its results is active.
		if (
			'combobox' !== input.getAttribute( 'role' ) ||
			input.getAttribute( 'aria-activedescendant' )
		) {
			return;
		}

		value = input.value.toLowerCase().replace( /[^a-z0-9]/g, '' );

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
