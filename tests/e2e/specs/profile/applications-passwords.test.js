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

		const response = await applicationPasswords.get();
		expect( response[0]['name']).toBe( TEST_APPLICATION_NAME );

		const successMessage = await page.waitForSelector(
			'#application-passwords-section .notice-success'
		);
		expect(
			await successMessage.evaluate( ( element ) => element.innerText )
		).toContain(
			`Your new password for ${TEST_APPLICATION_NAME} is: \n\nBe sure to save this in a safe location. You will not be able to retrieve it.`
		);
	} );

	test('should not allow to create two applications passwords with the same name', async ( {
		page,
		applicationPasswords
	} ) => {
		await applicationPasswords.create();
		await applicationPasswords.create();

		const errorMessage = await page.waitForSelector(
			'#application-passwords-section .notice-error'
		);

		expect(
			await errorMessage.evaluate( ( element ) => element.textContent )
		).toContain( 'Each application name should be unique.' );
	});

	test( 'should correctly revoke a single application password', async ( {
		page,
		applicationPasswords
	} ) => {
		await applicationPasswords.create();

		page.on('dialog', dialog => dialog.accept());
		await page.getByRole( 'button', { name: `Revoke "${TEST_APPLICATION_NAME}"` } ).click();

		await expect( page.locator( '#application-passwords-section .notice-success' ) )
			.toContainText( 'Application password revoked.' );

		const response = await applicationPasswords.get();
		expect( response ).toEqual([]);
	} );

	test( 'should correctly revoke all the application passwords', async ( {
		page,
		applicationPasswords
	} ) => {
		await applicationPasswords.create();

		page.on('dialog', dialog => dialog.accept());
		await page.getByRole( 'button', { name: 'Revoke all application passwords' } ).click();

		await expect( page.locator( '#application-passwords-section .notice-success' ) )
			.toContainText( 'All application passwords revoked.' );

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
		await this.admin.visitAdminPage('profile.php' );
		await this.page.waitForSelector('#new_application_password_name' );
		await this.page.type( '#new_application_password_name', applicationName );
		await this.page.click( '#do_new_application_password' );
		await this.page.waitForSelector( '#application-passwords-section .notice' );
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
