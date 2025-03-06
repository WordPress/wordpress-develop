// This script should eventually live elsewhere, but for now it's just in `wp-includes` for simplicity.
window.wp = window.wp || {};
window.wp.viewTransitions = {};
window.wp.viewTransitions.init = ( config ) => {
	if ( ! window.navigation || ! 'CSSViewTransitionRule' in window ) {
		window.console.warn( 'View transitions not loaded as the browser is lacking support.' );
		return;
	}

	const getViewTransitionEntries = ( transitionType, bodyElement, articleElement ) => {
		const isMainSlide = transitionType === 'forwards' || transitionType === 'backwards';
		let foundMainElement = false;
		return [
			...Object.entries( config.globalTransitionNames || {} ).map( ( [ selector, name ] ) => {
				const element = bodyElement.querySelector( selector );
				if ( name === 'main' && element ) {
					foundMainElement = true;
				}
				return [ element, name ];
			} ),
			...( articleElement && ( ! isMainSlide || ! foundMainElement )
				? Object.entries( config.postTransitionNames || {} ).map( ( [ selector, name ] ) => {
					const element = articleElement.querySelector( selector );
					return [ element, name ];
				} )
				: []
			),
		];
	};

	const setTemporaryViewTransitionNames = async ( entries, vtPromise ) => {
		for ( const [ element, name ] of entries ) {
			if ( ! element ) {
				continue;
			}
			element.style.viewTransitionName = name;
		}

		await vtPromise;

		for ( const [ element, _ ] of entries ) {
			if ( ! element ) {
				continue;
			}
			element.style.viewTransitionName = '';
		}
	};

	const appendSelectors = ( selectors, append ) => {
		return selectors.split( ',' ).map( subselector => subselector.trim() + ' ' + append ).join( ',' );
	};

	const getArticle = () => {
		if ( ! config.postSelector ) {
			return null;
		}
		return document.querySelector( config.postSelector );
	};

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

	window.addEventListener( 'pageswap', ( e ) => {
		if ( e.viewTransition ) {
			const transitionType = determineTransitionType( e.activation.from, e.activation.entry );
			e.viewTransition.types.add( transitionType );

			if ( document.body.classList.contains( 'single' ) ) {
				setTemporaryViewTransitionNames(
					getViewTransitionEntries(
						transitionType,
						document.body,
						getArticle()
					),
					e.viewTransition.finished
				);
			} else if ( document.body.classList.contains( 'home' ) || document.body.classList.contains( 'archive' ) ) {
				setTemporaryViewTransitionNames(
					getViewTransitionEntries(
						transitionType,
						document.body,
						getArticleForUrl( e.activation.entry.url )
					),
					e.viewTransition.finished
				);
			}
		}
	} );

	window.addEventListener( 'pagereveal', ( e ) => {
		if ( e.viewTransition ) {
			const transitionType = determineTransitionType( window.navigation.activation.from, window.navigation.activation.entry );
			e.viewTransition.types.add( transitionType );

			if ( document.body.classList.contains( 'single' ) ) {
				setTemporaryViewTransitionNames(
					getViewTransitionEntries(
						transitionType,
						document.body,
						getArticle()
					),
					e.viewTransition.ready
				);
			} else if ( document.body.classList.contains( 'home' ) || document.body.classList.contains( 'archive' ) ) {
				setTemporaryViewTransitionNames(
					getViewTransitionEntries(
						transitionType,
						document.body,
						window.navigation.activation.from ? getArticleForUrl( window.navigation.activation.from.url ) : null
					),
					e.viewTransition.ready
				);
			}
		}
	} );
};
