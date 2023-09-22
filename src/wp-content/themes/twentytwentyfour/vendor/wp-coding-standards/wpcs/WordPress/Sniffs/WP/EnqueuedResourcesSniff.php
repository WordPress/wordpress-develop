<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\WP;

use WordPressCS\WordPress\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Makes sure scripts and styles are enqueued and not explicitly echo'd.
 *
 * @link    https://vip.wordpress.com/documentation/vip-go/code-review-blockers-warnings-notices/#inline-resources
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.3.0
 * @since   0.12.0 This class now extends the WordPressCS native `Sniff` class.
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class EnqueuedResourcesSniff extends Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return Tokens::$textStringTokens;
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process_token( $stackPtr ) {
		$token = $this->tokens[ $stackPtr ];

		if ( preg_match( '# rel=\\\\?[\'"]?stylesheet\\\\?[\'"]?#', $token['content'] ) > 0 ) {
			$this->phpcsFile->addError(
				'Stylesheets must be registered/enqueued via wp_enqueue_style',
				$stackPtr,
				'NonEnqueuedStylesheet'
			);
		}

		if ( preg_match( '#<script[^>]*(?<=src=)#', $token['content'] ) > 0 ) {
			$this->phpcsFile->addError(
				'Scripts must be registered/enqueued via wp_enqueue_script',
				$stackPtr,
				'NonEnqueuedScript'
			);
		}
	}

}
