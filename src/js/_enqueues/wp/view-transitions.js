/**
 * @output wp-includes/js/wp-view-transitions.js
 */

window.wp = window.wp || {};
window.wp.viewTransitions = {};

/**
 * Initializes view transitions for the current URL.
 *
 * @param {object}  config                         The view transitions configuration.
 * @param {string}  config.postSelector            General selector for post elements in the DOM.
 * @param {object}  config.globalTransitionNames   Map of selectors for global elements (queried relative to 'body')
 *                                                 and their view transition names.
 * @param {object}  config.postTransitionNames     Map of selectors for post elements (queried relative to an element
 *                                                 identified by config.postSelector) and their view transition names.
 * @param {boolean} config.chronologicalSlideInOut Whether slide in/out animation for chronological URL relationship
 *                                                 (date- or pagination-based) should be enabled.
 */
window.wp.viewTransitions.init = ( config ) => {
	if ( ! window.navigation || 'CSSViewTransitionRule' in window === false ) {
		window.console.warn( 'View transitions not loaded as the browser is lacking support.' );
		return;
	}

	/**
	 * Gets all view transition entries relevant for a view transition.
	 *
	 * @param {string}       transitionType View transition type (e.g. 'default', 'forwards', 'backwards').
	 * @param {Element}      bodyElement    The body element.
	 * @param {Element|null} articleElement The post element relevant for the view transition, if any.
	 * @return {Array[]} View transition entries with each one containing the element and its view transition name.
	 */
	const getViewTransitionEntries = ( transitionType, bodyElement, articleElement ) => {
		const isMainSlide = transitionType === 'forwards' || transitionType === 'backwards';
		let foundMainElement = false;
		const globalEntries = Object.entries( config.globalTransitionNames || {} ).map( ( [ selector, name ] ) => {
			const element = bodyElement.querySelector( selector );
			if ( name === 'main' && element ) {
				foundMainElement = true;
			}
			return [ element, name ];
		} );
		if ( ! articleElement || isMainSlide && foundMainElement ) {
			return globalEntries;
		}
		return [
			...globalEntries,
			...Object.entries( config.postTransitionNames || {} ).map( ( [ selector, name ] ) => {
				const element = articleElement.querySelector( selector );
				return [ element, name ];
			} ),
		];
	};

	/**
	 * Temporarily sets view transition names for the given entries until the view transition has been completed.
	 *
	 * @param {Array[]}       entries   View transition entries as received from `getViewTransitionEntries()`.
	 * @param {Promise<void>} vtPromise Promise that resolves after the view transition has been completed.
	 * @return {Promise<void>} Promise that resolves after the view transition names were reset.
	 */
	const setTemporaryViewTransitionNames = async ( entries, vtPromise ) => {
		for ( const [ element, name ] of entries ) {
			if ( ! element ) {
				continue;
			}
			element.style.viewTransitionName = name;
		}

		await vtPromise;

		for ( const [ element ] of entries ) {
			if ( ! element ) {
				continue;
			}
			element.style.viewTransitionName = '';
		}
	};

	/**
	 * Appends a selector to another selector.
	 *
	 * This supports selectors which technically include multiple selectors (separated by comma).
	 *
	 * @param {string} selectors Main selector.
	 * @param {string} append    Selector to append to the main selector.
	 * @return {string} Combined selector.
	 */
	const appendSelectors = ( selectors, append ) => {
		return selectors.split( ',' ).map( subselector => subselector.trim() + ' ' + append ).join( ',' );
	};

	/**
	 * Gets a post element (the first on the page, in case there are multiple).
	 *
	 * @return {Element|null} Post element, or null if none is found.
	 */
	const getArticle = () => {
		if ( ! config.postSelector ) {
			return null;
		}
		return document.querySelector( config.postSelector );
	};

	/**
	 * Gets the post element for a specific post URL.
	 *
	 * @param {string} url Post URL (permalink) to find post element.
	 * @return {Element|null} Post element, or null if none is found.
	 */
	const getArticleForUrl = ( url ) => {
		if ( ! config.postSelector ) {
			return null;
		}
		const postLinkSelector = appendSelectors( config.postSelector, 'a[href="' + url + '"]' );
		const articleLink = document.querySelector( postLinkSelector );
		if ( ! articleLink ) {
			return null;
		}
		return articleLink.closest( config.postSelector );
	};

	/**
	 * Determines the view transition type to use, given an old and new navigation history entry.
	 *
	 * @param {NavigationHistoryEntry} oldEntry Navigation history entry for the URL navigated from.
	 * @param {NavigationHistoryEntry} newEntry Navigation history entry for the URL navigated to.
	 * @return {string} View transition type (e.g. 'default', 'forwards', 'backwards').
	 */
	const determineTransitionType = ( oldEntry, newEntry ) => {
		if ( ! config.chronologicalSlideInOut ) {
			return 'default';
		}

		if ( ! oldEntry || ! newEntry ) {
			return 'default';
		}

		const oldURL = new URL( oldEntry.url );
		const newURL = new URL( newEntry.url );

		const oldPathname = oldURL.pathname;
		const newPathname = newURL.pathname;

		if ( oldPathname === newPathname ) {
			return 'default';
		}

		// Check if the URLs are for a paginated archive.
		let oldPageMatches = oldPathname.match( /\/page\/(\d+)\/?$/ );
		let newPageMatches = newPathname.match( /\/page\/(\d+)\/?$/ );
		let prefix = '';

		// If not, check if the URLs are for a multi-page post.
		if ( ! oldPageMatches && ! newPageMatches ) {
			oldPageMatches = oldPathname.match( /\/(\d+)\/?$/ );
			newPageMatches = newPathname.match( /\/(\d+)\/?$/ );
			prefix = 'content-';
		}

		// If there is a match on at least one of the URLs, compare whether their roots before the page segment match.
		if ( oldPageMatches || newPageMatches ) {
			const oldPageBase = oldPageMatches ? oldPathname.substring( 0, oldPathname.length - oldPageMatches[ 0 ].length ) : oldPathname.replace( /\/$/, '' );
			const newPageBase = newPageMatches ? newPathname.substring( 0, newPathname.length - newPageMatches[ 0 ].length ) : newPathname.replace( /\/$/, '' );
			if ( oldPageBase === newPageBase ) { // They belong to the same archive or post.
				if ( oldPageMatches && newPageMatches ) {
					return Number( oldPageMatches[ 1 ] ) < Number( newPageMatches[ 1 ] ) ? `${ prefix }forwards` : `${ prefix }backwards`;
				}
				if ( newPageMatches && Number( newPageMatches[ 1 ] ) > 1 ) {
					return `${ prefix }forwards`;
				}
				if ( oldPageMatches && Number( oldPageMatches[ 1 ] ) > 1 ) {
					return `${ prefix }backwards`;
				}
			}
		}

		// Check if the URLs are for content labelled by date (e.g. navigation to previous/next post).
		const oldDateMatches = oldPathname.match( /\/(\d{4})\/(\d{2})\/(\d{2})\/[^\/]+\/?$/ );
		const newDateMatches = newPathname.match( /\/(\d{4})\/(\d{2})\/(\d{2})\/[^\/]+\/?$/ );
		if ( oldDateMatches && newDateMatches ) {
			const oldPageBase = oldPathname.substring( 0, oldPathname.length - oldDateMatches[ 0 ].length );
			const newPageBase = newPathname.substring( 0, newPathname.length - newDateMatches[ 0 ].length );
			if ( oldPageBase === newPageBase ) { // They belong to the same hierarchy.
				const oldDate = new Date( parseInt( oldDateMatches[ 1 ] ), parseInt( oldDateMatches[ 2 ] ) - 1, parseInt( oldDateMatches[ 3 ] ) );
				const newDate = new Date( parseInt( newDateMatches[ 1 ] ), parseInt( newDateMatches[ 2 ] ) - 1, parseInt( newDateMatches[ 3 ] ) );
				if ( oldDate < newDate ) {
					return 'forwards';
				}
				if ( oldDate > newDate ) {
					return 'backwards';
				}
			}
		}

		return 'default';
	};

	/**
	 * Customizes view transition behavior on the URL that is being navigated from.
	 *
	 * @param {PageSwapEvent} event Event fired as the previous URL is about to unload.
	 */
	window.addEventListener( 'pageswap', ( event ) => {
		if ( event.viewTransition ) {
			const transitionType = determineTransitionType( event.activation.from, event.activation.entry );
			event.viewTransition.types.add( transitionType );

			if ( document.body.classList.contains( 'single' ) ) {
				setTemporaryViewTransitionNames(
					getViewTransitionEntries(
						transitionType,
						document.body,
						getArticle()
					),
					event.viewTransition.finished
				);
			} else if ( document.body.classList.contains( 'home' ) || document.body.classList.contains( 'archive' ) ) {
				setTemporaryViewTransitionNames(
					getViewTransitionEntries(
						transitionType,
						document.body,
						getArticleForUrl( event.activation.entry.url )
					),
					event.viewTransition.finished
				);
			}
		}
	} );

	/**
	 * Customizes view transition behavior on the URL that is being navigated to.
	 *
	 * @param {PageRevealEvent} event Event fired as the new URL being navigated to is loaded.
	 */
	window.addEventListener( 'pagereveal', ( event ) => {
		if ( event.viewTransition ) {
			const transitionType = determineTransitionType( window.navigation.activation.from, window.navigation.activation.entry );
			event.viewTransition.types.add( transitionType );

			if ( document.body.classList.contains( 'single' ) ) {
				setTemporaryViewTransitionNames(
					getViewTransitionEntries(
						transitionType,
						document.body,
						getArticle()
					),
					event.viewTransition.ready
				);
			} else if ( document.body.classList.contains( 'home' ) || document.body.classList.contains( 'archive' ) ) {
				setTemporaryViewTransitionNames(
					getViewTransitionEntries(
						transitionType,
						document.body,
						window.navigation.activation.from ? getArticleForUrl( window.navigation.activation.from.url ) : null
					),
					event.viewTransition.ready
				);
			}
		}
	} );
};
