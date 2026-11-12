#!/usr/bin/env node

/*
 * Compares the performance results of this change with the ones of its target
 * and reports every metric that got worse by more than its budget allows.
 *
 * Usage: node check-regressions.js [summary-file]
 *
 * Environment variables:
 * - PERFORMANCE_REGRESSION_MODE: `fail` exits with code 1 when a metric with the
 *   `error` severity regressed. Any other value only reports. Defaults to `report`.
 * - PERFORMANCE_REGRESSION_ACCEPTED: `true` reports regressions without failing,
 *   for changes whose cost has been reviewed and accepted.
 */

/**
 * External dependencies.
 */
const { writeFileSync } = require( 'node:fs' );

/**
 * Internal dependencies
 */
const {
	median,
	medianAbsoluteDeviation,
	accumulateValues,
	formatAsMarkdownTable,
	formatValue,
	parseFile,
} = require( './utils' );

const summaryFile = process.argv[ 2 ];
const failOnError = process.env.PERFORMANCE_REGRESSION_MODE === 'fail';
const regressionAccepted =
	process.env.PERFORMANCE_REGRESSION_ACCEPTED === 'true';

/**
 * Asset counts and sizes, database queries and memory are deterministic for a
 * given build, so a regression there is real and fails the check.
 */
const deterministic = { minAbsolute: 2, minRelative: 0.05, severity: 'error' };

/**
 * Timings carry CI noise, so they must also grow by more than `noiseFactor`
 * times the combined spread of both samples, and are reported as warnings.
 */
const timing = {
	minAbsolute: 10,
	minRelative: 0.1,
	noiseFactor: 3,
	severity: 'warning',
};

/**
 * A metric regresses when its median grows by more than `minAbsolute` and by
 * more than `minRelative` of the target's median.
 *
 * @type {Record<string, {minAbsolute: number, minRelative: number, noiseFactor?: number, severity: 'error'|'warning'}>}
 */
const budgets = {
	scriptCount: deterministic,
	scriptBytes: {
		...deterministic,
		minAbsolute: 20 * 1024,
		minRelative: 0.02,
	},
	stylesheetCount: deterministic,
	stylesheetBytes: {
		...deterministic,
		minAbsolute: 10 * 1024,
		minRelative: 0.02,
	},
	wpDbQueries: deterministic,
	wpMemoryUsage: { ...deterministic, minAbsolute: 1024 * 1024 },
	domContentLoaded: timing,
	largestContentfulPaint: timing,
	lcpMinusTtfb: timing,
	timeToFirstByte: { ...timing, minAbsolute: 5 },
	wpTotal: { ...timing, minAbsolute: 5 },
};

/**
 * Scales the median absolute deviation to estimate the standard deviation of
 * normally distributed values without being skewed by outliers.
 */
const MAD_TO_STANDARD_DEVIATION = 1.4826;

/**
 * Returns the comparison of one metric against its budget.
 *
 * @param {string}   metric Metric name.
 * @param {number[]} before Values of the target.
 * @param {number[]} after  Values of this change.
 * @return {{metric: string, before: number, after: number, delta: number, relative: number, regressed: boolean, severity: string}} Comparison.
 */
function compareMetricToBudget( metric, before, after ) {
	const budget = budgets[ metric ];
	const beforeMedian = median( before );
	const afterMedian = median( after );
	const delta = afterMedian - beforeMedian;
	const relative =
		beforeMedian > 0 ? delta / beforeMedian : Number.POSITIVE_INFINITY;
	const noise = budget.noiseFactor
		? MAD_TO_STANDARD_DEVIATION *
		  Math.hypot(
				medianAbsoluteDeviation( before ),
				medianAbsoluteDeviation( after )
		  )
		: 0;

	const regressed =
		delta > budget.minAbsolute &&
		relative > budget.minRelative &&
		delta > ( budget.noiseFactor ?? 0 ) * noise;

	return {
		metric,
		before: beforeMedian,
		after: afterMedian,
		delta,
		relative,
		regressed,
		severity: budget.severity,
	};
}

/**
 * Returns the regressions of every scenario measured on both sides.
 *
 * @param {Array<{title: string, results: Record<string, number[]>[]}>} beforeStats Results of the target.
 * @param {Array<{title: string, results: Record<string, number[]>[]}>} afterStats  Results of this change.
 * @return {Array<{title: string} & ReturnType<typeof compareMetricToBudget>>} Regressions.
 */
