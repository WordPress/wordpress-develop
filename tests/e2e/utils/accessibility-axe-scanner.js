/**
 * AxeScanner utility for accessibility scanning with axe-core.
 *
 * Encapsulates axe-core scanning logic, result formatting, and assertions.
 * Can be configured with global rules and logger functions.
 *
 * @example
 * const scanner = new AxeScanner({ page, globalRules: { runOnly: ['wcag2a', 'wcag2aa'] } });
 * const results = await scanner.scan();
 * scanner.formatResults(results);
 * scanner.assertNoViolations(results);
 */

const AxeBuilder = require( '@axe-core/playwright' ).default;

class AxeScanner {
	/**
	 * Create an AxeScanner instance.
	 *
	 * @param {Object} options
	 * @param {Object} options.page          Playwright page object.
	 * @param {Object} [options.globalRules] Rules config passed to axe (e.g., { runOnly: ['wcag2a', 'wcag2aa'], rules: {...} }).
	 * @param {Function} [options.logger]    Custom logger function (defaults to console.log).
	 */
	constructor( { page, globalRules = {}, logger = console.log } ) {
		this.page = page;
		this.globalRules = globalRules;
		this.logger = logger;
	}

	/**
	 * Runs axe-core scan on the current page.
	 *
	 * @param {Object} [overrideOptions] Additional options to merge with globalRules (useful for per-page overrides).
	 * @returns {Promise<Object>} Axe results object (violations, passes, incomplete, inapplicable).
	 */
	async scan( overrideOptions = {} ) {
		const options = this._mergeRulesOptions( this.globalRules, overrideOptions );

		const results = await new AxeBuilder( { page: this.page } )
			.options( options )
			.analyze();

		return results;
	}

	/**
	 * Formats and logs scan results to console.
	 * Outputs violation details, element selectors, and HTML snippets.
	 *
	 * @param {Object} results Axe results object.
	 */
	formatResults( results ) {
		const violationsAmount = results.violations.length;

		if ( violationsAmount === 0 ) {
			this.logger( 'No accessibility violations found!' );
			return;
		}

		this.logger( `\nFound ${ violationsAmount } accessibility violation(s):\n` );

		/*
		 * Result Object documentation: https://github.com/dequelabs/axe-core/blob/master/doc/API.md#results-object
		 * This object has four components:
		 *   - a `passes` array:        keeps track of all the passed tests,
		 *                              along with detailed information on each one.
		 *   - a `violations` array:    keeps track of all the failed tests,
		 *                              along with detailed information on each one.
		 *   - an `incomplete` array:   indicates which nodes could neither be
		 *                              determined to definitively pass or definitively
		 *                              fail. They are separated out in order that
		 *                              a user interface can display these to the
		 *                              user for manual review
		 *   - an `inapplicable` array: lists all the rules for which no matching
		 *                              elements were found on the page.
		 */
		results.violations.forEach( ( violation, index ) => {
			this.logger( `--- Violation #${ index + 1 } ---` );
			this.logger( `Rule ID:   ${ violation.id }` );
			this.logger( `Impact:    ${ violation.impact.toUpperCase() }` );
			this.logger( `Failure:   ${ violation.description }` );
			this.logger( `Help:      ${ violation.help }` );
			this.logger( `Help link: ${ violation.helpUrl }` );

			// List every specific HTML element failing a failing rule.
			this.logger( 'Failing elements:' );
			violation.nodes.forEach( ( node ) => {
				this.logger( `  - Target Selector: ${ node.target.join( ', ' ) }` );
				this.logger( `  - HTML Snippet:\n${ node.html }` );
			} );
			this.logger( '\n' );
		} );
	}

	/**
	 * Asserts that there are no accessibility violations.
	 * Returns the violations amount; let the test use expect() for cleaner error reporting.
	 *
	 * @param {Object} results Axe results object.
	 * @returns {number} Number of violations found.
	 */
	getViolationsCount( results ) {
		return results.violations.length;
	}

	/**
	 * Merges rules options, with overrides taking precedence over global rules..
	 *
	 * @private
	 * @param {Object} globalRules   Global rules config.
	 * @param {Object} overrideRules Override rules config.
	 * @returns {Object} Merged rules object.
	 */
	_mergeRulesOptions( globalRules, overrideRules ) {
		// Deep merge rules object to allow per-rule overrides
		if ( globalRules.rules && overrideRules.rules ) {
			return {
				...globalRules,
				...overrideRules,
				rules: {
					...globalRules.rules,
					...overrideRules.rules,
				},
			};
		}

		return {
			...globalRules,
			...overrideRules,
		};
	}
}

module.exports = AxeScanner;
