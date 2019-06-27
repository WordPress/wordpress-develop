import { visitAdminPage } from '@wordpress/e2e-test-utils';

describe( 'Hello World', () => {
	it( 'Should load properly', async () => {
		await visitAdminPage( '/' );
		const title = await page.$x(
			'//h2[contains(text(), "Welcome to WordPress!")]'
		);
		expect( title ).not.toBeNull();
	} );
} );
