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
	standardDeviation,
	medianAbsoluteDeviation,
	accumulateValues,
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
	if ( beforeStats.length > 0 ) {
		if (process.env.GITHUB_SHA) {
			summaryMarkdown += `This compares the results from this commit (${linkToSha(
				process.env.GITHUB_SHA
			)}) with the ones from ${linkToSha(process.env.TARGET_SHA)}.\n\n`;
		} else {
			summaryMarkdown += `This compares the results from this commit with the ones from ${linkToSha(
				process.env.TARGET_SHA
			)}.\n\n`;
		}
	} else {
		summaryMarkdown += `Note: no build was found for the target commit ${linkToSha(process.env.TARGET_SHA)}. No comparison is possible.\n\n`;
	}
}

const numberOfRepetitions = afterStats[ 0 ].results.length;
const numberOfIterations = Object.values( afterStats[ 0 ].results[ 0 ] )[ 0 ]
	.length;

const repetitions = `${ numberOfRepetitions } ${
	numberOfRepetitions === 1 ? 'repetition' : 'repetitions'
}`;
const iterations = `${ numberOfIterations } ${
	numberOfIterations === 1 ? 'iteration' : 'iterations'
}`;

summaryMarkdown += `All numbers are median values over ${ repetitions } with ${ iterations } each.\n\n`;

if ( process.env.GITHUB_SHA ) {
	summaryMarkdown += `**Note:** Due to the nature of how GitHub Actions work, some variance in the results is expected.\n\n`;
}

console.log( 'Performance Test Results\n' );

console.log(
	`All numbers are median values over ${ repetitions } with ${ iterations } each.\n`
);

if ( process.env.GITHUB_SHA ) {
	console.log(
		'Note: Due to the nature of how GitHub Actions work, some variance in the results is expected.\n'
	);
}

for ( const { title, results } of afterStats ) {
	const prevStat = beforeStats.find( ( s ) => s.title === title );

	/**
	 * @type {Array<Record<string, string>>}
	 */
	const rows = [];

	const newResults = accumulateValues( results );
	// Only do comparison if the number of results is the same.
	const prevResults =
		prevStat && prevStat.results.length === results.length
			? accumulateValues( prevStat.results )
			: {};

	for ( const [ metric, values ] of Object.entries( newResults ) ) {
		const prevValues = prevResults[ metric ] ? prevResults[ metric ] : null;

		const value = median( values );
		const prevValue = prevValues ? median( prevValues ) : 0;
		const delta = value - prevValue;
		const percentage = ( delta / value ) * 100;
		const showDiff =
			metric !== 'wpExtObjCache' && ! Number.isNaN( percentage );

		rows.push( {
			Metric: metric,
			Before: prevValues ? formatValue( metric, prevValue ) : 'N/A',
			After: formatValue( metric, value ),
			'Diff abs.': showDiff ? formatValue( metric, delta ) : '',
			'Diff %': showDiff ? `${ percentage.toFixed( 2 ) } %` : '',
			STD: showDiff
				? formatValue( metric, standardDeviation( values ) )
				: '',
			MAD: showDiff
				? formatValue( metric, medianAbsoluteDeviation( values ) )
				: '',
		} );
	}

	console.log( title );
	if ( rows.length > 0 ) {
		console.table( rows );
	} else {
		console.log( '(no results)' );
	}

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
