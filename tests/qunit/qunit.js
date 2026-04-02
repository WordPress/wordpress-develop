const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const glob = require( 'fast-glob' );

const qunitDir = path.resolve( __dirname );
const htmlFiles = glob.sync( [ '**/*.html' ], {
	cwd: qunitDir,
	absolute: true,
} );

for ( const file of htmlFiles ) {
	const name = path.relative( qunitDir, file );

	test( `QUnit: ${ name }`, async ( { page } ) => {
		// Inject a QUnit.done hook before any page scripts run.
		await page.addInitScript( () => {
			window.__qunitResults = new Promise( ( resolve ) => {
				window.__qunitResolve = resolve;
			} );

			// Keep checking for QUnit to become available.
			const observer = new MutationObserver( () => {
				if ( typeof QUnit !== 'undefined' && ! window.__qunitHooked ) {
					window.__qunitHooked = true;
					observer.disconnect();

					const failures = [];
					QUnit.testDone( ( details ) => {
						if ( details.failed > 0 ) {
							failures.push(
								`${ details.module } > ${ details.name } (${ details.failed } assertion(s))`
							);
						}
					} );

					QUnit.done( ( details ) => {
						window.__qunitResolve( {
							passed: details.passed,
							failed: details.failed,
							total: details.total,
							runtime: details.runtime,
							failures,
						} );
					} );
				}
			} );
			observer.observe( document, {
				childList: true,
				subtree: true,
			} );
		} );

		// Navigate to the test file.
		await page.goto( 'file://' + file, { waitUntil: 'domcontentloaded' } );

		// Wait for QUnit to complete.
		const results = await page.evaluate( () => window.__qunitResults );

		// Log summary.
		// eslint-disable-next-line no-console
		console.log(
			`  ${ results.passed }/${ results.total } passed, ${ results.failed } failed, ${ results.runtime }ms`
		);

		if ( results.failures.length > 0 ) {
			// eslint-disable-next-line no-console
			console.log(
				results.failures.map( ( f ) => `    FAIL: ${ f }` ).join( '\n' )
			);
		}

		expect( results.failed ).toBe( 0 );
	} );
}
