#!/usr/bin/env node

/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const https = require( 'https' );
const [ token, branch, hash, timestamp, host ] = process.argv.slice( 2 );
const { median } = require( './utils' );

const resultsFiles = [
	{
		file: 'home-block-theme.test.results.json',
	},
	{
		file: 'home-classic-theme.test.results.json',
	},
];

const performanceResults = resultsFiles.map( ( { file } ) =>
	JSON.parse(
		fs.readFileSync( path.join( __dirname, '/specs/' + file ), 'utf8' )
	)
);

/**
 * Gets the array or metrics.
 *
 * @return {array} Metrics.
 */
function getMedianMetrics() {
    const rawResults = [];

    for (var keys in performanceResults) {
        const rawKeys = [];
        for (var key in performanceResults[keys]) {
            rawKeys[key] = median( performanceResults[keys][key] );
        }
        rawResults.push( rawKeys );
    }

    return rawResults;
}
const getMedianMetricsResult = getMedianMetrics();

const data = new TextEncoder().encode(
	JSON.stringify( {
		branch,
		hash,
		baseHash: '',
		timestamp: parseInt( timestamp, 10 ),
		metrics: getMedianMetricsResult,
		baseMetrics: '',
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
