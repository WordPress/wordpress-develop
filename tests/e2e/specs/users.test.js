/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';
import { signIn } from '../fixtures/user';
import {
	adminNotice,
	bulkActionApply,
	bulkActionSelect,
} from '../utils/admin';

test.describe( 'User management', () => {
	let prefix;

	test.beforeEach( async () => {
		prefix = `e2e-user-${ randomUUID() }`;
	} );

	test( 'creates a user who can log in', async ( {
		admin,
		page,
		loggedOutPage,
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
		await expect( adminNotice( page ) ).toContainText(
			'New user created.'
		);

		await signIn( loggedOutPage, username, password );
		await expect(
			loggedOutPage.getByRole( 'heading', {
				name: 'Profile',
				exact: true,
			} )
		).toBeVisible();
		await expect( loggedOutPage.locator( '#user_login' ) ).toHaveValue(
			username
		);
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
		await bulkActionSelect( page ).selectOption( 'delete' );
		await bulkActionApply( page ).click();
		await expect(
			page.getByRole( 'heading', { name: 'Delete Users', exact: true } )
		).toBeVisible();
		await page
			.getByRole( 'button', { name: 'Confirm Deletion', exact: true } )
			.click();
		await expect( adminNotice( page ) ).toContainText(
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
		loggedOutPage,
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

		await signIn( loggedOutPage, username, password );
		await expect(
			loggedOutPage.getByRole( 'heading', {
				name: 'Dashboard',
				exact: true,
			} )
		).toBeVisible();

		await loggedOutPage.goto(
			new URL( 'wp-admin/profile.php', baseURL ).href
		);
		await expect(
			loggedOutPage.getByLabel( 'Role', { exact: true } )
		).toHaveCount( 0 );

		await loggedOutPage.goto(
			new URL(
				`wp-admin/users.php?s=${ encodeURIComponent( username ) }`,
				baseURL
			).href
		);
		await loggedOutPage
			.locator( `#user-${ disposable.id }` )
			.getByRole( 'checkbox' )
			.check();
		// Top/bottom role controls share an accessible name, so use the top control ID.
		await loggedOutPage.locator( '#new_role' ).selectOption( 'subscriber' );
		await loggedOutPage.locator( '#changeit' ).click();
		await expect( loggedOutPage.locator( '#message.error' ) ).toContainText(
			'You cannot change your own role to one that does not allow managing other users. Your role was not changed.'
		);

		const stillAdmin = await requestUtils.rest( {
			path: `/wp/v2/users/${ disposable.id }`,
			params: { context: 'edit' },
		} );
		expect( stillAdmin.roles ).toContain( 'administrator' );
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
