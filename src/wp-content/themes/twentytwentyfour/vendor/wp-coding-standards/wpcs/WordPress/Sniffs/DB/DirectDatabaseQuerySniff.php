<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\DB;

use WordPressCS\WordPress\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flag Database direct queries.
 *
 * @link    https://vip.wordpress.com/documentation/vip-go/code-review-blockers-warnings-notices/#direct-database-queries
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.3.0
 * @since   0.6.0  Removed the add_unique_message() function as it is no longer needed.
 * @since   0.11.0 This class now extends the WordPressCS native `Sniff` class.
 * @since   0.13.0 Class name changed: this class is now namespaced.
 * @since   1.0.0  This sniff has been moved from the `VIP` category to the `DB` category.
 */
class DirectDatabaseQuerySniff extends Sniff {

	/**
	 * List of custom cache get functions.
	 *
	 * @since 0.6.0
	 *
	 * @var string|string[]
	 */
	public $customCacheGetFunctions = array();

	/**
	 * List of custom cache set functions.
	 *
	 * @since 0.6.0
	 *
	 * @var string|string[]
	 */
	public $customCacheSetFunctions = array();

	/**
	 * List of custom cache delete functions.
	 *
	 * @since 0.6.0
	 *
	 * @var string|string[]
	 */
	public $customCacheDeleteFunctions = array();

	/**
	 * Cache of previously added custom functions.
	 *
	 * Prevents having to do the same merges over and over again.
	 *
	 * @since 0.11.0
	 *
	 * @var array
	 */
	protected $addedCustomFunctions = array(
		'cacheget'    => array(),
		'cacheset'    => array(),
		'cachedelete' => array(),
	);

