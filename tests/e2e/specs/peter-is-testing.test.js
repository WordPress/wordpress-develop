
import { modifiers, SHIFT, ALT, CTRL } from '@wordpress/keycodes';

/**
 * External dependencies
 */
import { join } from 'path';
import { capitalCase } from 'change-case';

const { PLAYWRIGHT_TEST_BASE_URL } = process.env;




/**
 * Emulates a Ctrl+A SelectAll key combination by dispatching custom keyboard
 * events and using the results of those events to determine whether to call
 * `document.execCommand( 'selectall' );`. This is necessary because Puppeteer
 * does not emulate Ctrl+A SelectAll in macOS. Events are dispatched to ensure
 * that any `Event#preventDefault` which would have normally occurred in the
 * application as a result of Ctrl+A is respected.
 *
 * @see https://github.com/GoogleChrome/puppeteer/issues/1313
 * @see https://w3c.github.io/uievents/tools/key-event-viewer.html
 *
 * @return {Promise} Promise resolving once the SelectAll emulation completes.
 */
async function emulateSelectAll( page) {
	await page.evaluate( () => {
		const isMac = /Mac|iPod|iPhone|iPad/.test( window.navigator.platform );
		const canvasDoc = document.activeElement.contentDocument ?? document;

		canvasDoc.activeElement.dispatchEvent(
			new KeyboardEvent( 'keydown', {
				bubbles: true,
				cancelable: true,
				key: isMac ? 'Meta' : 'Control',
				code: isMac ? 'MetaLeft' : 'ControlLeft',
				location: window.KeyboardEvent.DOM_KEY_LOCATION_LEFT,
				getModifierState: ( keyArg ) =>
					keyArg === ( isMac ? 'Meta' : 'Control' ),
				ctrlKey: ! isMac,
				metaKey: isMac,
				charCode: 0,
				keyCode: isMac ? 93 : 17,
				which: isMac ? 93 : 17,
			} )
		);

		const preventableEvent = new KeyboardEvent( 'keydown', {
			bubbles: true,
			cancelable: true,
			key: 'a',
			code: 'KeyA',
			location: window.KeyboardEvent.DOM_KEY_LOCATION_STANDARD,
			getModifierState: ( keyArg ) =>
				keyArg === ( isMac ? 'Meta' : 'Control' ),
			ctrlKey: ! isMac,
			metaKey: isMac,
			charCode: 0,
			keyCode: 65,
			which: 65,
		} );

		const wasPrevented =
			! canvasDoc.activeElement.dispatchEvent( preventableEvent ) ||
			preventableEvent.defaultPrevented;

		if ( ! wasPrevented ) {
			canvasDoc.execCommand( 'selectall', false, null );
		}

		canvasDoc.activeElement.dispatchEvent(
			new KeyboardEvent( 'keyup', {
				bubbles: true,
				cancelable: true,
				key: isMac ? 'Meta' : 'Control',
				code: isMac ? 'MetaLeft' : 'ControlLeft',
				location: window.KeyboardEvent.DOM_KEY_LOCATION_LEFT,
				getModifierState: () => false,
				charCode: 0,
				keyCode: isMac ? 93 : 17,
				which: isMac ? 93 : 17,
			} )
		);
	} );
}

/**
 * Sets the clipboard data that can be pasted with
 * `pressKeyWithModifier( 'primary', 'v' )`.
 *
 * @param {Object} $1           Options.
 * @param {string} $1.plainText Plain text to set.
 * @param {string} $1.html      HTML to set.
 */
export async function setClipboardData( { page, plainText = '', html = '' } ) {
	await page.evaluate(
		( _plainText, _html ) => {
			window._clipboardData = new DataTransfer();
			window._clipboardData.setData( 'text/plain', _plainText );
			window._clipboardData.setData( 'text/html', _html );
		},
		plainText,
		html
	);
}

async function emulateClipboard( page, type ) {
	await page.evaluate( ( _type ) => {
		const canvasDoc = document.activeElement.contentDocument ?? document;

		if ( _type !== 'paste' ) {
			window._clipboardData = new DataTransfer();

			const selection = canvasDoc.defaultView.getSelection();
			const plainText = selection.toString();
			let html = plainText;

			if ( selection.rangeCount ) {
				const range = selection.getRangeAt( 0 );
				const fragment = range.cloneContents();

				html = Array.from( fragment.childNodes )
					.map( ( node ) => node.outerHTML || node.nodeValue )
					.join( '' );
			}

			window._clipboardData.setData( 'text/plain', plainText );
			window._clipboardData.setData( 'text/html', html );
		}

		canvasDoc.activeElement.dispatchEvent(
			new ClipboardEvent( _type, {
				bubbles: true,
				cancelable: true,
				clipboardData: window._clipboardData,
			} )
		);
	}, type );
}

/**
 * Performs a key press with modifier (Shift, Control, Meta, Alt), where each modifier
 * is normalized to platform-specific modifier.
 *
 * @param {string} modifier Modifier key.
 * @param {string} key      Key to press while modifier held.
 */
