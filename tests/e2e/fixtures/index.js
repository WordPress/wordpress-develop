/**
 * WordPress dependencies
 */
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';

export const test = base.extend( {
	loggedOutPage: async ( { browser }, use ) => {
		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		await use( await context.newPage() );
		await context.close();
	},
} );

export { expect };
