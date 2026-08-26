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
		settings: {
			jsdoc: {
				tagNamePreference: {
					'arg': 'param',
					'argument': 'param',
					'extends': 'augments',
					'returns': 'return',
				},
				preferredTypes: {
					'array': 'Array',
					'bool': 'boolean',
					'Boolean': 'boolean',
					'float': 'number',
					'Float': 'number',
					'function': 'Function',
					'int': 'number',
					'integer': 'number',
					'Integer': 'number',
					'Number': 'number',
					'object': 'Object',
					'String': 'string',
					'Void': 'void',
				},
			},
		},
		rules: {
			'jsdoc/check-param-names': 'error',
			'jsdoc/check-types': 'error',
			'jsdoc/check-tag-names': [ 'error', {
				definedTags: [
					'memberOf',
					'output',
					'ticket',
					'link',
				],
			} ],
			'jsdoc/require-param': [ 'error', {
				enableFixer: false,
			} ],
			'jsdoc/require-param-type': 'error',
			'jsdoc/require-returns-check': 'error',
			'jsdoc/require-returns-description': 'error',
			'jsdoc/require-returns': 'error',
			'jsdoc/require-returns-type': 'error',
			'jsdoc/check-syntax': 'error',
		},
	},
];
