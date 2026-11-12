/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Logs in through wp-login.php. Callers assert their own landing page, because it depends on the user's role.
 *
 * @param {import('@playwright/test').Page} page     A logged-out page.
 * @param {string}                          username The username.
 * @param {string}                          password The password.
 */
export async function signIn( page, username, password ) {
	// Only the `page` fixture resolves URLs against a `baseURL` with a subdirectory; this page may come from another context.
	const { baseURL } = test.info().project.use;
	await page.goto(
		new URL(
			'wp-login.php',
			baseURL.endsWith( '/' ) ? baseURL : `${ baseURL }/`
		).href
	);
	const usernameField = page.getByLabel( 'Username or Email Address', {
		exact: true,
	} );
	// Wait for WordPress's delayed autofocus before entering credentials.
	await expect( usernameField ).toBeFocused();
	await usernameField.fill( username );
	await page.getByLabel( 'Password', { exact: true } ).fill( password );
	await page.getByRole( 'button', { name: 'Log In', exact: true } ).click();
}

async function findUserId( requestUtils, username ) {
	const users = await requestUtils.rest( {
		path: '/wp/v2/users',
		params: { search: username, context: 'edit' },
	} );
	return users.find( ( user ) => user.username === username )?.id;
}

async function deleteUser( requestUtils, id ) {
	// User deletion only trashes posts; permanently delete this user's Quick Draft auto-drafts first.
	const drafts = await requestUtils.rest( {
		path: '/wp/v2/posts',
		params: { author: id, status: 'auto-draft', per_page: 100 },
	} );
	for ( const draft of drafts ) {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/posts/${ draft.id }`,
			params: { force: true },
		} );
	}
	try {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/users/${ id }`,
			params: { force: true, reassign: false },
		} );
	} catch ( error ) {
		// A test may delete its own users.
		if ( error.data?.status !== 404 ) {
			throw error;
		}
	}
}

/**
 * Creates users for a test. Teardown deletes them, also when the test fails.
 *
 * `create( { roles, username?, password?, ...data } )` resolves to `{ user, username, password }`.
 *
 * `adopt( username )` records a user that the test creates through the UI. Call it before the
 * form is submitted, so that teardown also runs if a later step fails. Teardown skips the
 * username if no such user exists.
 */
export const userFactory = async ( { requestUtils }, use ) => {
	const tracked = [];

	await use( {
		create: async ( {
			roles,
			username = `e2e-user-${ randomUUID() }`,
			password = randomUUID(),
			...data
		} ) => {
			const user = await requestUtils.createUser( {
				email: `${ username }@example.org`,
				...data,
				username,
				password,
				roles,
			} );
			tracked.push( { id: user.id } );
			return { user, username, password };
		},
		adopt: async ( username ) => {
			tracked.push( { username } );
		},
	} );

	const errors = [];
	for ( const { id, username } of tracked ) {
		try {
			const userId = id ?? ( await findUserId( requestUtils, username ) );
			if ( userId ) {
				await deleteUser( requestUtils, userId );
			}
		} catch ( error ) {
			errors.push( error );
		}
	}
	if ( errors.length ) {
		throw errors[ 0 ];
	}
};
