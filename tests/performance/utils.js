/**
 * Computes the median number from an array numbers.
 *
 * @param {number[]} array
 *
 * @return {number} Median.
 */
function median( array ) {
	const mid = Math.floor( array.length / 2 );
	const numbers = [ ...array ].sort( ( a, b ) => a - b );
	return array.length % 2 !== 0
		? numbers[ mid ]
		: ( numbers[ mid - 1 ] + numbers[ mid ] ) / 2;
}

/**
 * Gets the result file name.
 *
 * @param {string} File name.
 *
 * @return {string} Result file name.
 */
function getResultsFilename( fileName ) {
	const prefixArg = process.argv.find( ( arg ) =>
		arg.startsWith( '--prefix' )
	);
	const fileNamePrefix = prefixArg ? `${ prefixArg.split( '=' )[ 1 ] }-` : '';
	const resultsFilename = fileNamePrefix + fileName + '.results.json';
	return resultsFilename;
}

/**
 * Returns time to first byte (TTFB) using the Navigation Timing API.
 *
 * @see https://web.dev/ttfb/#measure-ttfb-in-javascript
 *
 * @return {Promise<number>}
 */
async function getTimeToFirstByte() {
	return page.evaluate( () => {
		const { responseStart, startTime } =
			performance.getEntriesByType( 'navigation' )[ 0 ];
		return responseStart - startTime;
	} );
}

/**
 * Returns the Largest Contentful Paint (LCP) value using the dedicated API.
 *
 * @see https://w3c.github.io/largest-contentful-paint/
 * @see https://web.dev/lcp/#measure-lcp-in-javascript
 *
 * @return {Promise<number>}
 */
async function getLargestContentfulPaint() {
	return page.evaluate(
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
}

module.exports = {
	median,
	getResultsFilename,
	getTimeToFirstByte,
	getLargestContentfulPaint,
};
