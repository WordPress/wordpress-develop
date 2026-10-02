/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Cache Control header directives', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	});

	test(
		'No private directive present in cache control when user not logged in.',
		async ( { browser, admin, editor}
		) => {
		await admin.createNewPost( { title: 'Hello World' } );
		await editor.publishPost();

		await admin.visitAdminPage( '/' );

		// Create a new incognito browser context to simulate logged-out state.
		const context = await browser.newContext();
		const loggedOutPage = await context.newPage();

		const response = await loggedOutPage.goto( '/hello-world/' );
		const responseHeaders = response.headers();

		// Dispose context once it's no longer needed.
		await context.close();

		expect( responseHeaders ).toEqual( expect.not.objectContaining( { "cache-control": "no-cache" } ) );
		expect( responseHeaders ).toEqual( expect.not.objectContaining( { "cache-control": "no-store" } ) );
		expect( responseHeaders ).toEqual( expect.not.objectContaining( { "cache-control": "private" } ) );
	} );

	test(
		'Private directive header present in cache control when logged in.',
		async ( { page, admin }
		) => {
		await admin.visitAdminPage( '/' );

		const response = await page.goto( '/wp-admin' );
		const responseHeaders = response.headers();

		expect( responseHeaders[ 'cache-control' ] ).toContain( 'no-cache' );
		expect( responseHeaders[ 'cache-control' ] ).toContain( 'no-store' );
		expect( responseHeaders[ 'cache-control' ] ).toContain( 'private' );
	} );

	test(
		'Correct directives present in cache control header when not logged in on 404 page.',
		async ( { browser }
		) => {
		const context = await browser.newContext();
		const loggedOutPage = await context.newPage();

		const response = await loggedOutPage.goto( '/this-does-not-exist/' );
		const responseHeaders = response.headers();
		const responseStatus = response.status();

		// Dispose context once it's no longer needed.
		await context.close();

		expect( responseStatus ).toBe( 404 );
		expect( responseHeaders[ 'cache-control' ] ).toContain( 'no-cache' );
		expect( responseHeaders[ 'cache-control' ] ).toContain( 'no-store' );
		expect( responseHeaders[ 'cache-control' ] ).toContain( 'private' );
	} );
} );
