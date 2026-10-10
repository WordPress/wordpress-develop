/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const TEST_APPLICATION_NAME = 'Test Application';

test.describe( 'Manage applications passwords', () => {
	test.use( {
		applicationPasswords: async ( { requestUtils, admin, page }, use ) => {
			await use(
				new ApplicationPasswords( { requestUtils, admin, page } )
			);
		},
	} );

	test.beforeEach( async ( { applicationPasswords } ) => {
		await applicationPasswords.delete();
	} );

	test( 'should correctly create a new application password', async ( {
		page,
		applicationPasswords,
	} ) => {
		await applicationPasswords.create();

		const [ app ] = await applicationPasswords.get();
		expect( app[ 'name' ] ).toBe( TEST_APPLICATION_NAME );

		const successMessage = page.getByRole( 'alert' );

		await expect( successMessage ).toHaveClass( /notice-success/ );
		await expect( successMessage ).toContainText(
			`Your new password for ${ TEST_APPLICATION_NAME } is:`
		);
		await expect( successMessage ).toContainText(
			`Be sure to save this in a safe location. You will not be able to retrieve it.`
		);
	} );

	test( 'should correctly create a new application password with expiration', async ( {
		page,
		applicationPasswords,
	} ) => {
		const expiresDate = new Date();
		expiresDate.setDate( expiresDate.getDate() + 7 );
		const expiresString = expiresDate.toISOString().split( 'T' )[ 0 ];

		await applicationPasswords.create(
			TEST_APPLICATION_NAME,
			expiresString + 'T09:30'
		);
		await expect(
			page.locator(
				'.create-application-password #application-passwords-timezone'
			)
		).toBeVisible();

		const [ app ] = await applicationPasswords.get();
		expect( app[ 'name' ] ).toBe( TEST_APPLICATION_NAME );
		expect( app[ 'expires' ] ).not.toBeNull();
		expect( app[ 'expires' ].startsWith( expiresString ) ).toBe( true );

		const successMessage = page.getByRole( 'alert' );
		await expect( successMessage ).toHaveClass( /notice-success/ );
	} );

	test( 'should correctly update an application password expiration date', async ( {
		page,
		applicationPasswords,
	} ) => {
		await applicationPasswords.create();

		const [ app ] = await applicationPasswords.get();
		expect( app[ 'expires' ] ).toBeNull();

		const editButton = page.getByRole( 'button', {
			name: 'Edit Expiration Date and Time',
		} );
		await expect( editButton ).toBeVisible();
		await editButton.click();

		const expiresInput = page.locator( '.edit-expires-input' );
		await expect( expiresInput ).toBeVisible();

		const expiresDate = new Date();
		expiresDate.setDate( expiresDate.getDate() + 10 );
		const expiresString = expiresDate.toISOString().split( 'T' )[ 0 ];
		await expiresInput.fill( expiresString + 'T16:45' );

		const saveButton = page.getByRole( 'button', { name: 'Save' } );
		await saveButton.click();

		await expect( page.getByRole( 'alert' ) ).toContainText(
			'Application password expiration updated.'
		);

		const [ updatedApp ] = await applicationPasswords.get();
		expect( updatedApp[ 'expires' ] ).not.toBeNull();
		expect( updatedApp[ 'expires' ].startsWith( expiresString ) ).toBe(
			true
		);
	} );

	test.describe( 'Expiration timezone handling', () => {
		test.use( { timezoneId: 'Australia/Melbourne' } );

		for ( const { timezone, created, updated } of [
			{
				timezone: 'UTC',
				created: '2028-07-15T00:30:00',
				updated: '2029-01-15T16:45:00',
			},
			{
				timezone: 'Australia/Melbourne',
				created: '2028-07-14T14:30:00',
				updated: '2029-01-15T05:45:00',
			},
			{
				timezone: 'America/New_York',
				created: '2028-07-15T04:30:00',
				updated: '2029-01-15T21:45:00',
			},
			{
				timezone: 'UTC+5.5',
				created: '2028-07-14T19:00:00',
				updated: '2029-01-15T11:15:00',
			},
		] ) {
			test( `should preserve site-local expiry dates in ${ timezone }`, async ( {
				page,
				admin,
				applicationPasswords,
			} ) => {
				await admin.visitAdminPage( '/options-general.php' );
				const originalTimezone = await page
					.locator( '#timezone_string' )
					.inputValue();
				try {
					await page
						.locator( '#timezone_string' )
						.selectOption( timezone );
					await page
						.getByRole( 'button', {
							name: 'Save Changes',
							exact: true,
						} )
						.click();
					await page.clock.setFixedTime(
						new Date(
							new Date( created + 'Z' ).getTime() - 15 * 60 * 1000
						)
					);
					await applicationPasswords.create(
						TEST_APPLICATION_NAME,
						'2028-07-15T00:30'
					);

					const [ app ] = await applicationPasswords.get();
					expect( app.expires ).toBe( created );
					const expiresCell = page
						.locator( '.column-expires' )
						.filter( { has: page.locator( '.edit-expires' ) } );
					await expect( expiresCell ).toContainText(
						'July 15, 2028 12:30 am'
					);
					await expect( expiresCell ).not.toContainText(
						'Expired on'
					);

					// Both AJAX-rendered and server-rendered rows must reopen with the site date.
					await page.locator( '.edit-expires' ).click();
					await expect(
						page.locator( '.edit-expires-input' )
					).toHaveValue( '2028-07-15T00:30' );
					await page
						.getByRole( 'button', { name: 'Cancel', exact: true } )
						.click();
					await page.reload();
					await expect( expiresCell ).toContainText(
						'July 15, 2028 12:30 am'
					);
					await page.locator( '.edit-expires' ).click();
					await expect(
						page.locator( '.edit-expires-input' )
					).toHaveValue( '2028-07-15T00:30' );
					await page
						.locator( '.edit-expires-input' )
						.fill( '2029-01-15T16:45' );
					await page
						.getByRole( 'button', { name: 'Save', exact: true } )
						.click();
					await expect( page.getByRole( 'alert' ) ).toContainText(
						'Application password expiration updated.'
					);

					const [ updatedApp ] = await applicationPasswords.get();
					expect( updatedApp.expires ).toBe( updated );
					await expect( expiresCell ).toContainText(
						'January 15, 2029 4:45 pm'
					);
					await page.locator( '.edit-expires' ).click();
					await expect(
						page.locator( '.edit-expires-input' )
					).toHaveValue( '2029-01-15T16:45' );
					await page.reload();
					await expect( expiresCell ).toContainText(
						'January 15, 2029 4:45 pm'
					);
				} finally {
					await admin.visitAdminPage( '/options-general.php' );
					await page
						.locator( '#timezone_string' )
						.selectOption( originalTimezone );
					await page
						.getByRole( 'button', {
							name: 'Save Changes',
							exact: true,
						} )
						.click();
				}
			} );
		}

		test( 'should preserve an unchanged expiry during the repeated DST hour', async ( {
			page,
			admin,
			requestUtils,
			applicationPasswords,
		} ) => {
			await admin.visitAdminPage( '/options-general.php' );
			const originalTimezone = await page
				.locator( '#timezone_string' )
				.inputValue();
			try {
				await page
					.locator( '#timezone_string' )
					.selectOption( 'America/New_York' );
				await page
					.getByRole( 'button', {
						name: 'Save Changes',
						exact: true,
					} )
					.click();
				await applicationPasswords.create(
					TEST_APPLICATION_NAME,
					'2028-07-15T00:30:37'
				);
				const [ app ] = await applicationPasswords.get();
				expect( app.expires ).toBe( '2028-07-15T04:30:37' );
				await requestUtils.rest( {
					method: 'PUT',
					path: `/wp/v2/users/me/application-passwords/${ app.uuid }`,
					data: { expires: '2028-11-05T06:30:37Z' },
				} );
				await page.reload();
				// The later 01:30 during the fallback must not become the earlier 01:30.
				for ( let i = 0; i < 2; i++ ) {
					await page.locator( '.edit-expires' ).click();
					await expect(
						page.locator( '.edit-expires-input' )
					).toHaveValue( '2028-11-05T01:30:37' );
					await page
						.getByRole( 'button', { name: 'Save', exact: true } )
						.click();
					await expect( page.getByRole( 'alert' ) ).toContainText(
						'Application password expiration updated.'
					);
					const [ saved ] = await applicationPasswords.get();
					expect( saved.expires ).toBe( '2028-11-05T06:30:37' );
				}
			} finally {
				await admin.visitAdminPage( '/options-general.php' );
				await page
					.locator( '#timezone_string' )
					.selectOption( originalTimezone );
				await page
					.getByRole( 'button', {
						name: 'Save Changes',
						exact: true,
					} )
					.click();
			}
		} );
	} );

	test( 'should correctly revoke a single application password', async ( {
		page,
		applicationPasswords,
	} ) => {
		await applicationPasswords.create();

		const revokeButton = page.getByRole( 'button', {
			name: `Revoke "${ TEST_APPLICATION_NAME }"`,
		} );
		await expect( revokeButton ).toBeVisible();

		// Revoke password.
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await revokeButton.click();

		await expect( page.getByRole( 'alert' ) ).toContainText(
			'Application password revoked.'
		);

		const response = await applicationPasswords.get();
		expect( response ).toEqual( [] );
	} );

	test( 'should correctly revoke all the application passwords', async ( {
		page,
		applicationPasswords,
	} ) => {
		await applicationPasswords.create();

		const revokeAllButton = page.getByRole( 'button', {
			name: 'Revoke all application passwords',
		} );
		await expect( revokeAllButton ).toBeVisible();

		// Confirms revoking action.
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await revokeAllButton.click();

		await expect( page.getByRole( 'alert' ) ).toContainText(
			'All application passwords revoked.'
		);

		const response = await applicationPasswords.get();
		expect( response ).toEqual( [] );
	} );
} );

