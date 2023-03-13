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
	const prefixArg = process.argv.find( ( arg ) => arg.startsWith( '--prefix' ) );
	const fileNamePrefix = prefixArg ? `${prefixArg.split( '=' )[1]}-` : '';
	const resultsFilename = fileNamePrefix + fileName + '.results.json';
	return resultsFilename;
}

module.exports = {
	median,
	getResultsFilename,
};
