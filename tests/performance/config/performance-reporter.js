/**
 * External dependencies
 */
import { join } from 'node:path';
import { writeFileSync, existsSync, mkdirSync } from 'node:fs';

/**
 * @implements {import('@playwright/test/reporter').Reporter}
 */
class PerformanceReporter {
	/**
	 *
	 * @type {Record<string,{title: string; results: Record< string, number[] >[];}>}
	 */
	allResults = {};

	/**
	 * Called after a test has been finished in the worker process.
	 *
	 * Used to add test results to the final summary of all tests.
	 *
	 * @param {import('@playwright/test/reporter').TestCase} test
	 * @param {import('@playwright/test/reporter').TestResult} result
	 */
	onTestEnd( test, result ) {
		const performanceResults = result.attachments.find(
			( attachment ) => attachment.name === 'results'
		);

		if ( performanceResults?.body ) {
			this.allResults[ test.location.file ] ??= {
				// 0 = empty, 1 = browser, 2 = file name, 3 = test suite name.
				title: test.titlePath()[ 3 ],
				results: [],
			};
			this.allResults[ test.location.file ].results.push(
				JSON.parse( performanceResults.body.toString( 'utf-8' ) )
			);
		}
	}

	/**
	 * Called after all tests have been run, or testing has been interrupted.
	 *
	 * Writes all raw numbers to a file for further processing,
	 * for example to compare with a previous run.
	 *
	 * @param {import('@playwright/test/reporter').FullResult} result
	 */
	onEnd( result ) {
		const summary = [];

		for ( const [ file, { title, results } ] of Object.entries(
			this.allResults
		) ) {
			summary.push( {
				file,
				title,
				results,
			} );
		}

		if ( ! existsSync( process.env.WP_ARTIFACTS_PATH ) ) {
			mkdirSync( process.env.WP_ARTIFACTS_PATH );
		}

		const prefix = process.env.TEST_RESULTS_PREFIX;
		const fileNamePrefix = prefix ? `${ prefix }-` : '';

		writeFileSync(
			join(
				process.env.WP_ARTIFACTS_PATH,
				`${ fileNamePrefix }performance-results.json`
			),
			JSON.stringify( summary, null, 2 )
		);
	}
}

export default PerformanceReporter;
