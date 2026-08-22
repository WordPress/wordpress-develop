/**
 * ESLint v10 flat config file for JSDoc linting.
 *
 * This file contains JSDoc validation rules converted to ESLint v10's
 * flat config format. It can be used standalone or imported by eslint.config.js.
 */

const wordpressPlugin = require( '@wordpress/eslint-plugin' );

module.exports = [
	...wordpressPlugin.configs.jsdoc,
];
