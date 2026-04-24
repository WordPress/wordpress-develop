// This script is loaded as a module so there is no global namespace pollution.

const script = /** @type {HTMLScriptElement} */ (
	document.getElementById( 'wp-script-module-data-@wordpress/bfcache' )
);

/**
 * Exports from PHP.
 *
 * @type {{cookieName: string}}
 */
const data = JSON.parse( script.text );

/**
 * Gets the current bfcache session token from a cookie.
 *
 * A change to the session token indicates that the bfcache needs to be
 * invalidated.
 *
 * @return {string|null} Session token if the cookie is set.
 */
function getCurrentSessionToken() {
	const re = new RegExp( '(?:^|;\\s*)' + data.cookieName + '=([^;]+)' );
	// Note that CookieStore is not being used since it requires HTTPS, and a synchronous API is preferable to more
	// quickly invalidate the bfcache in the pageshow event handler.
	const matches = document.cookie.match( re );
	return matches ? decodeURIComponent( matches[ 1 ] ) : null;
}

const initialSessionToken = getCurrentSessionToken();

/**
 * Reloads the page when navigating to a page via bfcache and the authentication state has changed.
 *
 * @param {PageTransitionEvent} event - The pageshow event object.
 */
function onPageShow( event ) {
	if ( event.persisted && getCurrentSessionToken() !== initialSessionToken ) {
		// Immediately clear out the contents of the page since otherwise the authenticated content will appear while the page reloads.
		document.body.innerHTML = '';

		// TODO: Could there be a scenario where an infinite reload happens? No, because the pageshow event would not have the persisted property set to true after a reload.
		window.location.reload();
	}
}

window.addEventListener( 'pageshow', onPageShow );
