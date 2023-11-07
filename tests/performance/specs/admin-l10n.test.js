/**
 * WordPress dependencies
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { camelCaseDashes } from '../utils';

const results = {
	timeToFirstByte: [],
};

test.describe( 'Admin (L10N)', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activateTheme( 'twentytwentyone' );
		await requestUtils.updateSiteSettings( {
			language: 'de_DE',
		} );
	} );

	test.afterAll( async ( { requestUtils }, testInfo ) => {
		await testInfo.attach( 'results', {
			body: JSON.stringify( results, null, 2 ),
			contentType: 'application/json',
		} );
		await requestUtils.updateSiteSettings( {
			language: '',
		} );
	} );

	const iterations = Number( process.env.TEST_RUNS );
	for ( let i = 1; i <= iterations; i++ ) {
		test( `Measure load time metrics (${ i } of ${ iterations })`, async ( {
			admin,
			metrics,
		} ) => {
			await admin.visitAdminPage( '/' );

			const serverTiming = await metrics.getServerTiming();

			for ( const [ key, value ] of Object.entries( serverTiming ) ) {
				results[ camelCaseDashes( key ) ] ??= [];
				results[ camelCaseDashes( key ) ].push( value );
			}

			const ttfb = await metrics.getTimeToFirstByte();
			results.timeToFirstByte.push( ttfb );
		} );
	}
} );
