import {
	createURL,
} from '@wordpress/e2e-test-utils';

import fetch from 'node-fetch';

describe( 'Load Styles', () => {
	it( 'Loads successfully.', async () => {
		const path = 'wp-admin/load-styles.php';
		const query = 'c=1&dir=ltr&load%5Bchunk_0%5D=dashicons,admin-bar,site-health,common,forms,admin-menu,dashboard,list-tables,edit,revisions,media,themes,about,nav-menus,wp-poi&load%5Bchunk_1%5D=nter,widgets,site-icon,l10n,buttons,wp-auth-check&ver=6.2-alpha-54642-src';
		const url = createURL( path, query );
		const response = await fetch( url, {
			method: 'GET',
		} );

		expect( response.status ).toBe( 200 );
	} );

} );
