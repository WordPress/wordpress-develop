#!/usr/bin/env node

/**
 * External dependencies.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { median } = require( './utils' );

/**
 * Parse test files into JSON objects.
 *
 * @param {string} fileName The name of the file.
 * @returns An array of parsed objects from each file.
 */
const parseFile = ( fileName ) =>
	JSON.parse(
		fs.readFileSync( path.join( __dirname, '/specs/', fileName ), 'utf8' )
	);

// The list of test suites to log.
const testSuites = [ 'home-block-theme', 'home-classic-theme' ];

// // The current commit's results.
const testResults = Object.fromEntries(
	testSuites.map( ( key ) => [
		key,
		parseFile( `${ key }.test.results.json` ),
	] )
);

// The previous commit's results
const prevResults = Object.fromEntries(
	testSuites.map( ( key ) => [
		key,
		parseFile( `before-${ key }.test.results.json` ),
	] )
);

const args = process.argv.slice( 2 );

const summaryFile = args[ 1 ];

function formatAsMarkdownTable( rows ) {
	let result = '';
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

let summaryMarkdown = `**Performance Test Results**\n\n`;

if ( process.env.GITHUB_SHA ) {
	summaryMarkdown += `Performance test results for ${ process.env.GITHUB_SHA } are in 🛎️!\n\n`;
} else {
	summaryMarkdown += `Performance test results are in 🛎️!\n\n`;
}

console.log( 'Performance Test Results\n' );

for ( const key of testSuites ) {
	const current = testResults[ key ];
	const prev = prevResults[ key ];

	const title = ( key.charAt( 0 ).toUpperCase() + key.slice( 1 ) ).replace(
		/-+/g,
		' '
	);

	const rows = [];

	for ( const [ metric, values ] of Object.entries( current ) ) {
		const value = median( values );
		const prevValue = median( prev[ metric ] );

		const delta = value - prevValue;
		const percentage = Math.round( ( delta / value ) * 100 );
		rows.push( {
			Metric: metric,
			Before: `${ prevValue } ms`,
			After: `${ value } ms`,
			'Diff abs.': `${ delta.toFixed( 2 ) } ms`,
			'Diff %': `${ percentage.toFixed( 2 ) } %`,
		} );
	}

	summaryMarkdown += `**${ title }**\n\n`;
	summaryMarkdown += `${ formatAsMarkdownTable( rows ) }\n`;

	console.log( title );
	console.table( rows );
}

fs.writeFileSync(
	summaryFile,
	summaryMarkdown
);
