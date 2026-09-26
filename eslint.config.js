/**
 * ESLint v10 flat config entry point.
 *
 * This minimal config focuses only on JSDoc linting by importing
 * the configuration from .eslintrc-jsdoc.js.
 *
 * General JavaScript linting is not performed here.
 */

const jsdocConfig = require( './.eslintrc-jsdoc.js' );

module.exports = [
	...jsdocConfig,
	{
		ignores: [
			'build/**',
			'**/build/**',
			'node_modules/**',
			'tests/**',
			'vendor/**',
			'tools/**',
			'jsdoc/**',
			'artifacts/**',
			'coverage/**',
			'.cache/**',
			'src/wp-includes/blocks/**/*.js',
			'src/wp-includes/blocks/**/*.js.map',
			'src/wp-content/themes/**',
			'src/wp-content/plugins/**',
			'src/wp-content/mu-plugins/**',
			'src/wp-content/upgrade/**',
			'src/wp-content/uploads/**',
			'src/js/_enqueues/vendor/**',
			'src/wp-admin/js/**',
			'src/wp-includes/js/**',
		],
	},
];
