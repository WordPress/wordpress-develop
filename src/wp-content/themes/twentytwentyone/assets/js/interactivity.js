/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

function checkClass( element, className ) {
    if ( element.classList.contains( className ) ) {
        return element;
    }
    if ( element.parentElement && element.parentElement.classList.contains( className ) ) {
        return element.parentElement;
    }
    if ( element.parentElement.parentElement && element.parentElement.parentElement.classList.contains( className ) ) {
        return element.parentElement.parentElement;
    }
    return null;
}

const { state, actions } = store( 'twentytwentyone', {
    state: {
        isPrimaryMenuOpen: false,
        windowWidth: 0,
        prevScroll: 0,
        isDarkMode: false,
        isDarkModeTogglerHidden: false,
    },
    actions: {
        togglePrimaryMenu: () => {
            state.isPrimaryMenuOpen = ! state.isPrimaryMenuOpen;
        },

        openPrimaryMenu: () => {
            state.isPrimaryMenuOpen = true;
        },

        closePrimaryMenu: () => {
            state.isPrimaryMenuOpen = false;
        },

        toggleDarkMode: () => {
            state.isDarkMode = ! state.isDarkMode;
            window.localStorage.setItem( 'twentytwentyoneDarkMode', state.isDarkMode ? 'yes' : 'no' );
        },

        trapFocusInModal: ( event ) => {
            if ( ! state.isPrimaryMenuOpen ) {
                return;
            }

            const ctx = getContext();

			const escKey = event.keyCode === 27;
            if ( escKey ) {
				event.preventDefault();
                actions.closePrimaryMenu();
                if ( ctx.firstFocusable ) {
                    ctx.firstFocusable.focus();
                }
                return;
			}

            const tabKey = event.keyCode === 9;
			const shiftKey = event.shiftKey;
			const activeEl = document.activeElement; // eslint-disable-line @wordpress/no-global-active-element

            if ( ! shiftKey && tabKey && ctx.lastFocusable === activeEl ) {
				event.preventDefault();
				if ( ctx.firstFocusable ) {
                    ctx.firstFocusable.focus();
                }
                return;
			}

			if ( shiftKey && tabKey && ctx.firstFocusable === activeEl ) {
				event.preventDefault();
				if ( ctx.lastFocusable ) {
                    ctx.lastFocusable.focus();
                }
                return;
			}

			// If there are no elements in the menu, don't move the focus
			if ( tabKey && ctx.firstFocusable === ctx.lastFocusable ) {
				event.preventDefault();
			}
        },

        listenToSpecialClicks: ( event ) => {
            const ctx = getContext();

            // Check if this was a `.sub-menu-toggle` click.
            const subMenuToggle = checkClass( event.target, 'sub-menu-toggle' );
            if ( subMenuToggle ) {
                if ( ctx.activeSubmenu === subMenuToggle ) {
                    ctx.activeSubmenu = null;
                } else {
                    ctx.activeSubmenu = subMenuToggle;
                }
                return;
            }

            // Otherwise, check if this was an anchor link click.
            if ( ! event.target.hash ) {
                return;
            }

            actions.closePrimaryMenu();

            // Wait 550 and scroll to the anchor.
            setTimeout( () => {
                var anchor = document.getElementById( event.target.hash.slice( 1 ) );
                if ( anchor ) {
                    anchor.scrollIntoView();
                }
            }, 550 );
        },
    },
    callbacks: {
        determineFocusableElements: () => {
            if ( ! state.isPrimaryMenuOpen ) {
                return;
            }

            const ctx = getContext();
            const { ref } = getElement();
			const elements = ref.querySelectorAll( 'input, a, button' );

            ctx.firstFocusable = elements[ 0 ];
            ctx.lastFocusable = elements[ elements.length - 1 ];
        },

        refreshSubmenus: () => {
            const ctx = getContext();
            const { ref } = getElement();
			const elements = ref.querySelectorAll( '.sub-menu-toggle' );
            elements.forEach( ( subMenuToggle ) => {
                if ( ctx.activeSubmenu === subMenuToggle ) {
                    subMenuToggle.setAttribute( 'aria-expanded', 'true' );
                } else {
                    subMenuToggle.setAttribute( 'aria-expanded', 'false' );
                }
            } );
        },

        makeIframesResponsive: () => {
            const { ref } = getElement();

            ref.querySelectorAll( 'iframe' ).forEach( function( iframe ) {
                // Only continue if the iframe has a width & height defined.
                if ( iframe.width && iframe.height ) {
                    // Calculate the proportion/ratio based on the width & height.
                    proportion = parseFloat( iframe.width ) / parseFloat( iframe.height );
                    // Get the parent element's width.
                    parentWidth = parseFloat( window.getComputedStyle( iframe.parentElement, null ).width.replace( 'px', '' ) );
                    // Set the max-width & height.
                    iframe.style.maxWidth = '100%';
                    iframe.style.maxHeight = Math.round( parentWidth / proportion ).toString() + 'px';
                }
            } );
        },

        updateWindowWidthOnResize: () => {
            // The following may be needed here since we can't use `data-wp-on--resize`?
            const refreshWidth = () => {
                state.windowWidth = window.innerWidth;
            }
            window.onresize = refreshWidth;
        },

        initDarkMode: () => {
            let isDarkMode = window.matchMedia( '(prefers-color-scheme: dark)' ).matches;

            if ( 'yes' === window.localStorage.getItem( 'twentytwentyoneDarkMode' ) ) {
                isDarkMode = true;
            } else if ( 'no' === window.localStorage.getItem( 'twentytwentyoneDarkMode' ) ) {
                isDarkMode = false;
            }

            state.isDarkMode = isDarkMode;

            // The following may be needed here since we can't use `data-wp-on--scroll`?
            const checkScroll = () => {
                const currentScroll = window.scrollY || document.documentElement.scrollTop;
                if (
                    currentScroll + ( window.innerHeight * 1.5 ) > document.body.clientHeight ||
                    currentScroll < state.prevScroll
                ) {
                    state.isDarkModeTogglerHidden = false;
                } else if ( currentScroll > state.prevScroll && 250 < currentScroll ) {
                    state.isDarkModeTogglerHidden = true;
                }
                state.prevScroll = currentScroll;
            }
            window.addEventListener( 'scroll', checkScroll );
        },

        refreshHtmlElementDarkMode: () => {
            // This hack may be needed since the HTML element cannot be controlled with the API attributes?
            if ( state.isDarkMode ) {
                document.documentElement.classList.add( 'is-dark-theme' );
            } else {
                document.documentElement.classList.remove( 'is-dark-theme' );
            }
        },
    },
} );