export async function pressKeyWithModifier( page, modifier, key ) {
	if ( modifier.toLowerCase() === 'primary' && key.toLowerCase() === 'a' ) {
		return await emulateSelectAll( page );
	}

	if ( modifier.toLowerCase() === 'primary' && key.toLowerCase() === 'c' ) {
		return await emulateClipboard( page, 'copy' );
	}

	if ( modifier.toLowerCase() === 'primary' && key.toLowerCase() === 'x' ) {
		return await emulateClipboard( page, 'cut' );
	}

	if ( modifier.toLowerCase() === 'primary' && key.toLowerCase() === 'v' ) {
		return await emulateClipboard( page, 'paste' );
	}

	const isAppleOS = () => process.platform === 'darwin';
	const overWrittenModifiers = {
		...modifiers,
		shiftAlt: ( _isApple ) =>
			_isApple() ? [ SHIFT, ALT ] : [ SHIFT, CTRL ],
	};
	const mappedModifiers = overWrittenModifiers[ modifier ]( isAppleOS );
	const ctrlSwap = ( mod ) => ( mod === CTRL ? 'control' : mod );

	await Promise.all(
		mappedModifiers.map( async ( mod ) => {
			const capitalizedMod = capitalCase( ctrlSwap( mod ) );
			return page.keyboard.down( capitalizedMod );
		} )
	);

	await page.keyboard.press( key );

	await Promise.all(
		mappedModifiers.map( async ( mod ) => {
			const capitalizedMod = capitalCase( ctrlSwap( mod ) );
			return page.keyboard.up( capitalizedMod );
		} )
	);
}




/**
 * Regular expression matching a displayed PHP error within a markup string.
 *
 * @see https://github.com/php/php-src/blob/598175e/main/main.c#L1257-L1297
 *
 * @type {RegExp}
 */
const REGEXP_PHP_ERROR =
	/(<b>)?(Fatal error|Recoverable fatal error|Warning|Parse error|Notice|Strict Standards|Deprecated|Unknown error)(<\/b>)?: (.*?) in (.*?) on line (<b>)?\d+(<\/b>)?/;

/**
 * Returns a promise resolving to one of either a string or null. A string will
 * be resolved if an error message is present in the contents of the page. If no
 * error is present, a null value will be resolved instead. This requires the
 * environment be configured to display errors.
 *
 * @see http://php.net/manual/en/function.error-reporting.php
 *
 * @return {Promise<?string>} Promise resolving to a string or null, depending
 *                            whether a page error is present.
 */
export async function getPageError( page ) {
	const content = await page.content();
	const match = content.match( REGEXP_PHP_ERROR );
	return match ? match[ 0 ] : null;
}

/**
 * Creates new URL by parsing base URL, WPPath and query string.
 *
 * @param {string}  WPPath String to be serialized as pathname.
 * @param {?string} query  String to be serialized as query portion of URL.
 * @return {string} String which represents full URL.
 */
export function createURL( WPPath, query = '' ) {
	const url = new URL( PLAYWRIGHT_TEST_BASE_URL );

	url.pathname = join( url.pathname, WPPath );
	url.search = query;

	return url.href;
}

export function isCurrentURL( page, WPPath, query = '' ) {
	const currentURL = new URL( page.url() );

	currentURL.search = query;

	return createURL( WPPath, query ) === currentURL.href;
}

async function loginUser(
	page,
	username = 'admin',
	password = 'password'
) {
	if ( ! isCurrentURL( page, 'wp-login.php' ) ) {
		const waitForLoginPageNavigation = page.waitForNavigation();
		await page.goto( createURL( 'wp-login.php' ) );
		await waitForLoginPageNavigation;
	}

	await page.focus( '#user_login' );
	await pressKeyWithModifier( page, 'primary', 'a' );
	await page.type( '#user_login', username );
	await page.focus( '#user_pass' );
	await pressKeyWithModifier( page, 'primary', 'a' );
	await page.type( '#user_pass', password );

	await Promise.all( [
		page.click( '#wp-submit' ),
		page.waitForNavigation( { waitUntil: 'networkidle0' } ),
	] );
}

async function visitAdminPage( page, adminPath, query ) {
	await page.goto( createURL( join( 'wp-admin', adminPath ), query ) );

	// Handle upgrade required screen.
	if ( isCurrentURL( page, 'wp-admin/upgrade.php' ) ) {
		// Click update.
		await page.click( '.button.button-large.button-primary' );
		// Click continue.
		await page.click( '.button.button-large' );
	}

	if ( isCurrentURL( page, 'wp-login.php' ) ) {
		await loginUser( page );
		await visitAdminPage( page, adminPath, query );
	}

	const error = await getPageError( page );
	if ( error ) {
		throw new Error( 'Unexpected error in page content: ' + error );
	}
}


/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
	test( 'Is it network related?', async ( { page, admin } ) => {
		await page.goto( '/wp-login.php?reauth=1', {} );
		await visitAdminPage( page, '/' );
		// await admin.visitAdminPage( '/' );

		expect(page.locator('body')).toContainText('Dashboard');
		expect( isCurrentURL( page, '/wp-admin/' ) ).toBe( true );
	} );
