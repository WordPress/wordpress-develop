/**
 * Listens to the command palette for the teletype.
 *
 * @output wp-admin/js/teletype-loader.js
 */

/**
 * Watches Enter in the command palette and loads the teletype on a match.
 *
 * @param {any}                           wp       The global WordPress JS object.
 * @param {WPTeletypeSettings|undefined} settings Settings printed by wp_enqueue_command_palette_assets().
 */
( ( wp, settings ) => {
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
	const normalize = ( value ) =>
		value.toLowerCase().replace( /[\s!-\/:-@\[-`{-~　-〿！-／]/g, '' );

	const { name, src, i18n } = settings;
	const trigger = normalize( i18n.trigger || '' );

	/**
	 * Loads the teletype if needed, then runs it.
	 */
	const play = () => {
		if ( wp.teletype ) {
			wp.teletype.run( name, i18n );
			return;
		}

		const script = document.createElement( 'script' );
		script.src = src;
		script.addEventListener( 'load', () => {
			wp.teletype?.run( name, i18n );
		} );

		document.head.appendChild( script );
	};

	document.addEventListener(
		'keydown',
		( event ) => {
			const input = event.target;

			if ( 'Enter' !== event.key || ! ( input instanceof HTMLInputElement ) || ! input.value ) {
				return;
			}

			const store = wp.data.select( 'core/commands' );

			if ( ! store?.isOpen() ) {
				return;
			}

			// Leave Enter to the palette whenever one of its results is active.
			if (
				'combobox' !== input.getAttribute( 'role' ) ||
				input.getAttribute( 'aria-activedescendant' )
			) {
				return;
			}

			const value = normalize( input.value );

			if ( ! value || value !== trigger ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			wp.data.dispatch( 'core/commands' ).close();
			play();
		},
		true
	);
} )( window.wp, window.wpTeletype );