test.describe( 'Application password creation expiry validation', () => {
	test.use( { timezoneId: 'Australia/Melbourne' } );

	test( 'should restrict new expiry times in the site timezone and refresh the minimum', async ( {
		page,
		admin,
	} ) => {
		await page.clock.setFixedTime( new Date( '2028-07-15T10:00:00Z' ) );
		await admin.visitAdminPage( '/profile.php' );
		await page.evaluate( () => {
			const settings = window.wp.date.getSettings();
			window.wp.date.setSettings( {
				...settings,
				timezone: { ...settings.timezone, string: '', offset: 5.5 },
			} );
		} );

		const input = page.getByLabel( 'Expires on' );
		await input.focus();
		await expect( input ).toHaveAttribute( 'min', '2028-07-15T15:30:01' );
		await input.fill( '2028-07-15T15:29:59' );
		expect(
			await input.evaluate(
				( element ) => element.validity.rangeUnderflow
			)
		).toBe( true );
		await input.fill( '2028-07-15T15:30:01' );
		expect(
			await input.evaluate( ( element ) => element.checkValidity() )
		).toBe( true );

		await page.clock.setFixedTime( new Date( '2028-07-15T10:00:30Z' ) );
		await input.blur();
		await input.focus();
		await expect( input ).toHaveAttribute( 'min', '2028-07-15T15:30:31' );

		// A time that was valid when the picker opened may have passed before submission.
		await page.clock.setFixedTime( new Date( '2028-07-15T10:01:00Z' ) );
		await page
			.getByLabel( 'New Application Password Name' )
			.fill( 'Not created' );
		let submitted = false;
		await page.route( '**/application-passwords?*', async ( route ) => {
			submitted = true;
			await route.abort();
		} );
		await page
			.getByRole( 'button', {
				name: 'Add Application Password',
				exact: true,
			} )
			.click();
		await expect( input ).toHaveAttribute( 'min', '2028-07-15T15:31:01' );
		expect(
			await input.evaluate(
				( element ) => element.validity.rangeUnderflow
			)
		).toBe( true );
		expect( submitted ).toBe( false );
		await expect(
			page.getByRole( 'button', {
				name: 'Add Application Password',
				exact: true,
			} )
		).not.toHaveClass( /disabled/ );
		await input.fill( '' );
		expect(
			await input.evaluate( ( element ) => element.checkValidity() )
		).toBe( true );
	} );
} );

