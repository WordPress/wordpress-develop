import { test, expect } from '@wordpress/e2e-test-utils-playwright';
const AxeScanner = require( '../utils/accessibility-axe-scanner' );
const { pages } = require( '../utils/accessibility-pages' );
const ignoresAndknownFalsePositives = require( '../utils/accessibility-ignores-and-false-positives' );

/**
 * Global accessibility scan rules.
 * Applied to all pages defined in utils/accessibility-pages.
 *
 * See the Axe Options parameter documentation.
 * See: https://github.com/dequelabs/axe-core/blob/master/doc/API.md#options-parameter
 */
const globalRules = {
	runOnly: [ 'wcag2a', 'wcag2aa', 'best-practice' ],
	absolutePaths: true, // Report the absolute CSS target selector for better exclusions mechanism.
};

/**
 * Filters violations to remove violations we want to intentionally ignore and known false positives.
 * Only returns violations for nodes that don't match excluded selectors.
 *
 * See more details in tests/e2e/utils/accessibility-ignores-and-false-positives.js.
 *
 * @param {Object} results    Axe results object.
 * @param {Object} exclusions Explicit exclusions and known false positives config (rule ID → selectors[]).
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

			/*
		 * Normalize the received Axe-core Target Selector: remove combinators,
		 * attributes, and collapse spaces. This allows us to match patterns
		 * against the normalized selector using token-based matching. For example:
		 * - Raw Target Selector: "html > body > #__wp-uploader > .attachments-browser > .attachments-wrapper > li[aria-label="image-1"]"
		 * - After normalization: "html body #__wp-uploader .attachments-browser .attachments-wrapper li"
		 *
		 * Then it checks if all pattern tokens appear in order in the normalized selector.
		 * Intermediate selectors can be skipped.
		 */
		const normalizedTargetSelector = targetSelector
			.replace( /[>+~]/g, ' ' )        // Replace combinators with spaces.
			.replace( /\[[^\]]*\]/g, ' ' )   // Remove attributes (anything in brackets).
			.replace( /\s+/g, ' ' )          // Collapse multiple spaces.
			.trim();

		// Check if target selector matches any excluded selector pattern.
		const isExcluded = excludedSelectors.some( ( excluded ) => {
				const selectors = excluded.trim().split( ' ' ).filter( s => s.length > 0 );
				return selectors.every( selector => {
					// Escape regex special chars (periods, brackets, etc.) to match literals.
					const escaped = selector.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
					// Replace escaped asterisks with .* to enable wildcard matching.
					const wildcard = escaped.replace( /\\\*/g, '.*' );
					// Regex checks for token at start/end or bounded by spaces.
					const pattern = `(?:^|\\s)${ wildcard }(?=\\s|$)`;
					return new RegExp( pattern ).test( normalizedTargetSelector );
				} );
			} );

			return ! isExcluded;
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
	let mediaAttachmentId = null;

	test.beforeAll( async ( { requestUtils } ) => {
		// Upload sample image to media library for testing.
		const fs = require( 'fs' );
		const path = require( 'path' );

		const imagePath = path.join( __dirname, '../assets/sample.png' );
		const imageBuffer = fs.readFileSync( imagePath );

		const response = await requestUtils.rest( {
			method: 'POST',
			path: 'wp/v2/media',
			data: imageBuffer,
			headers: {
				'Content-Disposition': 'attachment; filename="sample.png"',
				'Content-Type': 'image/png',
			},
		} );

		// Store the ID for cleanup later.
		mediaAttachmentId = response.id;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Delete the uploaded image to restore initial state
		if ( mediaAttachmentId ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `wp/v2/media/${ mediaAttachmentId }?force=true`,
			} );
		}
	} );

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
