/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

for ( const { label, screen, confirmationLabel, usernamePrefix } of [
	{
		label: 'Export Personal Data',
		screen: 'export-personal-data.php',
		confirmationLabel: 'Send personal data export confirmation email.',
		usernamePrefix: 'e2e-export',
	},
	{
		label: 'Erase Personal Data',
		screen: 'erase-personal-data.php',
		confirmationLabel: 'Send personal data erasure confirmation email.',
		usernamePrefix: 'e2e-erase',
	},
] ) {
	test.describe( label, () => {
		let username;
		let email;
		let userId;

		test.beforeEach( async ( { requestUtils } ) => {
			username = `${ usernamePrefix }-${ randomUUID().slice( 0, 8 ) }`;
			email = `${ username }@example.org`;
			userId = undefined;

			const user = await requestUtils.createUser( {
				username,
				email,
				password: randomUUID(),
				roles: [ 'subscriber' ],
			} );
			userId = user.id;
		} );

		test.afterEach( async ( { admin, page, requestUtils } ) => {
			try {
				await admin.visitAdminPage( screen, `s=${ email }` );
				const row = page
					.getByRole( 'row' )
					.filter( { hasText: email } );
				if ( await row.count() ) {
					await row.first().getByRole( 'checkbox' ).check();
					await page
						.locator( '#bulk-action-selector-top' )
						.selectOption( 'delete' );
					await page.locator( '#doaction' ).click();
				}
			} finally {
				if ( userId ) {
					const adminUser = await requestUtils.rest( {
						path: '/wp/v2/users/me',
					} );
					await requestUtils.rest( {
						method: 'DELETE',
						path: `/wp/v2/users/${ userId }`,
						params: { force: true, reassign: adminUser.id },
					} );
				}
			}
		} );

		test( 'adds a requester to the personal data request list', async ( {
			admin,
			page,
		} ) => {
			await admin.visitAdminPage( screen );

			await page
				.getByLabel( 'Username or email address', { exact: true } )
				.fill( username );
			await page
				.getByLabel( confirmationLabel, {
					exact: true,
				} )
				.uncheck();
			await page
				.getByRole( 'button', { name: 'Send Request', exact: true } )
				.click();

			await expect(
				page.locator(
					'#setting-error-username_or_email_for_privacy_request'
				)
			).toContainText( 'Request added successfully.' );

			const requesterLink = page
				.getByRole( 'row' )
				.filter( { hasText: email } )
				.getByRole( 'link', { name: email, exact: true } );
			await expect( requesterLink ).toBeVisible();

			await admin.visitAdminPage(
				screen,
				`s=${ encodeURIComponent( email ) }`
			);
			await expect(
				page
					.getByRole( 'row' )
					.filter( { hasText: email } )
					.getByRole( 'link', { name: email, exact: true } )
			).toBeVisible();
		} );
	} );
}