function findRegressions( beforeStats, afterStats ) {
	return afterStats.flatMap( ( { title, results } ) => {
		const beforeStat = beforeStats.find( ( stat ) => stat.title === title );
		if ( ! beforeStat ) {
			return [];
		}

		const afterValues = accumulateValues( results );
		const beforeValues = accumulateValues( beforeStat.results );

		return Object.keys( budgets )
			.filter(
				( metric ) =>
					afterValues[ metric ]?.length &&
					beforeValues[ metric ]?.length
			)
			.map( ( metric ) =>
				compareMetricToBudget(
					metric,
					beforeValues[ metric ],
					afterValues[ metric ]
				)
			)
			.filter( ( comparison ) => comparison.regressed )
			.map( ( comparison ) => ( { title, ...comparison } ) );
	} );
}

/**
 * Formats how much a regressed metric grew, in its unit and in percent.
 *
 * @param {ReturnType<typeof compareMetricToBudget>} regression Regression.
 * @return {string} Formatted growth, for example `+12 ms (+25.0 %)`.
 */
function formatGrowth( regression ) {
	const absolute = `+${ formatValue( regression.metric, regression.delta ) }`;

	return Number.isFinite( regression.relative )
		? `${ absolute } (+${ ( regression.relative * 100 ).toFixed( 1 ) } %)`
		: absolute;
}

/**
 * Checks this change against one reference and reports its regressions.
 *
 * A missing reference is only reported when it is enforced, because the
 * workflow measures the target commit on pull requests and the last release
 * on trunk, never both.
 *
 * @param {Object}  reference
 * @param {string}  reference.file        Results file of the reference.
 * @param {string}  reference.heading     Heading of the report section.
 * @param {string}  reference.missingNote Note shown when an enforced reference has no results.
 * @param {boolean} reference.enforced    Whether error regressions may fail the check.
 * @param {Array<{title: string, results: Record<string, number[]>[]}>} afterStats Results of this change.
 * @return {{markdown: string, failed: boolean}} Report.
 */
function checkAgainstReference( reference, afterStats ) {
	const referenceStats = parseFile( reference.file );
	let markdown = `### ${ reference.heading }\n\n`;

	if ( ! referenceStats.length || ! afterStats.length ) {
		return {
			markdown: reference.enforced
				? `${ markdown }${ reference.missingNote }\n\n`
				: '',
			failed: false,
		};
	}

	const regressions = findRegressions( referenceStats, afterStats );
	const hasErrors = regressions.some(
		( regression ) => regression.severity === 'error'
	);
	const failed = reference.enforced && hasErrors;
	const errorRegressionsFailCheck =
		reference.enforced && ! regressionAccepted;

	for ( const regression of regressions ) {
		const level =
			errorRegressionsFailCheck && regression.severity === 'error'
				? 'error'
				: 'warning';
		console.log(
			`::${ level } title=Performance regression (${
				reference.heading
			})::${ regression.title }: ${
				regression.metric
			} went from ${ formatValue(
				regression.metric,
				regression.before
			) } to ${ formatValue(
				regression.metric,
				regression.after
			) }, ${ formatGrowth( regression ) }.`
		);
	}

	if ( ! regressions.length ) {
		markdown +=
			':white_check_mark: No metric got worse by more than its budget allows.\n\n';
	} else {
		markdown += `:${ hasErrors ? 'x' : 'warning' }: ${
			regressions.length
		} ${
			regressions.length === 1 ? 'metric' : 'metrics'
		} got worse by more than the budget allows.\n\n`;
		markdown += `${ formatAsMarkdownTable(
			regressions.map( ( regression ) => ( {
				Scenario: regression.title,
				Metric: regression.metric,
				Before: formatValue( regression.metric, regression.before ),
				After: formatValue( regression.metric, regression.after ),
				Change: formatGrowth( regression ),
				Severity: regression.severity,
			} ) )
		) }\n`;
	}

	if ( failed && regressionAccepted ) {
		markdown +=
			'This change is marked as an accepted performance regression, so the check does not fail.\n\n';
	}

	return { markdown, failed: failed && ! regressionAccepted };
}

const afterStats = parseFile( 'performance-results.json' );

const targetReport = checkAgainstReference(
	{
		file: 'before-performance-results.json',
		heading: 'Compared with the target commit',
		missingNote:
			'No build of the target commit was available, so this change could not be compared with it.',
		enforced: failOnError,
	},
	afterStats
);

const releaseReport = checkAgainstReference(
	{
		file: 'base-performance-results.json',
		heading: `Compared with the last release${
			process.env.BASE_TAG ? ` (${ process.env.BASE_TAG })` : ''
		}`,
		enforced: false,
	},
	afterStats
);

const summaryMarkdown =
	'## Performance Budget Check\n\n' +
	targetReport.markdown +
	releaseReport.markdown +
	'Asset counts and sizes, database queries and memory usage are deterministic for a build, so exceeding their budget fails pull requests. ' +
	'Timings are reported as warnings, because shared CI runners add noise.\n';

if ( summaryFile ) {
	writeFileSync( summaryFile, summaryMarkdown );
}

console.log( summaryMarkdown );

process.exitCode = targetReport.failed ? 1 : 0;
