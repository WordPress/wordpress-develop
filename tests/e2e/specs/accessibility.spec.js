import { test, expect } from '@wordpress/e2e-test-utils-playwright';
const AxeBuilder = require( '@axe-core/playwright' ).default;

test.describe( 'Page Accessibility Tests', () => {
	test( 'should not have any automatically detectable accessibility violations', async ( { admin, page } ) => {

	// The page to be scanned.
	await admin.visitAdminPage( '/upload.php?mode=grid' );

	const scanResults = await new AxeBuilder( { page } )
		.options(
			{
				runOnly: [ 'wcag2a', 'wcag2aa' ],
				rules: {
					// This is only to test how to disable a rule.
					'aria-allowed-role': { enabled: false },
				}
			}
		)
		.analyze();

	const violationsAmount = scanResults.violations.length;

	if ( violationsAmount > 0 ) {
		console.log(`\nFound ${ violationsAmount } accessibility violation(s):\n`);

		/*
		 * Result Object documentation: https://github.com/dequelabs/axe-core/blob/master/doc/API.md#results-object
		 * This object has four components:
		 *   - a `passes` array:        keeps track of all the passed tests,
		 *                              along with detailed information on each one.
		 *   - a `violations` array:    keeps track of all the failed tests,
		 *                              along with detailed information on each one.
		 *   - an `incomplete` array:   indicates which nodes could neither be
		 *                              determined to definitively pass or definitively
		 *                              fail. They are separated out in order that
		 *                              a user interface can display these to the
		 *                              user for manual review
		 *   - an `inapplicable` array: lists all the rules for which no matching
		 *                              elements were found on the page.
		 */
		scanResults.violations.forEach( ( violation, index ) => {
			console.log( `--- Violation #${ index + 1 } ---` );
			console.log( `Rule ID:   ${ violation.id }` );
			console.log( `Impact:    ${ violation.impact.toUpperCase() }` );
			console.log( `Failure:   ${ violation.description }` );
			console.log( `Help:      ${ violation.help }` );
			console.log( `Help link: ${ violation.helpUrl }` );

			// List every specific HTML element failing a failing rule.
			console.log( 'Failing elements:' );
			violation.nodes.forEach( ( node ) => {
				console.log( `  - Target Selector: ${ node.target.join( ', ' ) }` );
				console.log( `  - HTML Snippet:\n${ node.html }` );
			} );
			console.log('\n');
		} );
	}

	// Assert that there are no violations.
	expect( violationsAmount ).toEqual( 0 );
  } );
} );
