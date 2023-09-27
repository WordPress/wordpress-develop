/**
 * WordPress dependencies
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Gutenberg plugin', () => {
	test( 'should activate', async ( { requestUtils }) => {
		// Increasing timeout to 5 minutes because install could take longer.
		test.setTimeout( 300_000 );

		await requestUtils.rest( {
			method: 'POST',
			path: 'wp/v2/plugins?slug=gutenberg&status=active',
		} );

		// This flow will only work if the activation previously succeeded.
		await requestUtils.deactivatePlugin( 'gutenberg' );

		await requestUtils.rest( {
			method: 'DELETE',
			path: 'wp/v2/plugins/gutenberg',
		} );
	} );
} );
