/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';
import { signIn } from '../fixtures/user';

test.describe( 'User management', () => {
	let prefix;

	test.beforeEach( async () => {
		prefix = `e2e-user-${ randomUUID() }`;
	} );

	test( 'creates a user who can log in', async ( {
		admin,
		page,
		browser,
		userFactory,
	} ) => {
		const username = `${ prefix }-new`;
		await userFactory.adopt( username );
		await admin.visitAdminPage( '/user-new.php' );
		await page
			.getByLabel( 'Username (required)', { exact: true } )
			.fill( username );
		await page
			.getByLabel( 'Email (required)', { exact: true } )
			.fill( `${ username }@example.org` );
		const passwordField = page.locator( '#pass1' );
		await expect( passwordField ).not.toHaveValue( '' );
		const password = await passwordField.inputValue();
		await page
			.getByLabel( 'Send the new user an email about their account' )
			.uncheck();
		await page
			.getByRole( 'button', { name: 'Add User', exact: true } )
			.click();
		await expect( page.locator( '#message' ) ).toContainText(
			'New user created.'
		);

		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		try {
			const loginPage = await context.newPage();
			await signIn( loginPage, username, password );
			await expect(
				loginPage.getByRole( 'heading', {
					name: 'Profile',
					exact: true,
				} )
			).toBeVisible();
			await expect( loginPage.locator( '#user_login' ) ).toHaveValue(
				username
			);
		} finally {
			await context.close();
		}
	} );

	test( 'bulk deletes selected users and keeps the unselected user', async ( {
		admin,
		page,
		requestUtils,
		userFactory,
	} ) => {
		const users = [];
		for ( const suffix of [ 'first', 'second', 'control' ] ) {
			const { user } = await userFactory.create( {
				username: `${ prefix }-${ suffix }`,
				roles: [ 'subscriber' ],
			} );
			users.push( user );
		}
		await admin.visitAdminPage( '/users.php', `s=${ prefix }` );
		await page
			.locator( `#user-${ users[ 0 ].id }` )
			.getByRole( 'checkbox' )
			.check();
		await page
			.locator( `#user-${ users[ 1 ].id }` )
			.getByRole( 'checkbox' )
			.check();
		await page
			.locator( '#bulk-action-selector-top' )
			.selectOption( 'delete' );
		await page.locator( '#doaction' ).click();
		await expect(
			page.getByRole( 'heading', { name: 'Delete Users', exact: true } )
		).toBeVisible();
		await page
			.getByRole( 'button', { name: 'Confirm Deletion', exact: true } )
			.click();
		await expect( page.locator( '#message' ) ).toContainText(
			'2 users deleted.'
		);
		await expect( page.locator( `#user-${ users[ 0 ].id }` ) ).toBeHidden();
		await expect( page.locator( `#user-${ users[ 1 ].id }` ) ).toBeHidden();
		await expect(
			page.locator( `#user-${ users[ 2 ].id }` )
		).toBeVisible();
		const remaining = await requestUtils.rest( {
			path: '/wp/v2/users',
			params: {
				include: users.map( ( user ) => user.id ),
				context: 'edit',
			},
		} );
		expect( remaining.map( ( user ) => user.id ) ).toEqual( [
			users[ 2 ].id,
		] );
	} );

	test( 'prevents an administrator from changing their own role to subscriber', async ( {
		browser,
		requestUtils,
		baseURL,
		userFactory,
	} ) => {
		// Use a disposable administrator so a regression cannot demote the shared
		// suite account and break cleanup or later tests.
		const {
			user: disposable,
			username,
			password,
		} = await userFactory.create( {
			username: `${ prefix }-self-role`,
			roles: [ 'administrator' ],
		} );

		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		try {
			const page = await context.newPage();
			await signIn( page, username, password );
			await expect(
				page.getByRole( 'heading', {
					name: 'Dashboard',
					exact: true,
				} )
			).toBeVisible();

			await page.goto( new URL( 'wp-admin/profile.php', baseURL ).href );
			await expect(
				page.getByLabel( 'Role', { exact: true } )
			).toHaveCount( 0 );

			await page.goto(
				new URL(
					`wp-admin/users.php?s=${ encodeURIComponent( username ) }`,
					baseURL
				).href
			);
			await page
				.locator( `#user-${ disposable.id }` )
				.getByRole( 'checkbox' )
				.check();
			// Top/bottom role controls share an accessible name, so use the top control ID.
			await page.locator( '#new_role' ).selectOption( 'subscriber' );
			await page.locator( '#changeit' ).click();
			await expect( page.locator( '#message.error' ) ).toContainText(
				'You cannot change your own role to one that does not allow managing other users. Your role was not changed.'
			);

			const stillAdmin = await requestUtils.rest( {
				path: `/wp/v2/users/${ disposable.id }`,
				params: { context: 'edit' },
			} );
			expect( stillAdmin.roles ).toContain( 'administrator' );
		} finally {
			await context.close();
		}
	} );

	test( 'shows an empty result when searching for a missing user', async ( {
		admin,
		page,
		userFactory,
	} ) => {
		// Ensure at least one non-matching user exists so ignoring `s` cannot
		// vacuously pass with an empty users table.
		await userFactory.create( {
			username: `${ prefix }-present`,
			roles: [ 'subscriber' ],
		} );
		const missing = `${ prefix }-missing-${ randomUUID().slice( 0, 8 ) }`;

		await admin.visitAdminPage(
			'/users.php',
			`s=${ encodeURIComponent( missing ) }`
		);
		await expect(
			page.getByRole( 'cell', {
				name: 'No users found.',
				exact: true,
			} )
		).toBeVisible();
	} );
} );
