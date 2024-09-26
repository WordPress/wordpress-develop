/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const TEST_APPLICATION_NAME = 'Test Application';

test.describe( 'Manage applications passwords', () => {
	test.use( {
		applicationPasswords: async ( { requestUtils, admin, page }, use ) => {
			await use( new ApplicationPasswords( { requestUtils, admin, page } ) );
		},
	} );

	test.beforeEach(async ( { applicationPasswords } ) => {
		await applicationPasswords.delete();
	} );

	test('should correctly create a new application password', async ( {
		page,
		applicationPasswords
	} ) => {
		await applicationPasswords.create();

		const [ app ] = await applicationPasswords.get();
		expect( app['name']).toBe( TEST_APPLICATION_NAME );

		const successMessage = page.getByRole( 'alert' );

		await expect( successMessage ).toHaveClass( /notice-success/ );
		await expect(
			successMessage
		).toContainText(
			`Your new password for ${TEST_APPLICATION_NAME} is:`
		);
		await expect(
			successMessage
		).toContainText(
			`Be sure to save this in a safe location. You will not be able to retrieve it.`
		);
	} );

	test( 'should correctly revoke a single application password', async ( {
		page,
		applicationPasswords
	} ) => {
		await applicationPasswords.create();

		const revokeButton = page.getByRole( 'button', { name: `Revoke "${ TEST_APPLICATION_NAME }"` } );
		await expect( revokeButton ).toBeVisible();

		// Revoke password.
		page.on( 'dialog', ( dialog ) => dialog.accept() );
		await revokeButton.click();

		await expect(
			page.getByRole( 'alert' )
		).toContainText(
			'Application password revoked.'
		);

		const response = await applicationPasswords.get();
		expect( response ).toEqual([]);
	} );

	test( 'should correctly revoke all the application passwords', async ( {
		page,
		applicationPasswords
	} ) => {
		await applicationPasswords.create();

		const revokeAllButton = page.getByRole( 'button', { name: 'Revoke all application passwords' } );
		await expect( revokeAllButton ).toBeVisible();

		// Confirms revoking action.
		page.on( 'dialog', ( dialog ) => dialog.accept() );
		await revokeAllButton.click();

		await expect(
			page.getByRole( 'alert' )
		).toContainText(
			'All application passwords revoked.'
		);

		const response = await applicationPasswords.get();
		expect( response ).toEqual([]);
	} );
} );

class ApplicationPasswords {
	constructor( { requestUtils, page, admin }) {
		this.requestUtils = requestUtils;
		this.page = page;
		this.admin = admin;
	}

	async create(applicationName = TEST_APPLICATION_NAME) {
		await this.admin.visitAdminPage( '/profile.php' );

		const newPasswordField = this.page.getByRole( 'textbox', { name: 'New Application Password Name' } );
		await expect( newPasswordField ).toBeVisible();
		await newPasswordField.fill( applicationName );

		await this.page.getByRole( 'button', { name: 'Add New Application Password' } ).click();
		await expect( this.page.getByRole( 'alert' ) ).toBeVisible();
	}

	async get() {
		return this.requestUtils.rest( {
			method: 'GET',
			path: '/wp/v2/users/me/application-passwords',
		} );
	}

	async delete() {
		await this.requestUtils.rest( {
			method: 'DELETE',
			path: '/wp/v2/users/me/application-passwords',
		} );
	}
}
