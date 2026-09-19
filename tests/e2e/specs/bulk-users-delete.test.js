/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Delete User', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		function generateRandomUser( index ) {
			return {
				username: `testuser${ index }_${ Math.floor(
					Math.random() * 10000
				) }`,
				email: `test${ index }_${ Math.floor(
					Math.random() * 10000
				) }@domain.tld`,
				password: `admin${ index }`,
				roles: 'subscriber',
			};
		}
		const users = Array.from( { length: 2 }, ( _, index ) =>
			generateRandomUser( index )
		);

		users.forEach( ( user ) => {
			requestUtils.createUser( user );
		} );
	} );

	test( 'Delete Bulk Users', async ( { page, admin } ) => {
		await admin.visitAdminPage( '/users.php' );

		await page.locator( "a[href='users.php?role=subscriber']" ).click();
		await page
			.locator( 'th.check-column input[type="checkbox"]' )
			.first()
			.check();
		await page
			.locator( 'th.check-column input[type="checkbox"] >> nth=1' )
			.check();
		await page
			.locator( '#bulk-action-selector-top' )
			.selectOption( 'delete' );
		await page.locator( '#doaction' ).click();
		await page.getByRole( 'button', { name: 'Confirm Deletion' } ).click();

		// Expect successful user deletion
		await expect( page.locator( '#message > p' ) ).toHaveText(
			/2 users deleted/
		);
	} );
} );
