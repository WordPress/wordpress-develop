/**
 * Listens to the command palette for the teletype.
 *
 * @output wp-admin/js/teletype-loader.js
 */

/**
 * Watches Enter in the command palette and loads the teletype on a match.
 *
 * @param {Object} wp       The global WordPress JS object.
 * @param {Object} settings Settings printed by wp_enqueue_command_palette_assets().
 */
( function ( wp, settings ) {
	'use strict';

	if ( ! wp || ! wp.data || ! settings || ! settings.i18n ) {
		return;
	}

	/**
	 * Lowercases text and strips its whitespace and punctuation.
	 *
	 * @param {string} value Text to normalize.
	 * @return {string} Normalized text.
	 */
	function normalize( value ) {
		return value.toLowerCase().replace( /[\s!-\/:-@\[-`{-~\u3000-\u303f\uff01-\uff0f]/g, '' );
	}

	var trigger = normalize( settings.i18n.trigger || '' );

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

		value = normalize( input.value );

		if ( ! value || value !== trigger ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		wp.data.dispatch( 'core/commands' ).close();
		play();
	}

	document.addEventListener( 'keydown', onKeydown, true );
}( window.wp, window.wpTeletype ) );
