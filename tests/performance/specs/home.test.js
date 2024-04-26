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
	largestContentfulPaint: [],
	lcpMinusTtfb: [],
};

const themes = [ 'twentytwentyone', 'twentytwentythree', 'twentytwentyfour' ];

const locales = [ 'en_US', 'de_DE' ];

test.describe( 'Front End', () => {
	test.use( {
		storageState: {}, // User will be logged out.
	} );

	for ( const theme of themes ) {
		for ( const locale of locales ) {
			test.describe( `Theme: ${ theme }, Locale: ${ locale }`, () => {
				test.beforeAll( async ( { requestUtils } ) => {
					await requestUtils.activateTheme( theme );
					await requestUtils.updateSiteSettings( {
						language: 'en_US' === locale ? '' : locale,
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

					results.largestContentfulPaint = [];
					results.timeToFirstByte = [];
					results.lcpMinusTtfb = [];
				} );

				const iterations = Number( process.env.TEST_RUNS );
				for ( let i = 1; i <= iterations; i++ ) {
					test( `Measure load time metrics (${ i } of ${ iterations })`, async ( {
						page,
						metrics,
					} ) => {
						await page.goto( '/' );

						const serverTiming = await metrics.getServerTiming();

						for ( const [ key, value ] of Object.entries(
							serverTiming
						) ) {
							results[ camelCaseDashes( key ) ] ??= [];
							results[ camelCaseDashes( key ) ].push( value );
						}

						const ttfb = await metrics.getTimeToFirstByte();
						const lcp = await metrics.getLargestContentfulPaint();

						results.largestContentfulPaint.push( lcp );
						results.timeToFirstByte.push( ttfb );
						results.lcpMinusTtfb.push( lcp - ttfb );
					} );
				}
			} );
		}
	}
} );
