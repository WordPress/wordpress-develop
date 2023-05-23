import {
	activatePlugin,
	deactivatePlugin,
	installPlugin,
	uninstallPlugin,
} from '@wordpress/e2e-test-utils';

describe( 'Gutenberg plugin', () => {
	beforeAll( async () => {
		await installPlugin( 'gutenberg' );
	} );

	afterAll( async () => {
		await uninstallPlugin( 'gutenberg' );
	} );

	it( 'should activate', async () => {
		await activatePlugin( 'gutenberg' );
		/*
		 * If plugin activation fails, it will time out and throw an error,
		 * since the activatePlugin helper is looking for a `.deactivate` link
		 * which is only there if activation succeeds.
		 */
		await deactivatePlugin( 'gutenberg' );
	} );
} );
