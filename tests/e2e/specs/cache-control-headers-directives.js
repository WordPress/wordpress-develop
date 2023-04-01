import {
	visitAdminPage,
	createNewPost,
	trashAllPosts,
	createURL,
} from "@wordpress/e2e-test-utils";

describe( 'Cache Control header directives', () => {

	beforeEach( async () => {
		await trashAllPosts();
	} );

	it( 'No private directive present in cache control when not an admin', async () => {
		await createNewPost( {
			title: 'Hello World',
			post_status: 'publish',
		} );

		const response = await page.goto( createURL( '/hello-world/' ) );
		const cacheControl = response.headers();
		expect( cacheControl[ 'cache-control' ] ).not.toContain( 'private' );
	} );

	it( 'Private directive header present in cache control when admin', async () => {
		await visitAdminPage( '/wp-admin' );

		const response = await page.goto( createURL( '/wp-admin' ) );
		const cacheControl = response.headers();
		expect( cacheControl[ 'cache-control' ] ).toContain( 'private' );
	} );

} );