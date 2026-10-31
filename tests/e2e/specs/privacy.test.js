/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

function normalizeText( text ) {
	return text.replace( /\s+/g, ' ' ).trim();
}

test.describe( 'Privacy page creation', () => {
	let originalSettings;
	let createdPageId;

	test.beforeEach( async ( { requestUtils } ) => {
		originalSettings = undefined;
		createdPageId = undefined;
		originalSettings = await requestUtils.rest( {
			path: '/wp/v2/settings',
		} );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( ! originalSettings ) {
			return;
		}

		try {
			await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/settings',
				data: {
					page_for_privacy_policy:
						originalSettings.page_for_privacy_policy,
				},
			} );
		} finally {
			if ( createdPageId ) {
				await requestUtils.rest( {
					method: 'DELETE',
					path: `/wp/v2/pages/${ createdPageId }`,
					params: { force: true },
				} );
			}
		}
	} );

	test( 'creates a draft privacy page and selects it in settings', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'options-privacy.php' );
		await Promise.all( [
			page
				.waitForResponse(
					( response ) =>
						response.request().method() === 'POST' &&
						new URL( response.url() ).pathname.endsWith(
							'/options-privacy.php'
						) &&
						response.status() === 302
				)
				.then( ( response ) => {
					// Capture ownership from the redirect before any editor assertions can fail.
					createdPageId = Number(
						new URL(
							response.headers().location,
							page.url()
						).searchParams.get( 'post' )
					);
				} ),
			page.locator( '#create-page' ).click(),
		] );
		expect( createdPageId ).toBeGreaterThan( 0 );
		await expect( page ).toHaveURL(
			new RegExp( `post\\.php\\?post=${ createdPageId }&action=edit` )
		);
		const policy = await requestUtils.rest( {
			path: `/wp/v2/pages/${ createdPageId }`,
			params: { context: 'edit' },
		} );
		expect( policy.title.raw ).toBe( 'Privacy Policy' );
		expect( policy.status ).toBe( 'draft' );
		expect( policy.content.raw ).toContain( 'Who we are' );
		expect( policy.content.raw ).toContain( 'Comments' );
		const settings = await requestUtils.rest( { path: '/wp/v2/settings' } );
		expect( settings.page_for_privacy_policy ).toBe( createdPageId );
		await admin.visitAdminPage( 'options-privacy.php' );
		await expect( page.locator( '#page_for_privacy_policy' ) ).toHaveValue(
			String( createdPageId )
		);
	} );
} );

test( 'copies suggested policy text without tutorial or toolbar text', async ( {
	admin,
	page,
	context,
	baseURL,
} ) => {
	await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ], {
		origin: new URL( baseURL ).origin,
	} );
	await page.clock.install( { time: new Date( '2026-01-01T00:00:00Z' ) } );
	await admin.visitAdminPage( 'privacy-policy-guide.php' );
	const section = page.getByRole( 'button', {
		name: 'WordPress',
		exact: true,
	} );
	await section.click();
	const panel = page.locator(
		`#${ await section.getAttribute( 'aria-controls' ) }`
	);
	await expect( panel ).toBeVisible();
	const expectedText = normalizeText(
		await panel.locator( 'h2, p' ).evaluateAll( ( elements ) =>
			elements
				.filter(
					( element ) =>
						! element.classList.contains(
							'privacy-policy-tutorial'
						)
				)
				.map( ( element ) => {
					const copy = element.cloneNode( true );
					copy.querySelectorAll( '.privacy-policy-tutorial' ).forEach(
						( tutorial ) => tutorial.remove()
					);
					return copy.textContent;
				} )
				.join( '\n' )
		)
	);
	expect( expectedText ).toContain( 'Who we are' );
	expect( expectedText ).toContain( 'Comments' );
	const copyButton = panel.getByRole( 'button', {
		name: 'Copy suggested policy text from WordPress.',
		exact: true,
	} );
	// Keep the notice's dismissal timer from racing the second copy on slow runners.
	await page.clock.pauseAt( new Date( '2026-01-01T01:00:00Z' ) );
	// Repeat while the success notice is visible to catch copying the toolbar into the clipboard.
	for ( let attempt = 0; attempt < 2; attempt++ ) {
		await page.evaluate( () =>
			navigator.clipboard.writeText( 'Clipboard sentinel' )
		);
		if ( attempt > 0 ) {
			await expect( panel.locator( '.success' ) ).toBeVisible();
		}
		await copyButton.click();
		await expect( panel.locator( '.success' ) ).toBeVisible();
		await expect
			.poll( async () =>
				normalizeText(
					await page.evaluate( () => navigator.clipboard.readText() )
				)
			)
			.toBe( expectedText );
	}
} );
