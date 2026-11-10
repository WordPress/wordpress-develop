/**
 * WordPress dependencies
 */
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { postFactory } from './post';
import { muPlugin, siteOptions, siteSettings } from './site';
import { userFactory } from './user';

export const test = base.extend( {
	loggedOutPage: async ( { browser }, use ) => {
		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		await use( await context.newPage() );
		await context.close();
	},
	muPlugin,
	postFactory,
	siteOptions,
	siteSettings,
	userFactory,
} );

export { expect };
