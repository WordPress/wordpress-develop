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

	test('should correctly create a new application password with expiration', async ( {
		page,
		applicationPasswords
	} ) => {
		const expiresDate = new Date();
		expiresDate.setDate( expiresDate.getDate() + 7 );
		const expiresString = expiresDate.toISOString().split( 'T' )[ 0 ];

		await applicationPasswords.create( TEST_APPLICATION_NAME, expiresString );

		const [ app ] = await applicationPasswords.get();
		expect( app['name'] ).toBe( TEST_APPLICATION_NAME );
		expect( app['expires'] ).not.toBeNull();
		expect( app['expires'].startsWith( expiresString ) ).toBe( true );

		const successMessage = page.getByRole( 'alert' );
		await expect( successMessage ).toHaveClass( /notice-success/ );
	} );

	test('should correctly update an application password expiration date', async ( {
		page,
		applicationPasswords
	} ) => {
		await applicationPasswords.create();

		const [ app ] = await applicationPasswords.get();
		expect( app['expires'] ).toBeNull();

		const editButton = page.getByRole( 'button', { name: 'Edit Expiration Date' } );
		await expect( editButton ).toBeVisible();
		await editButton.click();

		const expiresInput = page.locator( '.edit-expires-input' );
		await expect( expiresInput ).toBeVisible();

		const expiresDate = new Date();
		expiresDate.setDate( expiresDate.getDate() + 10 );
		const expiresString = expiresDate.toISOString().split( 'T' )[ 0 ];
		await expiresInput.fill( expiresString );

		const saveButton = page.getByRole( 'button', { name: 'Save' } );
		await saveButton.click();

		await expect( page.getByRole( 'alert' ) ).toContainText( 'Application password expiration updated.' );

		const [ updatedApp ] = await applicationPasswords.get();
		expect( updatedApp['expires'] ).not.toBeNull();
		expect( updatedApp['expires'].startsWith( expiresString ) ).toBe( true );
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

	async create(applicationName = TEST_APPLICATION_NAME, expires = null) {
		await this.admin.visitAdminPage( '/profile.php' );

		const newPasswordField = this.page.getByRole( 'textbox', { name: 'New Application Password Name' } );
		await expect( newPasswordField ).toBeVisible();
		await newPasswordField.fill( applicationName );

		if ( expires ) {
			const newPasswordExpiresField = this.page.getByLabel( 'Expires on' );
			await expect( newPasswordExpiresField ).toBeVisible();
			await newPasswordExpiresField.fill( expires );
		}

		await this.page.getByRole( 'button', { name: 'Add Application Password' } ).click();
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
