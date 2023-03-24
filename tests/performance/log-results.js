#!/usr/bin/env node

/**
 * External dependencies.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const https = require( 'https' );
const [ token, branch, hash, baseHash, timestamp, host ] = process.argv.slice( 2 );
const { median } = require( './utils' );

// The list of test suites to log.
const testSuites = [
	'home-block-theme',
	'home-classic-theme',
];

// A list of results to parse based on test suites.
const testResults = testSuites.map(( key ) => ({
	key,
	file: `${ key }.test.results.json`,
}));

// A list of base results to parse based on test suites.
const baseResults = testSuites.map(( key ) => ({
	key,
	file: `base-${ key }.test.results.json`,
}));

/**
 * Parse test files into JSON objects.
 *
 * @param {string} fileName The name of the file.
 * @returns An array of parsed objects from each file.
 */
const parseFile = ( fileName ) => (
	JSON.parse(
		fs.readFileSync( path.join( __dirname, '/specs/', fileName ), 'utf8' )
	)
);

/**
 * Gets the array of metrics from a list of results.
 *
 * @param {Object[]} results A list of results to format.
 * @return {Object[]} Metrics.
 */
const formatResults = ( results ) => {
	return results.reduce(
		( result, { key, file } ) => {
			return {
				...result,
				...Object.fromEntries(
					Object.entries(
						parseFile( file ) ?? {}
					).map( ( [ metric, value ] ) => [
						key + '-' + metric,
						median ( value ),
					] )
				),
			};
		},
		{}
	);
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
