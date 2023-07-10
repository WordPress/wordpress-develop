import {
	visitAdminPage,
	createNewPost,
	publishPost,
	trashAllPosts,
	createURL,
	logout,
} from "@wordpress/e2e-test-utils";

describe( 'Cache Control header directives', () => {

	beforeEach( async () => {
		await trashAllPosts();
	} );

	it( 'No private directive present in cache control when user not logged in.', async () => {
		await createNewPost( { title: 'Hello World' } );
		await publishPost();
		await logout();

		const response = await page.goto( createURL( '/hello-world/' ) );
		const responseHeaders = response.headers();

		expect( responseHeaders ).toEqual( expect.not.objectContaining( { "cache-control": "no-store" } ) );
		expect( responseHeaders ).toEqual( expect.not.objectContaining( { "cache-control": "private" } ) );
	} );

	it( 'Private directive header present in cache control when logged in.', async () => {
		await visitAdminPage( '/wp-admin' );

		const response = await page.goto( createURL( '/wp-admin' ) );
		const responseHeaders = response.headers();

		expect( responseHeaders[ 'cache-control' ] ).toContain( 'no-store' );
		expect( responseHeaders[ 'cache-control' ] ).toContain( 'private' );
	} );

} );
