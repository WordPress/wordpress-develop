/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';

test.describe( 'Cache Control header directives', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	});

	test(
		'No private directive present in cache control when user not logged in.',
		async ( { loggedOutPage, admin, editor}
		) => {
		await admin.createNewPost( { title: 'Hello World' } );
		await editor.publishPost();

		await admin.visitAdminPage( '/' );

		const response = await loggedOutPage.goto( '/hello-world/' );
		const responseHeaders = response.headers();

		const cacheControl = responseHeaders[ 'cache-control' ] ?? '';
		expect( cacheControl ).not.toContain( 'no-cache' );
		expect( cacheControl ).not.toContain( 'no-store' );
		expect( cacheControl ).not.toContain( 'private' );
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
		async ( { loggedOutPage }
		) => {
		const response = await loggedOutPage.goto( '/this-does-not-exist/' );
		const responseHeaders = response.headers();
		const responseStatus = response.status();

		expect( responseStatus ).toBe( 404 );
		expect( responseHeaders[ 'cache-control' ] ).toContain( 'no-cache' );
		expect( responseHeaders[ 'cache-control' ] ).toContain( 'no-store' );
		expect( responseHeaders[ 'cache-control' ] ).toContain( 'private' );
	} );
} );
