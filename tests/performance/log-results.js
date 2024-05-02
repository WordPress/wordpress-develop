#!/usr/bin/env node

/*
 * Get the test results and format them in the way required by the API.
 *
 * Contains some backward compatibility logic for the original test suite format,
 * see #59900 for details.
 */

/**
 * External dependencies.
 */
const https = require( 'https' );
const [ token, branch, hash, baseHash, timestamp, host ] =
	process.argv.slice( 2 );
const { median, parseFile, accumulateValues } = require( './utils' );

const testSuiteMap = {
	'Admin › Locale: en_US': 'admin',
	'Admin › Locale: de_DE': 'admin-l10n',
	'Front End › Theme: twentytwentyone, Locale: en_US': 'home-classic-theme',
	'Front End › Theme: twentytwentyone, Locale: de_DE':
		'home-classic-theme-l10n',
	'Front End › Theme: twentytwentythree, Locale: en_US': 'home-block-theme',
	'Front End › Theme: twentytwentythree, Locale: de_DE':
		'home-block-theme-l10n',
};

/**
 * @type {Array<{file: string, title: string, results: Record<string,number[]>[]}>}
 */
const afterStats = parseFile( 'performance-results.json' );

/**
 * @type {Array<{file: string, title: string, results: Record<string,number[]>[]}>}
 */
const baseStats = parseFile( 'base-performance-results.json' );

/**
 * @type {Record<string, number>}
 */
const metrics = {};
/**
 * @type {Record<string, number>}
 */
const baseMetrics = {};

for ( const { title, results } of afterStats ) {
	const testSuiteName = testSuiteMap[ title ];
	if ( ! testSuiteName ) {
		continue;
	}

	const baseStat = baseStats.find( ( s ) => s.title === title );

	const currResults = accumulateValues( results );
	const baseResults = accumulateValues( baseStat.results );

	for ( const [ metric, values ] of Object.entries( currResults ) ) {
		metrics[ `${ testSuiteName }-${ metric }` ] = median( values );
	}

	for ( const [ metric, values ] of Object.entries( baseResults ) ) {
		baseMetrics[ `${ testSuiteName }-${ metric }` ] = median( values );
	}
}

process.exit( 0 );

/**
 * Gets the array of metrics from a list of results.
 *
 * @param {Object[]} results A list of results to format.
 * @return {Object} Metrics.
 */
const formatResults = ( results ) => {
	return results.reduce( ( result, { key, file } ) => {
		return {
			...result,
			...Object.fromEntries(
				Object.entries( parseFile( file ) ?? {} ).map(
					( [ metric, value ] ) => [
						key + '-' + metric,
						median( value ),
					]
				)
			),
		};
	}, {} );
};

const data = new TextEncoder().encode(
	JSON.stringify( {
		branch,
		hash,
		baseHash,
		timestamp: parseInt( timestamp, 10 ),
		metrics: formatResults( testResults ),
		baseMetrics: formatResults( baseResults ),
	} )
);

const options = {
	hostname: host,
	port: 443,
	path: '/api/log?token=' + token,
	method: 'POST',
	headers: {
		'Content-Type': 'application/json',
		'Content-Length': data.length,
	},
};

const req = https.request( options, ( res ) => {
	console.log( `statusCode: ${ res.statusCode }` );

	res.on( 'data', ( d ) => {
		process.stdout.write( d );
	} );
} );

req.on( 'error', ( error ) => {
	console.error( error );
} );

req.write( data );
req.end();
