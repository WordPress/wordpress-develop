/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const TEST_APPLICATION_NAME = 'Test Application';

test.describe( 'Manage applications passwords', () => {
	test.use( {
		applicationPasswords: async ( { requestUtils }, use ) => {
			await use( new ApplicationPasswords( { requestUtils } ) );
		},
	} );

	test.beforeEach(async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'DELETE',
			path: '/wp/v2/users/me/application-passwords',
		} );
	});

	test('should correctly create a new application password', async ( {
	   page,
	   applicationPasswords
	} ) => {
		await applicationPasswords.create();

		const response = await applicationPasswords.get();
		expect(response[0]["name"]).toBe(TEST_APPLICATION_NAME);

		const successMessage = await page.waitForSelector(
			"#application-passwords-section .notice-success"
		);
		expect(
			await successMessage.evaluate((element) => element.innerText)
		).toContain(
			`Your new password for ${TEST_APPLICATION_NAME} is: \n\nBe sure to save this in a safe location. You will not be able to retrieve it.`
		);
	});

	test("should not allow to create two applications passwords with the same name", async ( {
		page,
		applicationPasswords
	} ) => {
		await applicationPasswords.create();
		await applicationPasswords.create();

		const errorMessage = await page.waitForSelector(
			"#application-passwords-section .notice-error"
		);

		expect(
			await errorMessage.evaluate((element) => element.textContent)
		).toContain("Each application name should be unique.");
	});

	test("should correctly revoke a single application password", async ( {
		page,
		applicationPasswords
	} ) => {
		await applicationPasswords.create();

		const revokeApplicationButton = await page.waitForSelector(
			".application-passwords-user tr button.delete"
		);

		const revocationDialogPromise = new Promise((resolve) => {
			page.once("dialog", resolve);
		});

		await Promise.all([
			revocationDialogPromise,
			revokeApplicationButton.click(),
		]);

		const successMessage = await page.waitForSelector(
			"#application-passwords-section .notice-success"
		);
		expect(
			await successMessage.evaluate((element) => element.textContent)
		).toContain("Application password revoked.");

		const response = await applicationPasswords.get();
		expect(response).toEqual([]);
	});

	test("should correctly revoke all the application passwords", async ( {
		page,
		applicationPasswords
	} ) => {
		await applicationPasswords.create();

		const revokeAllApplicationPasswordsButton = await page.waitForSelector(
			"#revoke-all-application-passwords"
		);

		const revocationDialogPromise = new Promise((resolve) => {
			page.once("dialog", resolve);
		});

		await Promise.all([
			revocationDialogPromise,
			revokeAllApplicationPasswordsButton.click(),
		]);

		/**
		 * This is commented out because we're using enablePageDialogAccept
		 * which is overly aggressive and no way to temporary disable it either.
		 */
		// await dialog.accept();

		await page.waitForSelector(
			"#application-passwords-section .notice-success"
		);

		const successMessage = await page.waitForSelector(
			"#application-passwords-section .notice-success"
		);
		expect(
			await successMessage.evaluate((element) => element.textContent)
		).toContain("All application passwords revoked.");

		const response = await applicationPasswords.get();
		expect(response).toEqual([]);
	});
});

class ApplicationPasswords {
	constructor( { requestUtils, page, admin }) {
		this.requestUtils = requestUtils;
		this.page = page;
		this.admin = admin;
	}

	async createInUi(applicationName = TEST_APPLICATION_NAME) {
		await this.admin.visitAdminPage('profile.php' );
		await this.page.waitForSelector('#new_application_password_name' );
		await this.page.type( '#new_application_password_name', applicationName );
		await this.page.click( '#do_new_application_password' );
		await this.page.waitForSelector( '#application-passwords-section .notice' );
	}

	async create( applicationName = TEST_APPLICATION_NAME ) {
		await this.requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/users/me/application-passwords',
			data: {
				name: applicationName,
			},
		} );
	}

	async get() {
		await this.requestUtils.rest( {
			method: 'GET',
			path: '/wp/v2/users/me/application-passwords',
		} );
	}
}
