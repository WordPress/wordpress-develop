/**
 * CLI entry point for checking or updating Stylelint warning-level rule thresholds.
 * See tools/stylelint/lib/warning-thresholds.js for the underlying logic.
 */

const {
	lintCss,
	getWarningLevelRules,
	countWarnings,
	checkThresholds,
	updateThresholds,
} = require( './lib/warning-thresholds' );

const shouldUpdate = process.argv.includes( '--update' );

async function main() {
	console.log( 'Checking Stylelint warnings thresholds...' );

	const warningLevelRules = getWarningLevelRules();
	const { results } = await lintCss();
	const actualCounts = countWarnings( results, warningLevelRules );

	const success = shouldUpdate
		? updateThresholds( actualCounts )
		: checkThresholds( actualCounts, warningLevelRules );

	if ( ! success ) {
		process.exitCode = 1;
	}
}

main().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