test.describe( 'Application password table expiry validation', () => {
	test.use( { timezoneId: 'Australia/Melbourne' } );

	test( 'should block past selections in the inline editor while allowing expiry removal', async ( {
		page,
		admin,
	} ) => {
		await page.clock.setFixedTime( new Date( '2028-07-15T10:00:00Z' ) );
		await admin.visitAdminPage( '/profile.php' );
		const uuid = '11111111-1111-4111-8111-111111111111';
		await page.evaluate( ( fixtureUuid ) => {
			const settings = window.wp.date.getSettings();
			window.wp.date.setSettings( {
				...settings,
				timezone: { ...settings.timezone, string: '', offset: 5.5 },
			} );
			window.jQuery( '#application-passwords-section tbody' ).append(
				window.wp.template( 'application-password-row' )( {
					uuid: fixtureUuid,
					app_id: '',
					name: 'Browser-only expiry fixture',
					created: '2028-07-15T09:00:00',
					last_used: null,
					last_ip: null,
					expires: '2028-07-16T10:00:00',
				} )
			);
			window.jQuery( '.application-passwords-list-table-wrapper' ).show();
		}, uuid );

		const submitted = [];
		await page.route(
			`**/application-passwords/${ uuid }?*`,
			async ( route ) => {
				submitted.push( route.request().postDataJSON() );
				await route.fulfill( {
					status: 400,
					contentType: 'application/json',
					body: JSON.stringify( {
						message: 'Save intercepted for testing.',
					} ),
				} );
			}
		);
		const row = page.locator( `tr[data-uuid="${ uuid }"]` );
		await row.locator( '.edit-expires' ).click();
		const input = row.getByLabel( 'Expiration date and time', {
			exact: true,
		} );
		const save = row.getByRole( 'button', { name: 'Save', exact: true } );

		await expect( input ).toHaveAttribute( 'min', '2028-07-15T15:30:01' );
		await input.fill( '2028-07-14T15:30' );
		expect(
			await input.evaluate(
				( element ) => element.validity.rangeUnderflow
			)
		).toBe( true );
		await save.click();
		expect( submitted ).toHaveLength( 0 );
		await expect( save ).toBeEnabled();

		await input.fill( '2028-07-15T15:30:01' );
		await page.clock.setFixedTime( new Date( '2028-07-15T10:01:00Z' ) );
		await save.click();
		await expect( input ).toHaveAttribute( 'min', '2028-07-15T15:31:01' );
		expect( submitted ).toHaveLength( 0 );
		await page.clock.setFixedTime( new Date( '2028-07-15T10:01:30Z' ) );
		await input.blur();
		await input.focus();
		await expect( input ).toHaveAttribute( 'min', '2028-07-15T15:31:31' );

		await input.fill( '2028-07-15T15:32' );
		await save.click();
		await expect( page.getByRole( 'alert' ) ).toContainText(
			'Save intercepted for testing.'
		);
		expect( submitted ).toEqual( [
			{ expires: '2028-07-15T10:02:00.000Z' },
		] );
		await input.fill( '' );
		await save.click();
		await expect.poll( () => submitted.length ).toBe( 2 );
		expect( submitted[ 1 ] ).toEqual( { expires: null } );

		// Opening an already expired password must not silently change its stored expiry.
		await row
			.getByRole( 'button', { name: 'Cancel', exact: true } )
			.click();
		await row.evaluate( ( element ) => {
			window.jQuery( element ).data( 'expires', '2028-07-14T10:00:00' );
		} );
		await row.locator( '.edit-expires' ).click();
		await expect( input ).toHaveValue( '2028-07-14T15:30' );
		await save.click();
		await expect.poll( () => submitted.length ).toBe( 3 );
		expect( submitted[ 2 ] ).toEqual( { expires: '2028-07-14T10:00:00Z' } );
	} );
} );

