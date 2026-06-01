const { test, expect } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );
const { pathToFileURL } = require( 'url' );

function getHtmlFiles( dir ) {
	const entries = fs.readdirSync( dir, { withFileTypes: true } );

	return entries.flatMap( ( entry ) => {
		const entryPath = path.join( dir, entry.name );

		if ( entry.isDirectory() ) {
			return getHtmlFiles( entryPath );
		}

		return entry.isFile() && entry.name.endsWith( '.html' ) ? [ entryPath ] : [];
	} );
}

const qunitDir = path.resolve( __dirname );
const htmlFiles = getHtmlFiles( qunitDir );

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
		await page.goto( pathToFileURL( file ).href, { waitUntil: 'domcontentloaded' } );

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
