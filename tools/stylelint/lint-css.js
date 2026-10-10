/**
 * Runs Stylelint on core CSS. Problems recorded in stylelint-suppressions.json are not reported.
 */

async function main() {
	const { default: stylelint } = await import( 'stylelint' );

	const { results, report } = await stylelint.lint( {
		files: 'src/**/*.{css,scss}',
		formatter: 'string',
	} );

	if ( report ) {
		console.log( report );
	}

	// Don't rely on `errored`: Stylelint leaves it set on results whose problems were all suppressed. Count what remains.
	const hasViolations = results.some( ( result ) =>
		result.warnings.some( ( warning ) => warning.severity === 'error' )
	);

	const hasErrors = hasViolations || results.some( ( result ) =>
		result.parseErrors.length > 0 ||
		result.invalidOptionWarnings.length > 0
	);

	if ( hasViolations ) {
		console.log(
			[
				'Stylelint found new CSS Coding Standards violations.',
				'',
				'Existing violations are allowed up to the count recorded for each file and rule in stylelint-suppressions.json.',
				'Note that if a file has new violations, Stylelint lists all of them, including the existing ones.',
				'',
				'Fix the problems you introduced. Do not increase the counts in stylelint-suppressions.json.',
				'Once you have fixed the new violations, update the suppressions file by using: npm run lint:css:update-suppressions',
			].join( '\n' )
		);
	}

	process.exitCode = hasErrors ? 1 : 0;
}

main().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
