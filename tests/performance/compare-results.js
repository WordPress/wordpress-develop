#!/usr/bin/env node

/**
 * External dependencies.
 */
const fs = require( 'node:fs' );
const path = require( 'node:path' );

/**
 * Internal dependencies
 */
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
const testSuites = [
	'admin',
	'admin-l10n',
	'home-block-theme',
	'home-block-theme-l10n',
	'home-classic-theme',
	'home-classic-theme-l10n',
];

// The current commit's results.
const testResults = Object.fromEntries(
	testSuites
		.filter( ( key ) => fs.existsSync( path.join( __dirname, '/specs/', `${ key }.test.results.json` ) ) )
		.map( ( key ) => [ key, parseFile( `${ key }.test.results.json` ) ] )
);

// The previous commit's results.
const prevResults = Object.fromEntries(
	testSuites
		.filter( ( key ) => fs.existsSync( path.join( __dirname, '/specs/', `before-${ key }.test.results.json` ) ) )
		.map( ( key ) => [ key, parseFile( `before-${ key }.test.results.json` ) ] )
);

const args = process.argv.slice( 2 );

const summaryFile = args[ 0 ];

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
 * Returns a Markdown link to a Git commit on the current GitHub repository.
 *
 * For example, turns `a5c3785ed8d6a35868bc169f07e40e889087fd2e`
 * into (https://github.com/wordpress/wordpress-develop/commit/36fe58a8c64dcc83fc21bddd5fcf054aef4efb27)[36fe58a].
 *
 * @param {string} sha Commit SHA.
 * @return string Link
 */
function linkToSha(sha) {
	const repoName = process.env.GITHUB_REPOSITORY || 'wordpress/wordpress-develop';

	return `[${sha.slice(0, 7)}](https://github.com/${repoName}/commit/${sha})`;
}

let summaryMarkdown = `# Performance Test Results\n\n`;

if ( process.env.GITHUB_SHA ) {
	summaryMarkdown += `🛎️ Performance test results for ${ linkToSha( process.env.GITHUB_SHA ) } are in!\n\n`;
} else {
	summaryMarkdown += `🛎️ Performance test results are in!\n\n`;
}

if ( process.env.TARGET_SHA ) {
	summaryMarkdown += `This compares the results from this commit with the ones from ${ linkToSha( process.env.TARGET_SHA ) }.\n\n`;
}

if ( process.env.GITHUB_SHA ) {
	summaryMarkdown += `**Note:** Due to the nature of how GitHub Actions work, some variance in the results is expected.\n\n`;
}

console.log( 'Performance Test Results\n' );

console.log( 'Note: Due to the nature of how GitHub Actions work, some variance in the results is expected.\n' );

/**
 * Nicely formats a given value.
 *
 * @param {string} metric Metric.
 * @param {number} value
 */
function formatValue( metric, value) {
	if ( null === value ) {
		return 'N/A';
	}
	if ( 'wpMemoryUsage' === metric ) {
		return `${ ( value / Math.pow( 10, 6 ) ).toFixed( 2 ) } MB`;
	}

	return `${ value.toFixed( 2 ) } ms`;
}

for ( const key of testSuites ) {
	const current = testResults[ key ] || {};
	const prev = prevResults[ key ] || {};

	const title = ( key.charAt( 0 ).toUpperCase() + key.slice( 1 ) ).replace(
		/-+/g,
		' '
	);

	const rows = [];

	for ( const [ metric, values ] of Object.entries( current ) ) {
		const value = median( values );
		const prevValue = prev[ metric ] ? median( prev[ metric ] ) : null;

		const delta = null !== prevValue ? value - prevValue : 0
		const percentage = ( delta / value ) * 100;
		rows.push( {
			Metric: metric,
			Before: formatValue( metric, prevValue ),
			After: formatValue( metric, value ),
			'Diff abs.': formatValue( metric, delta ),
			'Diff %': `${ percentage.toFixed( 2 ) } %`,
		} );
	}

	if ( rows.length > 0 ) {
		summaryMarkdown += `## ${ title }\n\n`;
		summaryMarkdown += `${ formatAsMarkdownTable( rows ) }\n`;

		console.log( title );
		console.table( rows );
	}
}

if ( summaryFile ) {
	fs.writeFileSync(
		summaryFile,
		summaryMarkdown
	);
}
