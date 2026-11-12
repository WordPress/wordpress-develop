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
 * Returns the number and transfer size of the scripts and stylesheets the page loads from its markup.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<{scriptCount: number, scriptTransferSize: number, stylesheetCount: number, stylesheetTransferSize: number}>} Asset metrics.
 */
async function getPageAssetMetrics( page ) {
	return await page.evaluate( () => {
		const resources = performance.getEntriesByType( 'resource' );

		const scriptUrls = new Set(
			Array.from(
				document.querySelectorAll( 'script[src]' ),
				( script ) => script.src
			)
		);
		const stylesheetUrls = new Set(
			Array.from(
				document.querySelectorAll( 'link[rel="stylesheet"][href]' ),
				( link ) => link.href
			)
		);

		const sumTransferSize = ( urls ) =>
			resources
				.filter( ( resource ) => urls.has( resource.name ) )
				.reduce( ( total, resource ) => total + resource.transferSize, 0 );

		return {
			scriptCount: scriptUrls.size,
			scriptTransferSize: sumTransferSize( scriptUrls ),
			stylesheetCount: stylesheetUrls.size,
			stylesheetTransferSize: sumTransferSize( stylesheetUrls ),
		};
	} );
}

function createEmptyResults() {
	return {
		timeToFirstByte: [],
		domContentLoaded: [],
		scriptCount: [],
		scriptTransferSize: [],
		stylesheetCount: [],
		stylesheetTransferSize: [],
	};
}

for ( const screen of screens ) {
	test.describe( screen.suiteTitle, () => {
		let results = createEmptyResults();

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

					results = createEmptyResults();
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
							results[ camelCaseDashes( key ) ] ??= [];
							results[ camelCaseDashes( key ) ].push( value );
						}

						results.timeToFirstByte.push(
							await metrics.getTimeToFirstByte()
						);

						const { domContentLoaded } =
							await metrics.getLoadingDurations();
						results.domContentLoaded.push( domContentLoaded );

						for ( const [ key, value ] of Object.entries(
							await getPageAssetMetrics( page )
						) ) {
							results[ key ].push( value );
						}
					} );
				}
			} );
		}
	} );
}
