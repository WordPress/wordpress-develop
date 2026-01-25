// JSHINT has some GPL Compatability issues, so we are faking it out and using espree for validation
// Based on https://github.com/jquery/esprima/blob/gh-pages/demo/validate.js which is MIT licensed

( () => {
	/**
	 * @typedef {Object} JSHINTError
	 * @property {number} line - Line number.
	 * @property {number} character - Column number.
	 * @property {string} reason - Error message.
	 * @property {string} code - Error code.
	 */

	/**
	 * Fake JSHINT.
	 */
	const fakeJSHINT = {
		/**
		 * Collected error(s) during parsing.
		 *
		 * @type {JSHINTError[]}
		 */
		data: [],

		/**
		 * Converts a SyntaxError to a JSHINT error.
		 *
		 * @param {SyntaxError} error - SyntaxError to convert.
		 * @returns {JSHINTError}
		 */
		convertError( error ) {
			return {
				line: error.lineNumber,
				character: error.column,
				reason: error.message,
				code: 'E',
			};
		},

		/**
		 * Parses JS code to find errors.
		 *
		 * @param {string} code - JS code to parse.
		 */
		parse( code ) {
			try {
				window.espree.parse( code, {
					ecmaVersion: 'latest',
					loc: true,
					sourceType: 'module',
				} );
				this.data = [];
			} catch ( error ) {
				this.data.push( this.convertError( error ) );
			}
		},
	};

	window.JSHINT = ( text ) => {
		fakeJSHINT.parse( text );
	};
	window.JSHINT.data = () => {
		return {
			errors: fakeJSHINT.data,
		};
	};
} )();
