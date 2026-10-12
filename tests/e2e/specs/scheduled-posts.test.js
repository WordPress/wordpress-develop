/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Scheduled Posts', () => {
	let postIds;

	test.beforeEach( () => {
		postIds = [];
	} );

	test.afterEach( async ( { requestUtils } ) => {
		for ( const id of postIds ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/posts/${ id }`,
				params: { force: true },
			} );
		}
	} );

	test( 'lists only scheduled posts in the Scheduled view', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		// Use the server clock and keep publication well outside the test run.
		const response = await requestUtils.request.get( requestUtils.baseURL );
		const dateHeader = response.headers().date;
		expect( dateHeader ).toBeDefined();
		const tomorrow = new Date( new Date( dateHeader ).getTime() + 86_400_000 );
		const scheduled = await requestUtils.createPost( {
			title: 'Future Post',
			status: 'future',
			date_gmt: tomorrow.toISOString(),
		} );
		postIds.push( scheduled.id );
		expect( scheduled.status ).toBe( 'future' );

		const published = await requestUtils.createPost( {
			title: 'Published Post',
			status: 'publish',
		} );
		postIds.push( published.id );

		await admin.visitAdminPage( '/edit.php' );
		const listTable = page.getByRole( 'table', {
			name: 'Table ordered by',
		} );
		const publishedLink = listTable.getByRole( 'link', {
			name: 'Published Post',
			exact: true,
		} );
		await expect( publishedLink ).toBeVisible();

		await page.getByRole( 'link', { name: /^Scheduled/ } ).click();
		await expect( page ).toHaveURL( /[?&]post_status=future(?:&|$)/ );
		const scheduledLink = listTable.getByRole( 'link', {
			name: 'Future Post',
			exact: true,
		} );
		await expect( scheduledLink ).toBeVisible();
		await expect(
			listTable
				.getByRole( 'row' )
				.filter( {
					has: page.getByRole( 'link', {
						name: 'Future Post',
						exact: true,
					} ),
				} )
				.getByRole( 'cell', { name: /^Scheduled/ } )
		).toBeVisible();
		await expect( publishedLink ).toHaveCount( 0 );
	} );
} );
