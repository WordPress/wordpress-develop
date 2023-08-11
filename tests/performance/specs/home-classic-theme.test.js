/**
 * External dependencies.
 */
const { basename, join } = require( 'path' );
const { writeFileSync } = require( 'fs' );
const { exec } = require( 'child_process' );
const { getResultsFilename } = require( './../utils' );

/**
 * WordPress dependencies.
 */
import { activateTheme, createURL } from '@wordpress/e2e-test-utils';

describe( 'Server Timing - Twenty Twenty One', () => {
	const results = {
		wpBeforeTemplate: [],
		wpTemplate: [],
		wpTotal: [],
		lcp: [],
		ttfb: [],
		lcpMinusTtfb: [],
	};

	beforeAll( async () => {
		await activateTheme( 'twentytwentyone' );
		await exec(
			'npm run env:cli -- menu location assign all-pages primary'
		);
	} );

	afterAll( async () => {
		const resultsFilename = getResultsFilename(
			basename( __filename, '.js' )
		);
		writeFileSync(
			join( __dirname, resultsFilename ),
			JSON.stringify( results, null, 2 )
		);
	} );

	it( 'Server Timing Metrics', async () => {
		let i = TEST_RUNS;
		while ( i-- ) {
			await page.goto( createURL( '/' ) );
			const navigationTimingJson = await page.evaluate( () =>
				JSON.stringify( performance.getEntriesByType( 'navigation' ) )
			);

			const [ navigationTiming ] = JSON.parse( navigationTimingJson );

			results.wpBeforeTemplate.push(
				navigationTiming.serverTiming[ 0 ].duration
			);
			results.wpTemplate.push(
				navigationTiming.serverTiming[ 1 ].duration
			);
			results.wpTotal.push( navigationTiming.serverTiming[ 2 ].duration );

			const ttfb = await page.evaluate(
				() =>
					new Promise( ( resolve ) => {
						new PerformanceObserver( ( entryList ) => {
							const [ pageNav ] =
								entryList.getEntriesByType( 'navigation' );

							resolve( pageNav.responseStart );
						} ).observe( {
							type: 'navigation',
							buffered: true,
						} );
					} )
			);

			const lcp = await page.evaluate(
				() =>
					new Promise( ( resolve ) => {
						new PerformanceObserver( ( entryList ) => {
							const entries = entryList.getEntries();
							// The last entry is the largest contentful paint.
							const largestPaintEntry = entries.at( -1 );

							resolve( largestPaintEntry?.startTime || 0 );
						} ).observe( {
							type: 'largest-contentful-paint',
							buffered: true,
						} );
					} )
			);

			results.ttfb.push( ttfb );
			results.lcp.push( lcp );
			results.lcpMinusTtfb.push( lcp - ttfb );
		}
	} );
} );
