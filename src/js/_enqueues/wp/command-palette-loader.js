/**
 * @output wp-includes/js/wp-command-palette-loader.js
 */

/**
 * Opens the Command Palette, loading its assets on first use.
 *
 * On screens that do not load the Command Palette up front, its scripts and
 * styles are printed into an inert template by
 * wp_print_command_palette_assets_template(). They are only added to the page
 * when the palette is first requested, with the keyboard shortcut or the
 * toolbar button, so that they do not delay every admin page load.
 *
 * @since 7.3.0
 */
( function ( window, document ) {
	const TEMPLATE_ID = 'wp-command-palette-assets';
	const TOOLBAR_LINK_SELECTOR = '#wp-admin-bar-command-palette > .ab-item';
	const IS_APPLE_OS = /Mac|iPod|iPhone|iPad/.test( window.navigator.platform );

	let isLoading = false;

	/**
	 * Tells whether a key press is the shortcut that opens the Command Palette.
	 *
	 * @param {KeyboardEvent} event The key press.
	 * @return {boolean} Whether the key press is Cmd+K on Apple devices or Ctrl+K elsewhere.
	 */
	function isOpenPaletteShortcut( event ) {
		const primaryModifier = IS_APPLE_OS ? event.metaKey : event.ctrlKey;

		return primaryModifier && ! event.altKey && ! event.shiftKey && 'k' === event.key?.toLowerCase();
	}

	/**
	 * Tells whether the Command Palette assets are still waiting in their template.
	 *
	 * @return {boolean} Whether the assets have not been added to the page yet.
	 */
	function arePaletteAssetsPending() {
		return isLoading || null !== document.getElementById( TEMPLATE_ID );
	}

	/**
	 * Adds a script to the page and waits until it has run.
	 *
	 * A script added with the DOM runs as soon as it is inserted when it is
	 * inline, but only after it has loaded when it has a source. Waiting for
	 * each one keeps the order in which they were printed.
	 *
	 * @param {HTMLScriptElement} templateScript The script as printed in the template.
	 * @return {Promise<void>} Resolves once the script has run or failed to load.
	 */
	function insertScript( templateScript ) {
		const script = document.createElement( 'script' );

		for ( const attribute of templateScript.attributes ) {
			script.setAttribute( attribute.name, attribute.value );
		}
		script.text = templateScript.text;

		if ( ! script.hasAttribute( 'src' ) ) {
			document.body.appendChild( script );
			return Promise.resolve();
		}

		return new Promise( ( resolve ) => {
			script.onload = () => resolve();
			script.onerror = () => resolve();
			document.body.appendChild( script );
		} );
	}

	/**
	 * Adds a stylesheet to the page and waits until it has loaded.
	 *
	 * @param {HTMLLinkElement|HTMLStyleElement} templateStyle The style as printed in the template.
	 * @return {Promise<void>} Resolves once the stylesheet has loaded or failed to load.
	 */
	function insertStyle( templateStyle ) {
		const style = document.importNode( templateStyle, true );

		if ( 'LINK' !== style.tagName ) {
			document.head.appendChild( style );
			return Promise.resolve();
		}

		return new Promise( ( resolve ) => {
			style.onload = () => resolve();
			style.onerror = () => resolve();
			document.head.appendChild( style );
		} );
	}

	/**
	 * Asks the browser to start downloading a script before it is inserted.
	 *
	 * @param {string} url The script URL.
	 */
	function preloadScript( url ) {
		const link = document.createElement( 'link' );

		link.rel = 'preload';
		link.as = 'script';
		link.href = url;
		document.head.appendChild( link );
	}

	/**
	 * Adds the Command Palette's scripts and styles from the template to the page.
	 *
	 * All scripts are preloaded at once and then run one after the other.
	 *
	 * @param {DocumentFragment} assets The template content.
	 * @return {Promise<void>} Resolves once every script has run and every stylesheet has loaded.
	 */
	async function insertPaletteAssets( assets ) {
		const scripts = Array.from( assets.querySelectorAll( 'script' ) );
		const styles = Array.from(
			assets.querySelectorAll( 'link[rel="stylesheet"], style' ),
			( style ) => /** @type {HTMLLinkElement|HTMLStyleElement} */ ( style )
		);

		for ( const script of scripts ) {
			const url = script.getAttribute( 'src' );

			if ( url ) {
				preloadScript( url );
			}
		}

		const stylesLoaded = Promise.all( styles.map( insertStyle ) );

		for ( const script of scripts ) {
			await insertScript( script );
		}

		await stylesLoaded;
	}

	/**
	 * Opens the Command Palette, adding its assets to the page first if needed.
	 */
	async function openPalette() {
		if ( isLoading ) {
			return;
		}

		const template = /** @type {HTMLTemplateElement|null} */ ( document.getElementById( TEMPLATE_ID ) );

		if ( template ) {
			isLoading = true;
			template.remove();
			await insertPaletteAssets( template.content );
			isLoading = false;
		}

		window.wp.data.dispatch( 'core/commands' ).open();
	}

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.defaultPrevented || ! isOpenPaletteShortcut( event ) || ! arePaletteAssetsPending() ) {
			return;
		}

		event.preventDefault();
		openPalette();
	} );

	document.addEventListener( 'click', ( event ) => {
		if ( ! ( event.target instanceof Element ) || ! event.target.closest( TOOLBAR_LINK_SELECTOR ) ) {
			return;
		}

		event.preventDefault();
		openPalette();
	} );
}( window, document ) );
