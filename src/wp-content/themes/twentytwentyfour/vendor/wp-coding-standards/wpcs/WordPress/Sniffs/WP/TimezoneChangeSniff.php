<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\WP;

use WordPressCS\WordPress\Sniffs\DateTime\RestrictedFunctionsSniff;

/**
 * Disallow the changing of timezone.
 *
 * @link    https://vip.wordpress.com/documentation/vip-go/code-review-blockers-warnings-notices/#manipulating-the-timezone-server-side
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.3.0
 * @since   0.11.0 Extends the WordPressCS native `AbstractFunctionRestrictionsSniff`
 *                 class instead of the upstream `Generic.PHP.ForbiddenFunctions` sniff.
 * @since   0.13.0 Class name changed: this class is now namespaced.
 * @since   1.0.0  This sniff has been moved from the `VIP` category to the `WP` category.
 *
 * @deprecated 2.2.0 Use the `WordPress.DateTime.RestrictedFunctions` sniff instead.
 *                   This `WordPress.WP.TimezoneChange` sniff will be removed in WPCS 3.0.0.
 */
class TimezoneChangeSniff extends RestrictedFunctionsSniff {

	/**
	 * Keep track of whether the warnings have been thrown to prevent
	 * the messages being thrown for every token triggering the sniff.
	 *
	 * @since 2.2.0
	 *
	 * @var array
	 */
	private $thrown = array(
		'DeprecatedSniff'                 => false,
		'FoundPropertyForDeprecatedSniff' => false,
	);

	/**
	 * Don't use.
	 *
	 * @deprecated 2.2.0
	 *
	 * @return array
	 */
	public function getGroups() {
		$groups = parent::getGroups();
		return array( 'timezone_change' => $groups['timezone_change'] );
	}

	/**
	 * Don't use.
	 *
	 * @since      2.2.0 Added to allow for throwing the deprecation notices.
	 * @deprecated 2.2.0
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return void|int
	 */
	public function process_token( $stackPtr ) {
		if ( false === $this->thrown['DeprecatedSniff'] ) {
			$this->thrown['DeprecatedSniff'] = $this->phpcsFile->addWarning(
				'The "WordPress.WP.TimezoneChange" sniff has been deprecated. Use the "WordPress.DateTime.RestrictedFunctions" sniff instead. Please update your custom ruleset.',
				0,
				'DeprecatedSniff'
			);
		}

		if ( ! empty( $this->exclude )
			&& false === $this->thrown['FoundPropertyForDeprecatedSniff']
		) {
			$this->thrown['FoundPropertyForDeprecatedSniff'] = $this->phpcsFile->addWarning(
				'The "WordPress.WP.TimezoneChange" sniff has been deprecated. Use the "WordPress.DateTime.RestrictedFunctions" sniff instead. "exclude" property setting found. Please update your custom ruleset.',
				0,
				'FoundPropertyForDeprecatedSniff'
			);
		}

		return parent::process_token( $stackPtr );
	}
}