	/**
	 * The lists of $wpdb methods.
	 *
	 * @since 0.6.0
	 * @since 0.11.0 Changed from static to non-static.
	 *
	 * @var array[]
	 */
	protected $methods = array(
		'cachable' => array(
			'delete'      => true,
			'get_var'     => true,
			'get_col'     => true,
			'get_row'     => true,
			'get_results' => true,
			'query'       => true,
			'replace'     => true,
			'update'      => true,
		),
		'noncachable' => array(
			'insert' => true,
		),
	);

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			\T_VARIABLE,
		);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	public function process_token( $stackPtr ) {

		// Check for $wpdb variable.
		if ( '$wpdb' !== $this->tokens[ $stackPtr ]['content'] ) {
			return;
		}

		$is_object_call = $this->phpcsFile->findNext( \T_OBJECT_OPERATOR, ( $stackPtr + 1 ), null, false, null, true );
		if ( false === $is_object_call ) {
			return; // This is not a call to the wpdb object.
		}

		$methodPtr = $this->phpcsFile->findNext( array( \T_WHITESPACE ), ( $is_object_call + 1 ), null, true, null, true );
		$method    = $this->tokens[ $methodPtr ]['content'];

		$this->mergeFunctionLists();

		if ( ! isset( $this->methods['all'][ $method ] ) ) {
			return;
		}

		$endOfStatement   = $this->phpcsFile->findNext( \T_SEMICOLON, ( $stackPtr + 1 ), null, false, null, true );
		$endOfLineComment = '';
		for ( $i = ( $endOfStatement + 1 ); $i < $this->phpcsFile->numTokens; $i++ ) {

			if ( $this->tokens[ $i ]['line'] !== $this->tokens[ $endOfStatement ]['line'] ) {
				break;
			}

			if ( \T_COMMENT === $this->tokens[ $i ]['code'] ) {
				$endOfLineComment .= $this->tokens[ $i ]['content'];
			}
		}

		$whitelisted_db_call = false;
		if ( preg_match( '/db call\W*(?:ok|pass|clear|whitelist)/i', $endOfLineComment ) ) {
			$whitelisted_db_call = true;
		}

		// Check for Database Schema Changes.
		for ( $_pos = ( $stackPtr + 1 ); $_pos < $endOfStatement; $_pos++ ) {
			$_pos = $this->phpcsFile->findNext( Tokens::$textStringTokens, $_pos, $endOfStatement, false, null, true );
			if ( false === $_pos ) {
				break;
			}

			if ( preg_match( '#\b(?:ALTER|CREATE|DROP)\b#i', $this->tokens[ $_pos ]['content'] ) > 0 ) {
				$this->phpcsFile->addWarning( 'Attempting a database schema change is discouraged.', $_pos, 'SchemaChange' );
			}
		}

		// Flag instance if not whitelisted.
		if ( ! $whitelisted_db_call ) {
			$this->phpcsFile->addWarning( 'Usage of a direct database call is discouraged.', $stackPtr, 'DirectQuery' );
		}

		if ( ! isset( $this->methods['cachable'][ $method ] ) ) {
			return $endOfStatement;
		}

		$whitelisted_cache = false;
		$cached            = false;
		$wp_cache_get      = false;
		if ( preg_match( '/cache\s+(?:ok|pass|clear|whitelist)/i', $endOfLineComment ) ) {
			$whitelisted_cache = true;
		}
		if ( ! $whitelisted_cache && ! empty( $this->tokens[ $stackPtr ]['conditions'] ) ) {
			$scope_function = $this->phpcsFile->getCondition( $stackPtr, \T_FUNCTION );

			if ( false === $scope_function ) {
				$scope_function = $this->phpcsFile->getCondition( $stackPtr, \T_CLOSURE );
			}

			if ( false !== $scope_function ) {
				$scopeStart = $this->tokens[ $scope_function ]['scope_opener'];
				$scopeEnd   = $this->tokens[ $scope_function ]['scope_closer'];

				for ( $i = ( $scopeStart + 1 ); $i < $scopeEnd; $i++ ) {
					if ( \T_STRING === $this->tokens[ $i ]['code'] ) {

						if ( isset( $this->cacheDeleteFunctions[ $this->tokens[ $i ]['content'] ] ) ) {

							if ( \in_array( $method, array( 'query', 'update', 'replace', 'delete' ), true ) ) {
								$cached = true;
								break;
							}
						} elseif ( isset( $this->cacheGetFunctions[ $this->tokens[ $i ]['content'] ] ) ) {

							$wp_cache_get = true;

						} elseif ( isset( $this->cacheSetFunctions[ $this->tokens[ $i ]['content'] ] ) ) {

							if ( $wp_cache_get ) {
								$cached = true;
								break;
							}
						}
					}
				}
			}
		}

		if ( ! $cached && ! $whitelisted_cache ) {
			$message = 'Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete().';
			$this->phpcsFile->addWarning( $message, $stackPtr, 'NoCaching' );
		}

		return $endOfStatement;
	}

	/**
	 * Merge custom functions provided via a custom ruleset with the defaults, if we haven't already.
	 *
	 * @since 0.11.0 Split out from the `process()` method.
	 *
	 * @return void
	 */
	protected function mergeFunctionLists() {
		if ( ! isset( $this->methods['all'] ) ) {
			$this->methods['all'] = array_merge( $this->methods['cachable'], $this->methods['noncachable'] );
		}

		if ( $this->customCacheGetFunctions !== $this->addedCustomFunctions['cacheget'] ) {
			$this->cacheGetFunctions = $this->merge_custom_array(
				$this->customCacheGetFunctions,
				$this->cacheGetFunctions
			);

			$this->addedCustomFunctions['cacheget'] = $this->customCacheGetFunctions;
		}

		if ( $this->customCacheSetFunctions !== $this->addedCustomFunctions['cacheset'] ) {
			$this->cacheSetFunctions = $this->merge_custom_array(
				$this->customCacheSetFunctions,
				$this->cacheSetFunctions
			);

			$this->addedCustomFunctions['cacheset'] = $this->customCacheSetFunctions;
		}

		if ( $this->customCacheDeleteFunctions !== $this->addedCustomFunctions['cachedelete'] ) {
			$this->cacheDeleteFunctions = $this->merge_custom_array(
				$this->customCacheDeleteFunctions,
				$this->cacheDeleteFunctions
			);

			$this->addedCustomFunctions['cachedelete'] = $this->customCacheDeleteFunctions;
		}
	}

}
