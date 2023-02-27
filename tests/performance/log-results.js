#!/usr/bin/env node

/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const https = require( 'https' );
const [ token, branch, hash, baseHash, timestamp, host ] = process.argv.slice( 2 );
const { median } = require( './utils' );

// Results files recorded for the current and base commit.
const resultsFiles = [
	{
		file: 'home-block-theme.test.results.json',
		baseFile: 'base-home-block-theme.test.results.json',
		metricsPrefix: 'home-block-theme-',
	},
	{
		file: 'home-classic-theme.test.results.json',
		baseFile: 'base-home-classic-theme.test.results.json',
		metricsPrefix: 'home-classic-theme-',
	},
];

/**
 * Parse test files into JSON objects.
 *
 * @param {Object[]} files
 * @param {boolean} baseFile True if file is base file, otherwise false. Default false.
 * @returns An array of parsed objects from each file.
 */
const parseResults = ( files, isBaseFile ) => (
	files.map( ( { file, baseFile } ) =>
		JSON.parse(
			fs.readFileSync( path.join( __dirname, '/specs/' + ( isBaseFile ? baseFile : file ) ), 'utf8' )
		)
	)
);

/**
 * Gets the array of metrics.
 *
 * @param {Object[]} files
 * @param {boolean} isBaseFile True if file is base file, otherwise false. Default false.
 * @return {Object[]} Metrics.
 */
const formatResults = ( files, isBaseFile = false ) => {

	const parsedResults = parseResults( files, isBaseFile );

	return files.reduce(
		( result, { metricsPrefix }, index ) => {
			return {
				...result,
				...Object.fromEntries(
					Object.entries(
						parsedResults[ index ] ?? {}
					).map( ( [ key, value ] ) => [
						metricsPrefix + key,
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
		metrics: formatResults( resultsFiles ),
		baseMetrics: formatResults( resultsFiles, true ),
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
