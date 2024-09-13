/**
 * External dependencies.
 */
const { readFileSync, existsSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

process.env.WP_ARTIFACTS_PATH ??= join( process.cwd(), 'artifacts' );

/**
 * Parse test files into JSON objects.
 *
 * @param {string} fileName The name of the file.
 * @return {Array<{file: string, title: string, results: Record<string,number[]>[]}>} Parsed object.
 */
function parseFile( fileName ) {
	const file = join( process.env.WP_ARTIFACTS_PATH, fileName );
	if ( ! existsSync( file ) ) {
		return [];
	}

	return JSON.parse( readFileSync( file, 'utf8' ) );
}

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

function camelCaseDashes( str ) {
	return str.replace( /-([a-z])/g, function ( g ) {
		return g[ 1 ].toUpperCase();
	} );
}

/**
 * Formats an array of objects as a Markdown table.
 *
 * For example, this array:
 *
 * [
 * 	{
 * 	    foo: 123,
 * 	    bar: 456,
 * 	    baz: 'Yes',
 * 	},
 * 	{
 * 	    foo: 777,
 * 	    bar: 999,
 * 	    baz: 'No',
 * 	}
 * ]
 *
 * Will result in the following table:
 *
 * | foo | bar | baz |
 * |-----|-----|-----|
 * | 123 | 456 | Yes |
 * | 777 | 999 | No  |
 *
 * @param {Array<Object>} rows Table rows.
 * @returns {string} Markdown table content.
 */
function formatAsMarkdownTable( rows ) {
	let result = '';

	if ( ! rows.length ) {
		return result;
	}

	const headers = Object.keys( rows[ 0 ] );
	for ( const header of headers ) {
		result += `| ${ header } `;
	}
	result += '|\n';
	for ( const header of headers ) {
		result += '| ------ ';
	}
	result += '|\n';

	for ( const row of rows ) {
		for ( const value of Object.values( row ) ) {
			result += `| ${ value } `;
		}
		result += '|\n';
	}

	return result;
}

/**
 * Nicely formats a given value.
 *
 * @param {string} metric Metric.
 * @param {number} value
 */
function formatValue( metric, value ) {
	if ( null === value ) {
		return 'N/A';
	}

	if ( 'wpMemoryUsage' === metric ) {
		return `${ ( value / Math.pow( 10, 6 ) ).toFixed( 2 ) } MB`;
	}

	if ( 'wpExtObjCache' === metric ) {
		return 1 === value ? 'yes' : 'no';
	}

	if ( 'wpDbQueries' === metric ) {
		return value;
	}

	return `${ value.toFixed( 2 ) } ms`;
}

/**
 * Returns a Markdown link to a Git commit on the current GitHub repository.
 *
 * For example, turns `a5c3785ed8d6a35868bc169f07e40e889087fd2e`
 * into (https://github.com/wordpress/wordpress-develop/commit/36fe58a8c64dcc83fc21bddd5fcf054aef4efb27)[36fe58a].
 *
 * @param {string} sha Commit SHA.
 * @return string Link
 */
function linkToSha( sha ) {
	const repoName =
		process.env.GITHUB_REPOSITORY || 'wordpress/wordpress-develop';

	return `[${ sha.slice(
		0,
		7
	) }](https://github.com/${ repoName }/commit/${ sha })`;
}

function standardDeviation( array = [] ) {
	if ( ! array.length ) {
		return 0;
	}

	const mean = array.reduce( ( a, b ) => a + b ) / array.length;
	return Math.sqrt(
		array
			.map( ( x ) => Math.pow( x - mean, 2 ) )
			.reduce( ( a, b ) => a + b ) / array.length
	);
}

function medianAbsoluteDeviation( array = [] ) {
	if ( ! array.length ) {
		return 0;
	}

	const med = median( array );
	return median( array.map( ( a ) => Math.abs( a - med ) ) );
}

/**
 *
 * @param {Array<Record<string, number[]>>} results
 * @returns {Record<string, number[]>}
 */
function accumulateValues( results ) {
	return results.reduce( ( acc, result ) => {
		for ( const [ metric, values ] of Object.entries( result ) ) {
			acc[ metric ] = acc[ metric ] ?? [];
			acc[ metric ].push( ...values );
		}
		return acc;
	}, {} );
}

module.exports = {
	parseFile,
	median,
	camelCaseDashes,
	formatAsMarkdownTable,
	formatValue,
	linkToSha,
	standardDeviation,
	medianAbsoluteDeviation,
	accumulateValues,
};
