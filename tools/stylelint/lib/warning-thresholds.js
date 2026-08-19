/**
 * Shared logic for enforcing thresholds on Stylelint rules configured with
 * `severity: 'warning'` in .stylelintrc.js, so that warning-level violations never
 * silently increase over time.
 *
 * The number of violations allowed for each warning-level rule is recorded in
 * warning-thresholds.json. The actual number of violations in the codebase is compared
 * against the recorded threshold:
 *
 *   - actual > threshold: new violations were introduced. Fail the build.
 *   - actual < threshold: violations were fixed, but the threshold file was not updated
 *     to lock in the improvement. Fail the build and ask the developer to update it.
 *   - actual === threshold: nothing to do.
 *
 * A rule that is downgraded to a warning in .stylelintrc.js but has no corresponding
 * entry in warning-thresholds.json also fails the build, so new warning-level rules
 * must have an explicit, reviewed threshold before they can be merged.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '..', '..', '..' );
const THRESHOLDS_FILE = path.join( __dirname, '..', 'warning-thresholds.json' );
const CONFIG_FILE = path.join( ROOT, '.stylelintrc.js' );
const IGNORE_PATH = path.join( ROOT, '.stylelintignore' );
const FILES_GLOB = path.join( ROOT, 'src/**/*.{css,scss}' ).split( path.sep ).join( '/' );

/**
 * Lints core CSS and returns the raw Stylelint results.
 *
 * @param {Object} [options]           Options.
 * @param {string} [options.formatter] Stylelint formatter to use for the `report` output.
 *
 * @return {Promise<Object>} Stylelint lint results (`results`, `errored`, `report`).
 */
async function lintCss( options = {} ) {
	// Use the ESM entry point dynamically to avoid stylelint's CommonJS deprecation warning.
	const { default: stylelint } = await import( 'stylelint' );

	return stylelint.lint( {
		files: FILES_GLOB,
		configFile: CONFIG_FILE,
		ignorePath: IGNORE_PATH,
		formatter: options.formatter,
	} );
}

/**
 * Returns the set of rule names configured with `severity: 'warning'` in the Stylelint
 * config, so newly downgraded rules are automatically picked up without editing this file.
 *
 * @return {Set<string>} Rule names configured with severity 'warning'.
 */
function getWarningLevelRules() {
	const config = require( CONFIG_FILE );
	const rules = config.rules || {};
	const warningRules = new Set();

	for ( const [ ruleName, ruleConfig ] of Object.entries( rules ) ) {
		const options = Array.isArray( ruleConfig ) ? ruleConfig[ 1 ] : null;

		if (
			options &&
			typeof options === 'object' &&
			options.severity === 'warning'
		) {
			warningRules.add( ruleName );
		}
	}

	return warningRules;
}

/**
 * Tallies, per rule, how many warning-level violations are present in a set of
 * Stylelint lint results.
 *
 * @param {Object[]}    results           Stylelint lint results.
 * @param {Set<string>} warningLevelRules Rule names configured with severity 'warning'.
 *
 * @return {Object<string, number>} Violation count per rule.
 */
function countWarnings( results, warningLevelRules ) {
	const actualCounts = {};
	for ( const rule of warningLevelRules ) {
		actualCounts[ rule ] = 0;
	}

	for ( const result of results ) {
		for ( const warning of result.warnings ) {
			if (
				warning.severity === 'warning' &&
				warningLevelRules.has( warning.rule )
			) {
				actualCounts[ warning.rule ] += 1;
			}
		}
	}

	return actualCounts;
}

function readThresholds() {
	if ( ! fs.existsSync( THRESHOLDS_FILE ) ) {
		return {};
	}

	return JSON.parse( fs.readFileSync( THRESHOLDS_FILE, 'utf8' ) );
}

/**
 * Compares actual violation counts against the recorded thresholds and prints a report.
 * Returns `true` if everything is up to date, `false` if the build should fail.
 *
 * @param {Object<string, number>} actualCounts      Violation count per rule.
 * @param {Set<string>}            warningLevelRules Rule names configured with severity 'warning'.
 *
 * @return {boolean} Whether all thresholds are up to date.
 */
