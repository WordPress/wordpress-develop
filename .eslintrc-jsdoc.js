/**
 * ESLint v10 flat config file for JSDoc linting.
 * This config is based on the original valid-jsdoc rules.
 */

const jsdocPlugin = require( 'eslint-plugin-jsdoc' );

module.exports = [
	{
		plugins: {
			jsdoc: jsdocPlugin,
		},
		rules: {
			// JSDoc validation - matching original valid-jsdoc behavior

			// Validate @param names match actual function parameters
			'jsdoc/check-param-names': 'error',

			// Type validation with exemptTagContexts to allow flexible type formats
			// This avoids enforcing type normalization (Object→object) preferences
			'jsdoc/check-types': [ 'error', {
				noDefaults: true,
				exemptTagContexts: [
					{ tag: 'param', types: true },
					{ tag: 'return', types: true },
					{ tag: 'returns', types: true },
					{ tag: 'type', types: true },
					{ tag: 'typedef', types: true },
					{ tag: 'property', types: true },
					{ tag: 'arg', types: true },
					{ tag: 'argument', types: true },
				],
			} ],

			// NOTE: check-tag-names is DISABLED because eslint-plugin-jsdoc enforces
			// opposite tag preferences (return→returns) than the original valid-jsdoc
			// (which preferred returns→return). Disabling avoids ~1600 false positives.
			'jsdoc/check-tag-names': 'off',

			// Disable all other jsdoc rules to match minimal original requirements
			'jsdoc/check-indentation': 'off',
			'jsdoc/check-line-alignment': 'off',
			'jsdoc/check-property-names': 'off',
			'jsdoc/check-syntax': 'off',
			'jsdoc/check-template-names': 'off',
			'jsdoc/check-values': 'off',
			'jsdoc/convert-to-jsdoc-comments': 'off',
			'jsdoc/empty-tags': 'off',
			'jsdoc/implements-on-classes': 'off',
			'jsdoc/match-description': 'off',
			'jsdoc/multiline-blocks': 'off',
			'jsdoc/no-bad-blocks': 'off',
			'jsdoc/no-defaults': 'off',
			'jsdoc/no-types': 'off',
			'jsdoc/require-asterisk-prefix': 'off',
			'jsdoc/require-description': 'off',
			'jsdoc/require-description-complete-sentence': 'off',
			'jsdoc/require-example': 'off',
			'jsdoc/require-file-overview': 'off',
			'jsdoc/require-hyphen-before-param-description': 'off',
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/require-param': 'off',
			'jsdoc/require-param-description': 'off',
			'jsdoc/require-param-name': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/require-property': 'off',
			'jsdoc/require-property-description': 'off',
			'jsdoc/require-property-name': 'off',
			'jsdoc/require-property-type': 'off',
			'jsdoc/require-returns': 'off',
			'jsdoc/require-returns-check': 'off',
			'jsdoc/require-returns-description': 'off',
			'jsdoc/require-returns-type': 'off',
			'jsdoc/require-throws': 'off',
			'jsdoc/require-yields': 'off',
			'jsdoc/require-yields-check': 'off',
			'jsdoc/sort-tags': 'off',
			'jsdoc/tag-lines': 'off',
			'jsdoc/text-escaping': 'off',
			'jsdoc/valid-types': 'off',
		},
	},
];
