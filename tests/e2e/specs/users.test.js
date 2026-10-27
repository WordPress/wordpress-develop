/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';
import { existsSync, mkdirSync, writeFileSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'User management', () => {
	let prefix;
	let usernames;

	test.beforeEach( async () => {
		prefix = `e2e-user-${ randomUUID() }`;
		usernames = [];
	} );

	test.afterEach( async ( { requestUtils } ) => {
		const users = await requestUtils.rest( {
			path: '/wp/v2/users',
			params: { search: prefix, context: 'edit' },
		} );
		const adminUser = await requestUtils.rest( {
			path: '/wp/v2/users/me',
		} );
		for ( const user of users.filter( ( item ) =>
			usernames.includes( item.username )
		) ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/users/${ user.id }`,
				params: { force: true, reassign: adminUser.id },
			} );
		}
	} );

	test( 'creates a user who can log in', async ( {
		admin,
		page,
		browser,
	} ) => {
		const username = `${ prefix }-new`;
		usernames.push( username );
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
			await loginPage.goto(
				new URL( '../wp-login.php', page.url() ).href
			);
			const usernameField = loginPage.getByLabel(
				'Username or Email Address',
				{ exact: true }
			);
			// Wait for WordPress's delayed autofocus before entering credentials.
			await expect( usernameField ).toBeFocused();
			await usernameField.fill( username );
			await loginPage
				.getByLabel( 'Password', { exact: true } )
				.fill( password );
			await loginPage
				.getByRole( 'button', { name: 'Log In', exact: true } )
				.click();
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
	} ) => {
		const users = [];
		for ( const suffix of [ 'first', 'second', 'control' ] ) {
			const username = `${ prefix }-${ suffix }`;
			usernames.push( username );
			users.push(
				await requestUtils.createUser( {
					username,
					email: `${ username }@example.org`,
					password: randomUUID(),
					roles: [ 'subscriber' ],
				} )
			);
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

	test.describe( 'Send password reset', () => {
		const muPlugins = join(
			process.cwd(),
			process.env.LOCAL_DIR ?? 'src',
			'wp-content/mu-plugins'
		);
		const muPluginFile = join( muPlugins, 'e2e-pre-wp-mail.php' );

		/**
		 * Short-circuits wp_mail() so that sending either succeeds or fails.
		 *
		 * @param {boolean} succeed Whether wp_mail() should report success.
		 */
		function mockMail( succeed ) {
			if ( ! existsSync( muPlugins ) ) {
				mkdirSync( muPlugins, { recursive: true } );
			}
			writeFileSync(
				muPluginFile,
				`<?php add_filter( 'pre_wp_mail', '__return_${
					succeed ? 'true' : 'false'
				}' );`
			);
		}

		async function createUsers( requestUtils, count ) {
			const users = [];
			for ( let i = 0; i < count; i++ ) {
				const username = `${ prefix }-reset-${ i }`;
				usernames.push( username );
				users.push(
					await requestUtils.createUser( {
						username,
						email: `${ username }@example.org`,
						password: randomUUID(),
						roles: [ 'subscriber' ],
					} )
				);
			}
			return users;
		}

		async function bulkResetPassword( page, userIds ) {
			for ( const id of userIds ) {
				await page
					.locator( `#user-${ id }` )
					.getByRole( 'checkbox' )
					.check();
			}
			await page
				.locator( '#bulk-action-selector-top' )
				.selectOption( 'resetpassword' );
			await page.locator( '#doaction' ).click();
		}

		test.afterEach( () => {
			if ( existsSync( muPluginFile ) ) {
				unlinkSync( muPluginFile );
			}
		} );

		test( 'shows an error when the email fails to send from the row action', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			mockMail( false );
			const [ user ] = await createUsers( requestUtils, 1 );
			await admin.visitAdminPage( '/users.php', `s=${ prefix }` );
			const row = page.locator( `#user-${ user.id }` );
			await row.hover();
			await row
				.getByRole( 'link', { name: 'Send password reset' } )
				.click();

			await expect(
				page.locator( '.notice.error' ).filter( {
					hasText:
						'The password reset link could not be sent to 1 user.',
				} )
			).toBeVisible();
			await expect( page.locator( '.notice.updated' ) ).toHaveCount( 0 );
		} );

		test( 'shows an error when emails fail to send in bulk', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			mockMail( false );
			const users = await createUsers( requestUtils, 3 );
			await admin.visitAdminPage( '/users.php', `s=${ prefix }` );
			await bulkResetPassword(
				page,
				users.map( ( user ) => user.id )
			);

			await expect(
				page.locator( '.notice.error' ).filter( {
					hasText:
						'The password reset links could not be sent to 3 users.',
				} )
			).toBeVisible();
			await expect( page.locator( '.notice.updated' ) ).toHaveCount( 0 );
		} );

		test( 'reports the number of emails sent in bulk', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			mockMail( true );
			const users = await createUsers( requestUtils, 3 );
			await admin.visitAdminPage( '/users.php', `s=${ prefix }` );
			await bulkResetPassword(
				page,
				users.map( ( user ) => user.id )
			);

			await expect(
				page.locator( '.notice.updated' ).filter( {
					hasText: 'Password reset links sent to 3 users.',
				} )
			).toBeVisible();
			await expect( page.locator( '.notice.error' ) ).toHaveCount( 0 );
		} );

		test( 'shows an error when the current user is selected', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			mockMail( true );
			const users = await createUsers( requestUtils, 2 );
			const currentUser = await requestUtils.rest( {
				path: '/wp/v2/users/me',
			} );
			await admin.visitAdminPage( '/users.php' );
			await bulkResetPassword( page, [
				currentUser.id,
				...users.map( ( user ) => user.id ),
			] );

			await expect(
				page.locator( '.notice.error' ).filter( {
					hasText: 'You cannot reset your own password here.',
				} )
			).toBeVisible();
			await expect(
				page.locator( '.notice.updated' ).filter( {
					hasText: 'Password reset links sent to 2 users.',
				} )
			).toBeVisible();
			await expect( page.locator( '[id="message"]' ) ).toHaveCount( 1 );
		} );
	} );
} );
