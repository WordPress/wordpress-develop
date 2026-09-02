import { test, expect } from '@wordpress/e2e-test-utils-playwright';
const AxeScanner = require( '../utils/accessibility-axe-scanner' );
const { pages } = require( '../utils/accessibility-pages' );
const ignoresAndknownFalsePositives = require( '../utils/accessibility-ignores-and-false-positives' );

/**
 * Global accessibility scan rules.
 * Applied to all pages defined in utils/accessibility-pages.
 *
 * See: https://github.com/dequelabs/axe-core/blob/master/doc/API.md#options-parameter
 */
const globalRules = {
	runOnly: [ 'wcag2a', 'wcag2aa', 'best-practice' ],
};

/**
 * Filters violations to remove violations we want to intentionally ignore and known false positives.
 * Only returns violations for nodes that don't match excluded selectors.
 *
 * SELECTOR MATCHING: Uses substring matching. An exclusion pattern matches a violation's
 * target selector if the pattern appears as a substring. For example:
 *   - A violation reports a Target Selector: "th[aria-label="Privacy Policy"] > strong > .row-title".
 *   - Pattern ".row-title" MATCHES (substring found).
 *   - Pattern "strong > .row-title" MATCHES (substring found).
 *   - Pattern ".column-title .row-title" does NOT match (not a substring).
 *
 * @param {Object} results    Axe results object.
 * @param {Object} exclusions Known false positives config (rule ID → selectors[]).
 * @return {Object} Filtered results.
 */
function filterIgnoresAndFalsePositives( results, exclusions ) {
	results.violations = results.violations.map( ( violation ) => {
		const excludedSelectors = exclusions[ violation.id ] || [];

		if ( excludedSelectors.length === 0 ) {
			return violation;
		}

		// Filter out nodes matching excluded selectors.
		violation.nodes = violation.nodes.filter( ( node ) => {
			const targetSelector = node.target[ 0 ];
			return ! excludedSelectors.some( ( excluded ) =>
				targetSelector?.includes( excluded )
			);
		} );

		return violation;
	} ).filter( ( violation ) => violation.nodes.length > 0 );

	return results;
}

/**
 * Scans a page for accessibility violations and asserts no violations found.
 *
 * @param {Object} page      Playwright page object.
 * @param {Object} pageSpec  Page specification object.
 * @param {Object} [variant] Optional state variant spec.
 * @return {Promise<void>}
 */
async function scanAndAssert( page, pageSpec, variant = null ) {
	// Merge rules: global < page < variant.
	const mergedRules = {
		...globalRules,
		...( pageSpec.rules || {} ),
		...( variant?.rules || {} ),
	};

	// Scan and assert.
	const scanner = new AxeScanner( { page, globalRules: mergedRules } );
	let results = await scanner.scan();

	// Filter out known ignores and false positives.
	results = filterIgnoresAndFalsePositives( results, ignoresAndknownFalsePositives );

	scanner.formatResults( results );

	// Use expect for cleaner error reporting at test location.
	expect( scanner.getViolationsCount( results ) ).toBe( 0 );
}

test.describe( 'Admin Pages Accessibility', () => {
	pages.forEach( ( pageSpec ) => {
		// Normalize: pages without stateVariants get a default variant.
		const variants = pageSpec.stateVariants || [ { name: 'default' } ];

		variants.forEach( ( variant ) => {
			// Generate test name: omit [variant.name] if default.
			const variantName = variant.name === 'default' ? '' : ` [${ variant.name }]`;
			const testName = `${ pageSpec.name }${ variantName } should not have violations`;

			test( testName, async ( { admin, page, requestUtils } ) => {
				// Navigate to page.
				await admin.visitAdminPage( pageSpec.path );

				// Wait for any async content to render. Some pages load content
				// dynamically after the DOM is ready. This is particularly important
				// for pages rendered via React components like the Settings > Connectors
				// page or the Fonts page. Without this wait, the scan may run before all
				// content is fully rendered.
				await page.waitForTimeout( 500 );

				// Run state setup if provided.
				if ( variant.setup ) {
					await variant.setup( page, requestUtils );
				}

				// Scan and assert.
				await scanAndAssert( page, pageSpec, variant );
			} );
		} );
	} );
} );
