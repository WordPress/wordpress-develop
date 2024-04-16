/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Gutenberg plugin', () => {
	// Increasing timeout to 5 minutes because potential plugin install could take longer.
	test.setTimeout( 300_000 );

	test.beforeAll( async ( { requestUtils } ) => {
		// Install Gutenberg plugin if it's not yet installed.
		const pluginsMap = await requestUtils.getPluginsMap();
		if ( ! pluginsMap.gutenberg ) {
			try {
				await requestUtils.rest( {
					method: 'POST',
					path: 'wp/v2/plugins?slug=gutenberg',
				} );
			} catch ( error ) {
				if (
					typeof error === 'object' &&
					error !== null &&
					Object.prototype.hasOwnProperty.call( error, 'code' ) &&
					error.code === 'incompatible_wp_required_version'
				) {
					test.skip();
				}
			}
		}

		// Refetch installed plugin details. It avoids stale values when the test installs the plugin.
		await requestUtils.getPluginsMap( /* forceRefetch */ true );
		await requestUtils.deactivatePlugin( 'gutenberg' );
	} );

	test( 'should activate', async ( { requestUtils }) => {
		let plugin = await requestUtils.rest( {
			path: 'wp/v2/plugins/gutenberg/gutenberg',
		} );

		expect( plugin.status ).toBe( 'inactive' );

		await requestUtils.activatePlugin( 'gutenberg' );

		plugin = await requestUtils.rest( {
			path: 'wp/v2/plugins/gutenberg/gutenberg',
		} );

		expect( plugin.status ).toBe( 'active' );

		await requestUtils.deactivatePlugin( 'gutenberg' );

		plugin = await requestUtils.rest( {
			path: 'wp/v2/plugins/gutenberg/gutenberg',
		} );

		expect( plugin.status ).toBe( 'inactive' );
	} );
} );
