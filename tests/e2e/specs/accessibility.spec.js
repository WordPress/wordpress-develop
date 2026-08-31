import { test, expect } from '@wordpress/e2e-test-utils-playwright';
const AxeBuilder = require( '@axe-core/playwright' ).default;

test.describe( 'PHP Page Accessibility Tests', () => {
	test( 'should not have any automatically detectable accessibility violations', async ( { admin, page, requestUtils } ) => {

	// await requestUtils.login();
	// await page.goto( 'http://localhost:8889/wp-admin/edit.php');
	await admin.visitAdminPage( '/edit.php' );

	// 2. Run the axe scan on the rendered page
	const scanResults = await new AxeBuilder({ page }).analyze();

	// 3. Format and print violations individually if any are found
	if (scanResults.violations.length > 0) {
		console.log(`\n❌ Found ${scanResults.violations.length} accessibility violation(s):\n`);

	scanResults.violations.forEach((violation, index) => {
		console.log(`--- Violation #${index + 1} ---`);
		console.log(`Rule ID:   ${violation.id}`);
		console.log(`Impact:    ${violation.impact.toUpperCase()}`);
		console.log(`Failure:   ${violation.description}`);
		console.log(`Help Link: ${violation.helpUrl}`);

		// List every specific HTML element failing this rule
		console.log('Failing Elements:');
		violation.nodes.forEach((node) => {
			console.log(`  - Target Selector:  ${node.target.join(', ')}`);
			console.log(`    HTML Snippet:     ${node.html}`);
		});
		console.log('\n');
		});
	}

	// 3. Assert that the violations list is empty
	expect(scanResults.violations).toEqual([]);
  });
});