function checkThresholds( actualCounts, warningLevelRules ) {
	const thresholds = readThresholds();

	const missingEntries = [];
	const regressions = [];
	const improvements = [];

	for ( const rule of warningLevelRules ) {
		const actual = actualCounts[ rule ];

		if ( ! ( rule in thresholds ) ) {
			missingEntries.push( { rule, actual } );
			continue;
		}

		const threshold = thresholds[ rule ];

		if ( actual > threshold ) {
			regressions.push( { rule, actual, threshold } );
		} else if ( actual < threshold ) {
			improvements.push( { rule, actual, threshold } );
		}
	}

	if ( missingEntries.length ) {
		console.error(
			'The following Stylelint rules are configured as warnings but have no recorded threshold:\n'
		);
		for ( const { rule, actual } of missingEntries ) {
			console.error( `  - ${ rule } (current violations: ${ actual })` );
		}
		console.error(
			`\nAdd them to ${ path.relative(
				ROOT,
				THRESHOLDS_FILE
			) } by running:`
		);
		console.error( '  npm run lint:css:thresholds:update\n' );
	}

	if ( regressions.length ) {
		console.error(
			'The number of Stylelint warnings has increased beyond the allowed threshold:\n'
		);
		for ( const { rule, actual, threshold } of regressions ) {
			console.error(
				`  - ${ rule }: ${ actual } violations found, threshold is ${ threshold }`
			);
		}
		console.error(
			'\nFix the new violations introduced by this change. The threshold must never increase.\n'
		);
	}

	if ( improvements.length ) {
		console.error(
			'Great news! Some Stylelint warnings have been fixed:\n'
		);
		for ( const { rule, actual, threshold } of improvements ) {
			console.error(
				`  - ${ rule }: ${ actual } violations found, threshold is ${ threshold }`
			);
		}
		console.error(
			'\nPlease lock in this improvement by updating the threshold. Run the following command and commit the result:'
		);
		console.error( '  npm run lint:css:thresholds:update\n' );
	}

	if ( missingEntries.length || regressions.length || improvements.length ) {
		console.error(
			'This check also runs in CI on every pull request and will block merging until it is resolved.\n'
		);
		return false;
	}

	console.log( 'All Stylelint warning thresholds are up to date.' );
	return true;
}

/**
 * Rewrites the thresholds file to match the actual counts. Refuses to write a higher
 * threshold for any rule. Returns `true` on success, `false` if the update was refused.
 *
 * @param {Object<string, number>} actualCounts Violation count per rule.
 *
 * @return {boolean} Whether the update succeeded.
 */
function updateThresholds( actualCounts ) {
	const thresholds = readThresholds();
	const updated = {};
	const blocked = [];

	for ( const rule of Object.keys( actualCounts ).sort() ) {
		const actual = actualCounts[ rule ];
		const existing = thresholds[ rule ];

		// A threshold may only stay the same or decrease, never increase.
		if ( typeof existing === 'number' && actual > existing ) {
			blocked.push( { rule, actual, existing } );
			updated[ rule ] = existing;
			continue;
		}

		updated[ rule ] = actual;
	}

	if ( blocked.length ) {
		console.error(
			'Refusing to update the following thresholds because it would increase them:\n'
		);
		for ( const { rule, actual, existing } of blocked ) {
			console.error(
				`  - ${ rule }: ${ actual } violations found, current threshold is ${ existing }`
			);
		}
		console.error(
			'\nFix the new violations first. The threshold must never increase.\n'
		);
		return false;
	}

	fs.writeFileSync(
		THRESHOLDS_FILE,
		JSON.stringify( updated, null, '\t' ) + '\n'
	);

	console.log( `Updated ${ path.relative( ROOT, THRESHOLDS_FILE ) }:` );
	for ( const rule of Object.keys( updated ) ) {
		console.log( `  ${ rule }: ${ updated[ rule ] }` );
	}

	return true;
}

module.exports = {
	lintCss,
	getWarningLevelRules,
	countWarnings,
	checkThresholds,
	updateThresholds,
};
