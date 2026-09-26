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

	// Stylelint leaves `errored` set on results whose problems were all suppressed, so count what remains.
	const hasErrors = results.some( ( result ) =>
		result.parseErrors.length > 0 ||
		result.warnings.some( ( warning ) => warning.severity === 'error' )
	);

	process.exitCode = hasErrors ? 1 : 0;
}

main().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
