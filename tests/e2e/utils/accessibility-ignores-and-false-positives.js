/**
 * Ignores and known false positives after human inspection.
 *
 * Maps rule IDs to an array of CSS selectors that should be excluded from violations.
 * These are instances where axe-core flags a violation, but after human review,
 * the pattern is acceptable or represents a known limitation of the automated check.
 *
 * SELECTOR MATCHING: Uses substring matching. An exclusion pattern matches a violation's
 * target selector if the pattern appears as a substring. For example:
 *   - A violation reports a Target Selector: "th[aria-label="Privacy Policy"] > strong > .row-title".
 *   - Pattern ".row-title" MATCHES (substring found).
 *   - Pattern "strong > .row-title" MATCHES (substring found).
 *   - Pattern ".column-title .row-title" does NOT match (not a substring).
 */

module.exports = {
	'link-in-text-block': [
		// Post title links in list tables may have surrounding text and are only distinguished by color.
		// We consider this acceptable as these links can be distinguished by the context.
		'strong > .row-title',
	],
};
