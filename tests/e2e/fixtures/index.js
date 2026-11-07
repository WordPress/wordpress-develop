/**
 * WordPress dependencies
 */
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { muPlugin, siteOptions, siteSettings } from './site';

export const test = base.extend( {
	loggedOutPage: async ( { browser }, use ) => {
		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		await use( await context.newPage() );
		await context.close();
	},
	muPlugin,
	siteOptions,
	siteSettings,
} );

export { expect };
