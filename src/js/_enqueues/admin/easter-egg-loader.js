/**
 * Hides the admin easter egg behind the command palette.
 *
 * This registers a command *loader* rather than a static command. A loader is handed the
 * text currently typed into the palette, so returning an empty list of commands keeps the
 * entry invisible until the phrase is typed. A static command would always be listed.
 *
 * The phrase is compared in its Dvorak/QWERTY-substituted form, using the same cipher the
 * 2013 original used, so it is not a readable string in the admin bundle. That is the one
 * piece of deliberate obfuscation here and it is aimed at users, not at other developers:
 * the mystery is meant to live in the trigger. Everything else, including the payload in
 * easter-egg.js, ships as readable source.
 *
 * @output wp-admin/js/easter-egg-loader.js
 */

( function ( wp, settings ) {
	'use strict';

	if ( ! wp || ! wp.data || ! settings ) {
		return;
	}

	var KEY   = 'ishdg;rsdkot',
		LABEL = 'Jre. co Lr.ypf',
		FROM  = '\',.pyfgcrl/=\\aoeuidhtns-;qjkxbmwvz"<>PYFGCRL?+|AOEUIDHTNS_:QJKXBMWVZ[]',
		TO    = 'qwertyuiop[]\\asdfghjkl;\'zxcvbnm,./QWERTYUIOP{}|ASDFGHJKL:"ZXCVBNM<>?-=';

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

		if ( wp.easterEgg ) {
			wp.easterEgg.run( settings.name );
			return;
		}

		script = document.createElement( 'script' );
		script.src = settings.src;

		script.onload = function () {
			if ( wp.easterEgg ) {
				wp.easterEgg.run( settings.name );
			}
		};

		document.head.appendChild( script );
	}

	wp.data.dispatch( 'core/commands' ).registerCommandLoader( {
		name: 'core/easter-egg',
		hook: function ( args ) {
			var search = ( ( args && args.search ) || '' ).toLowerCase().replace( /[^a-z0-9]/g, '' );

			if ( ! search || dvortr( search ) !== KEY ) {
				return { commands: [], isLoading: false };
			}

			return {
				commands: [ {
					name: 'core/easter-egg',
					label: dvortr( LABEL ),
					callback: function ( commandArgs ) {
						commandArgs.close();
						play();
					}
				} ],
				isLoading: false
			};
		}
	} );
}( window.wp, window.wpEasterEgg ) );
