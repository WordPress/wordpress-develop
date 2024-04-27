#!/usr/bin/env node

/**
 * External dependencies.
 */
const { readFileSync, writeFileSync, existsSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

/**
 * Internal dependencies
 */
const {
	median,
	formatAsMarkdownTable,
	formatValue,
	linkToSha,
} = require( './utils' );

process.env.WP_ARTIFACTS_PATH ??= join( process.cwd(), 'artifacts' );

const args = process.argv.slice( 2 );
const summaryFile = args[ 0 ];

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
 * @type {Array<{file: string, title: string, results: Record<string,number[]>[]}>}
 */
const beforeStats = parseFile( 'before-performance-results.json' );

/**
 * @type {Array<{file: string, title: string, results: Record<string,number[]>[]}>}
 */
const afterStats = parseFile( 'performance-results.json' );

let summaryMarkdown = `## Performance Test Results\n\n`;

if ( process.env.TARGET_SHA ) {
	if ( process.env.GITHUB_SHA ) {
		summaryMarkdown += `This compares the results from this commit (${ linkToSha(
			process.env.GITHUB_SHA
		) }) with the ones from ${ linkToSha( process.env.TARGET_SHA ) }.\n\n`;
	} else {
		summaryMarkdown += `This compares the results from this commit with the ones from ${ linkToSha(
			process.env.TARGET_SHA
		) }.\n\n`;
	}
}

if ( process.env.GITHUB_SHA ) {
	summaryMarkdown += `**Note:** Due to the nature of how GitHub Actions work, some variance in the results is expected.\n\n`;
}

console.log( 'Performance Test Results\n' );

console.log(
	'Note: Due to the nature of how GitHub Actions work, some variance in the results is expected.\n'
);

for ( const { title, results } of afterStats ) {
	const prevStat = beforeStats.find( ( s ) => s.title === title );

	/**
	 * @type {Array<Record<string, string>>}
	 */
	const rows = [];

	for ( const i in results ) {
		const newResult = results[ i ];

		for ( const [ metric, values ] of Object.entries( newResult ) ) {
			// Only do comparison if the number of results is the same.
			const prevValues =
				prevStat?.results.length === results.length
					? prevStat?.results[ i ].key
					: null;

			const value = median( values );
			const prevValue = prevValues ? median( prevValues ) : 0;
			const delta = value - prevValue;
			const percentage = ( delta / value ) * 100;

			rows.push( {
				Metric: metric,
				Before: formatValue( metric, prevValue ),
				After: formatValue( metric, value ),
				'Diff abs.': formatValue( metric, delta ),
				'Diff %': `${ percentage.toFixed( 2 ) } %`,
			} );
		}
	}

	console.log( title );
	console.table( rows );

	summaryMarkdown += `**${ title }**\n\n`;
	summaryMarkdown += `${ formatAsMarkdownTable( rows ) }\n`;
}

writeFileSync(
	join( process.env.WP_ARTIFACTS_PATH, '/performance-results.md' ),
	summaryMarkdown
);

if ( summaryFile ) {
	writeFileSync( summaryFile, summaryMarkdown );
}