test.describe( 'Application password expiry removal', () => {
	test( 'should remove and restore expiry and add expiry to a password without one', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		const currentUser = await requestUtils.rest( {
			path: '/wp/v2/users/me',
		} );
		const username = 'expiry-removal-' + Date.now();
		const user = await requestUtils.createUser( {
			username,
			email: username + '@example.org',
			password: 'test-password',
			roles: [ 'subscriber' ],
		} );
		try {
			const app = await requestUtils.rest( {
				method: 'POST',
				path: `/wp/v2/users/${ user.id }/application-passwords`,
				data: {
					name: TEST_APPLICATION_NAME,
					expires: new Date(
						Date.now() + 24 * 60 * 60 * 1000
					).toISOString(),
				},
			} );
			const path = `/wp/v2/users/${ user.id }/application-passwords/${ app.uuid }`;
			await admin.visitAdminPage(
				'/user-edit.php',
				`user_id=${ user.id }`
			);
			const row = page.locator( `tr[data-uuid="${ app.uuid }"]` );
			const input = row.getByLabel( 'Expiration date and time', {
				exact: true,
			} );
			const remove = row.getByRole( 'button', {
				name: 'Remove expiry',
				exact: true,
			} );

			await row.locator( '.edit-expires' ).click();
			await expect( remove ).toBeVisible();
			await expect
				.poll( async () =>
					remove.evaluate( ( element ) => {
						const [ red, green, blue ] = getComputedStyle( element )
							.color.match( /\d+/g )
							.map( Number );
						return red > green * 2 && red > blue * 2;
					} )
				)
				.toBe( true );
			const originalExpires = await input.inputValue();

			// Cancel still discards manual edits; Remove expiry is an immediate action.
			await input.fill( '' );
			await row
				.getByRole( 'button', { name: 'Cancel', exact: true } )
				.click();
			const unchanged = await requestUtils.rest( { path } );
			expect( unchanged.expires ).toBe( app.expires );

			await row.locator( '.edit-expires' ).click();
			await expect( input ).not.toHaveValue( '' );

			// Hold a failed removal response to check pending controls and retry behavior.
			let releaseResponse;
			const responseGate = new Promise( ( resolve ) => {
				releaseResponse = resolve;
			} );
			const routePattern = `**/application-passwords/${ app.uuid }?*`;
			let removalRequests = 0;
			await page.route( routePattern, async ( route ) => {
				removalRequests++;
				expect( route.request().postDataJSON() ).toEqual( {
					expires: null,
				} );
				await responseGate;
				await route.fulfill( {
					status: 500,
					contentType: 'application/json',
					body: JSON.stringify( {
						message: 'Removal failed for testing.',
					} ),
				} );
			} );

			try {
				await remove.click();
				await expect( remove ).toBeDisabled();
				await expect(
					row.getByRole( 'button', { name: 'Save', exact: true } )
				).toBeDisabled();
				await expect(
					row.getByRole( 'button', { name: 'Cancel', exact: true } )
				).toBeDisabled();
				await expect.poll( () => removalRequests ).toBe( 1 );
				await input.press( 'Enter' );
				await input.press( 'Escape' );
				await expect( input ).toBeVisible();
			} finally {
				releaseResponse();
			}

			await expect( page.getByRole( 'alert' ) ).toContainText(
				'Removal failed for testing.'
			);
			expect( removalRequests ).toBe( 1 );
			await expect( input ).toBeVisible();
			await expect( remove ).toBeEnabled();
			await expect(
				row.getByRole( 'button', { name: 'Save', exact: true } )
			).toBeEnabled();
			await expect(
				row.getByRole( 'button', { name: 'Cancel', exact: true } )
			).toBeEnabled();

			const failedRemoval = await requestUtils.rest( { path } );
			expect( failedRemoval.expires ).toBe( app.expires );
			await page.unroute( routePattern );
			await remove.click();
			await expect( page.getByRole( 'alert' ) ).toContainText(
				'Application password expiration updated.'
			);

			const cleared = await requestUtils.rest( { path } );
			expect( cleared.expires ).toBeNull();
			await expect( row.locator( '.edit-expires' ) ).toBeFocused();
			await expect( row.locator( '.column-expires' ) ).toContainText(
				'—'
			);
			await row.locator( '.edit-expires' ).click();
			await expect( input ).toHaveValue( '' );
			await expect( remove ).toHaveCount( 0 );
			await row
				.getByRole( 'button', { name: 'Cancel', exact: true } )
				.click();
			await page.reload();
			await expect( row.locator( '.column-expires' ) ).toContainText(
				'—'
			);
			await row.locator( '.edit-expires' ).click();
			await expect( input ).toHaveValue( '' );
			await expect( remove ).toHaveCount( 0 );

			// Add the original expiry back after removing it, then verify it survives reload.
			await input.fill( originalExpires );
			await row
				.getByRole( 'button', { name: 'Save', exact: true } )
				.click();
			await expect( page.getByRole( 'alert' ) ).toContainText(
				'Application password expiration updated.'
			);
			const restored = await requestUtils.rest( { path } );
			expect( restored.expires ).toBe( app.expires );
			await page.reload();
			await row.locator( '.edit-expires' ).click();
			await expect( input ).toHaveValue( originalExpires );
			await expect( remove ).toBeVisible();

			// A password created without any expiry must support adding and then removing one.
			const noExpiry = await requestUtils.rest( {
				method: 'POST',
				path: `/wp/v2/users/${ user.id }/application-passwords`,
				data: { name: 'Initially without expiry' },
			} );
			expect( noExpiry.expires ).toBeNull();
			await page.reload();
			const noExpiryRow = page.locator(
				`tr[data-uuid="${ noExpiry.uuid }"]`
			);
			const noExpiryInput = noExpiryRow.getByLabel(
				'Expiration date and time',
				{ exact: true }
			);
			const noExpiryRemove = noExpiryRow.getByRole( 'button', {
				name: 'Remove expiry',
				exact: true,
			} );
			await noExpiryRow.locator( '.edit-expires' ).click();
			await expect( noExpiryInput ).toHaveValue( '' );
			await expect( noExpiryRemove ).toHaveCount( 0 );
			await noExpiryInput.fill( originalExpires );
			await noExpiryRow
				.getByRole( 'button', { name: 'Save', exact: true } )
				.click();
			await expect( page.getByRole( 'alert' ) ).toContainText(
				'Application password expiration updated.'
			);
			const noExpiryPath = `/wp/v2/users/${ user.id }/application-passwords/${ noExpiry.uuid }`;
			const added = await requestUtils.rest( { path: noExpiryPath } );
			expect( added.expires ).toBe( app.expires );
			await page.reload();
			await noExpiryRow.locator( '.edit-expires' ).click();
			await expect( noExpiryInput ).toHaveValue( originalExpires );
			await noExpiryRemove.click();
			await expect( page.getByRole( 'alert' ) ).toContainText(
				'Application password expiration updated.'
			);
			const removedAgain = await requestUtils.rest( {
				path: noExpiryPath,
			} );
			expect( removedAgain.expires ).toBeNull();
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/users/${ user.id }`,
				params: { force: true, reassign: currentUser.id },
			} );
		}
	} );
} );

class ApplicationPasswords {
	constructor( { requestUtils, page, admin } ) {
		this.requestUtils = requestUtils;
		this.page = page;
		this.admin = admin;
	}

	async create( applicationName = TEST_APPLICATION_NAME, expires = null ) {
		await this.admin.visitAdminPage( '/profile.php' );

		const newPasswordField = this.page.getByRole( 'textbox', {
			name: 'New Application Password Name',
		} );
		await expect( newPasswordField ).toBeVisible();
		await newPasswordField.fill( applicationName );

		if ( expires ) {
			const newPasswordExpiresField =
				this.page.getByLabel( 'Expires on' );
			await expect( newPasswordExpiresField ).toBeVisible();
			await newPasswordExpiresField.fill( expires );
		}

		await this.page
			.getByRole( 'button', { name: 'Add Application Password' } )
			.click();
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
