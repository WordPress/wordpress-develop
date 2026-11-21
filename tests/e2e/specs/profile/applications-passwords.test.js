/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { signIn } from '../../fixtures/user';

const TEST_APPLICATION_NAME = 'Test Application';

test.describe( 'Manage applications passwords', () => {
	test.use( {
		storageState: { cookies: [], origins: [] },
		// Each test uses its own user, so parallel copies do not see each other's application passwords.
		applicationPasswords: async (
			{ requestUtils, admin, page, userFactory },
			use
		) => {
			const { user, username, password } = await userFactory.create( {
				roles: [ 'subscriber' ],
			} );
			await signIn( page, username, password );
			await expect( page ).toHaveURL( /\/wp-admin\/profile\.php/ );

			await use(
				new ApplicationPasswords( {
					requestUtils,
					admin,
					page,
					userId: user.id,
				} )
			);
		},
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
		page.once( 'dialog', ( dialog ) => dialog.accept() );
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
		page.once( 'dialog', ( dialog ) => dialog.accept() );
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
	constructor( { requestUtils, page, admin, userId } ) {
		this.requestUtils = requestUtils;
		this.page = page;
		this.admin = admin;
		this.userId = userId;
	}

	async create(applicationName = TEST_APPLICATION_NAME) {
		await this.admin.visitAdminPage( '/profile.php' );

		const newPasswordField = this.page.getByRole( 'textbox', { name: 'New Application Password Name' } );
		await expect( newPasswordField ).toBeVisible();
		await newPasswordField.fill( applicationName );

		await this.page.getByRole( 'button', { name: 'Add Application Password' } ).click();
		await expect( this.page.getByRole( 'alert' ) ).toBeVisible();
	}

	async get() {
		return this.requestUtils.rest( {
			method: 'GET',
			path: `/wp/v2/users/${ this.userId }/application-passwords`,
		} );
	}
}
