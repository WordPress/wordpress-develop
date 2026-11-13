/**
 * WordPress dependencies
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { camelCaseDashes, locales } from '../utils';

const screens = [
	{
		suiteTitle: 'Admin',
		path: '/',
	},
	{
		suiteTitle: 'Admin General Settings',
		path: 'options-general.php',
	},
];

/**
 * Returns the number and size of the scripts and stylesheets in the page markup.
 *
 * Sizes are encoded body sizes, which do not depend on whether a response came from the HTTP cache.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<{scriptCount: number, scriptBytes: number, stylesheetCount: number, stylesheetBytes: number}>} Asset metrics.
 */
async function getPageAssetMetrics( page ) {
	return await page.evaluate( () => {
		const resources = performance.getEntriesByType( 'resource' );
		const measure = ( selector, attribute ) => {
			const urls = new Set(
				Array.from(
					document.querySelectorAll( selector ),
					( element ) => element[ attribute ]
				)
			);
			const bytes = resources
				.filter( ( resource ) => urls.has( resource.name ) )
				.reduce( ( total, resource ) => total + resource.encodedBodySize, 0 );
			return [ urls.size, bytes ];
		};

		const [ scriptCount, scriptBytes ] = measure( 'script[src]', 'src' );
		const [ stylesheetCount, stylesheetBytes ] = measure(
			'link[rel="stylesheet"][href]',
			'href'
		);

		return { scriptCount, scriptBytes, stylesheetCount, stylesheetBytes };
	} );
}

/**
 * Returns the used JavaScript heap size after a forced garbage collection.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<number>} Used heap size in bytes.
 */
async function getJsHeapBytes( page ) {
	const session = await page.context().newCDPSession( page );
	await session.send( 'HeapProfiler.collectGarbage' );
	const { usedSize } = await session.send( 'Runtime.getHeapUsage' );
	await session.detach();
	return usedSize;
}

for ( const screen of screens ) {
	test.describe( screen.suiteTitle, () => {
		let results = {};
		const record = ( key, value ) => {
			results[ key ] ??= [];
			results[ key ].push( value );
		};

		for ( const locale of locales ) {
			test.describe( `Locale: ${ locale }`, () => {
				test.beforeAll( async ( { requestUtils } ) => {
					await requestUtils.activateTheme( 'twentytwentyone' );
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

					results = {};
				} );

				const iterations = Number( process.env.TEST_RUNS );
				for ( let i = 1; i <= iterations; i++ ) {
					test( `Measure load time metrics (${ i } of ${ iterations })`, async ( {
						page,
						admin,
						metrics,
					} ) => {
						// Clear caches using the clear-cache.php mu-plugin. Not actually loading the page.
						await page.goto( '/?clear_cache' );

						// This is the actual page to test.
						await admin.visitAdminPage( screen.path );

						const serverTiming = await metrics.getServerTiming();

						for ( const [ key, value ] of Object.entries(
							serverTiming
						) ) {
							record( camelCaseDashes( key ), value );
						}

						record(
							'timeToFirstByte',
							await metrics.getTimeToFirstByte()
						);
						record(
							'domContentLoaded',
							( await metrics.getLoadingDurations() ).domContentLoaded
						);

						for ( const [ key, value ] of Object.entries(
							await getPageAssetMetrics( page )
						) ) {
							record( key, value );
						}

						record( 'jsHeapBytes', await getJsHeapBytes( page ) );
					} );
				}
			} );
		}
	} );
}
