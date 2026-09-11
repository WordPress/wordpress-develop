/**
 * Runs Stylelint on core CSS and checks warning-level rule thresholds using a single
 * Stylelint run, so linting the codebase doesn't happen twice.
 */

const {
	lintCss,
	getWarningLevelRules,
	countWarnings,
	checkThresholds,
} = require( './lib/warning-thresholds' );

async function main() {
	const warningLevelRules = getWarningLevelRules();

	const { results, errored, report } = await lintCss( {
		formatter: 'string',
	} );

	if ( report ) {
		console.log( report );
	}

	const actualCounts = countWarnings( results, warningLevelRules );
	const thresholdsOk = checkThresholds( actualCounts, warningLevelRules );

	process.exitCode = errored || ! thresholdsOk ? 1 : 0;
}

main().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
