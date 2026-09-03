/**
 * Explicit exclusions and known false positives after human inspection.
 *
 * Maps rule IDs to CSS selectors that should be excluded from the reported violations.
 * These are instances where axe-core flags a violation, but after human review,
 * the pattern is acceptable or represents a known limitation of the automated check.
 *
 * SELECTOR MATCHING:
 * Uses space-separated selector tokens. An exclusion pattern matches a violation's
 * target selector if all selector tokens appear in order (but not necessarily
 * consecutively). Intermediate selectors can be skipped.
 * For example:
 *   - Violation Target Selector: "html > body > #__wp-uploader > .attachments-browser > .attachments-wrapper > li[aria-label="image-1"]".
 *   - Pattern ".attachments-browser li" MATCHES (skips .attachments-wrapper; all tokens found in order).
 *   - Pattern ".attachments-browser .attachments-wrapper" MATCHES (all tokens found).
 *   - Pattern ".attachments-browserxyz" does NOT match (selector token boundary prevents partial matches).
 *   - Attributes on target selectors are ignored: "li[aria-label="..."]" matches pattern "li".
 *
 * WILDCARD SUPPORT:
 * Use an asterisk * to match any characters in a selector token. For example:
 *   - Pattern "#wp-admin-bar-*" matches "#wp-admin-bar-site-name", "#wp-admin-bar-updates", etc.
 *   - Pattern "#wpadminbar #wp-toolbar #wp-admin-bar-*" matches full descendant chains with variable final selectors.
 *
 * CONSTRAINTS:
 * Exclusion patterns must be space-separated tokens only (e.g., '#id .class li').
 * Do not use attribute selectors, pseudo-classes, or explicit combinators (>, +, ~) in patterns.
 * Spaces implicitly represent descendant relationships (any number of intermediate selectors).
 *
 * NOTE:
 * The passed pattern isn't meant to be a meaningful CSS selector. It's only used
 * to match the Axe-core target selector of a violation. Just use a space-separated
 * list of tokens that appear in the 'Target Selector' entry of the reported violation.
 */

module.exports = {
	'link-in-text-block': [
		// Post title links in list tables may have surrounding text and are only distinguished by color.
		// We consider this acceptable as these links can be distinguished by the context.
		'.wp-list-table strong .row-title',
	],
	'aria-allowed-role': [
		// The "group" role for the admin bar list items is flagged as invalid by axe-core.
		// This is a known false positive in axe-core and can be ignored. Note
		// that the 'aria-allowed-role' rule is part of the 'best-practice' rules group.
		'#wp-toolbar #wp-admin-bar-*',
	],
	'region': [
		// Visually hidden text that clarifies what the ARIA live regions are about.
		'#a11y-speak-intro-text',
	],
};
