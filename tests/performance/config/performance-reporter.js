/**
 * External dependencies
 */
import { join, basename } from 'node:path';
import { writeFileSync } from 'node:fs';

/**
 * Internal dependencies
 */
import { getResultsFilename } from '../utils';

/**
 * @implements {import('@playwright/test/reporter').Reporter}
 */
class PerformanceReporter {
	/**
	 *
	 * @param {import('@playwright/test/reporter').TestCase} test
	 * @param {import('@playwright/test/reporter').TestResult} result
	 */
	onTestEnd( test, result ) {
		const performanceResults = result.attachments.find(
			( attachment ) => attachment.name === 'results'
		);

		if ( performanceResults?.body ) {
			writeFileSync(
				join(
					process.env.WP_ARTIFACTS_PATH,
					getResultsFilename( basename( test.location.file, '.test.js' ) )
				),
				performanceResults.body.toString( 'utf-8' )
			);
		}
	}
}

export default PerformanceReporter;
