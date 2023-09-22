<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress;

use WordPressCS\WordPress\Sniff;

/**
 * Restricts array assignment of certain keys.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.3.0
 * @since   0.10.0 Class became a proper abstract class. This was already the behaviour.
 *                 Moved the file and renamed the class from
 *                 `\WordPressCS\WordPress\Sniffs\Arrays\ArrayAssignmentRestrictionsSniff` to
 *                 `\WordPressCS\WordPress\AbstractArrayAssignmentRestrictionsSniff`.
 */
abstract class AbstractArrayAssignmentRestrictionsSniff extends Sniff {

	/**
	 * Exclude groups.
	 *
	 * Example: 'foo,bar'
	 *
	 * @since 0.3.0
	 * @since 1.0.0 This property now expects to be passed an array.
	 *              Previously a comma-delimited string was expected.
	 *
	 * @var array
	 */
	public $exclude = array();

	/**
	 * Groups of variable data to check against.
	 * Don't use this in extended classes, override getGroups() instead.
	 * This is only used for Unit tests.
	 *
	 * @var array
	 */
	public static $groups = array();

	/**
	 * Cache for the excluded groups information.
	 *
	 * @since 0.11.0
	 *
	 * @var array
	 */
	protected $excluded_groups = array();

	/**
	 * Cache for the group information.
	 *
	 * @since 0.13.0
	 *
	 * @var array
	 */
	protected $groups_cache = array();

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		// Retrieve the groups only once and don't set up a listener if there are no groups.
		if ( false === $this->setup_groups() ) {
			return array();
		}

		return array(
			\T_DOUBLE_ARROW,
			\T_CLOSE_SQUARE_BRACKET,
			\T_CONSTANT_ENCAPSED_STRING,
			\T_DOUBLE_QUOTED_STRING,
		);
	}

	/**
	 * Groups of variables to restrict.
	 *
	 * This method should be overridden in extending classes.
	 *
	 * Example: groups => array(
	 *  'groupname' => array(
	 *      'type'     => 'error' | 'warning',
	 *      'message'  => 'Dont use this one please!',
	 *      'keys'     => array( 'key1', 'another_key' ),
	 *      'callback' => array( 'class', 'method' ), // Optional.
	 *  )
	 * )
	 *
	 * @return array
	 */
	abstract public function getGroups();

	/**
	 * Cache the groups.
	 *
	 * @since 0.13.0
	 *
	 * @return bool True if the groups were setup. False if not.
	 */
	protected function setup_groups() {
		$this->groups_cache = $this->getGroups();

		if ( empty( $this->groups_cache ) && empty( self::$groups ) ) {
			return false;
		}

		// Allow for adding extra unit tests.
		if ( ! empty( self::$groups ) ) {
			$this->groups_cache = array_merge( $this->groups_cache, self::$groups );
		}

		return true;
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process_token( $stackPtr ) {

		$this->excluded_groups = $this->merge_custom_array( $this->exclude );
		if ( array_diff_key( $this->groups_cache, $this->excluded_groups ) === array() ) {
			// All groups have been excluded.
			// Don't remove the listener as the exclude property can be changed inline.
			return;
		}

		$token = $this->tokens[ $stackPtr ];

		if ( \T_CLOSE_SQUARE_BRACKET === $token['code'] ) {
			$equal = $this->phpcsFile->findNext( \T_WHITESPACE, ( $stackPtr + 1 ), null, true );
			if ( \T_EQUAL !== $this->tokens[ $equal ]['code'] ) {
				return; // This is not an assignment!
			}
		}

		// Instances: Multi-dimensional array, keyed by line.
		$inst = array();

		/*
		 * Covers:
		 * $foo = array( 'bar' => 'taz' );
		 * $foo['bar'] = $taz;
		 */
		if ( \in_array( $token['code'], array( \T_CLOSE_SQUARE_BRACKET, \T_DOUBLE_ARROW ), true ) ) {
			$operator = $stackPtr; // T_DOUBLE_ARROW.
			if ( \T_CLOSE_SQUARE_BRACKET === $token['code'] ) {
				$operator = $this->phpcsFile->findNext( \T_EQUAL, ( $stackPtr + 1 ) );
			}

			$keyIdx = $this->phpcsFile->findPrevious( array( \T_WHITESPACE, \T_CLOSE_SQUARE_BRACKET ), ( $operator - 1 ), null, true );
			if ( ! is_numeric( $this->tokens[ $keyIdx ]['content'] ) ) {
				$key            = $this->strip_quotes( $this->tokens[ $keyIdx ]['content'] );
				$valStart       = $this->phpcsFile->findNext( array( \T_WHITESPACE ), ( $operator + 1 ), null, true );
				$valEnd         = $this->phpcsFile->findNext( array( \T_COMMA, \T_SEMICOLON ), ( $valStart + 1 ), null, false, null, true );
				$val            = $this->phpcsFile->getTokensAsString( $valStart, ( $valEnd - $valStart ) );
				$val            = $this->strip_quotes( $val );
				$inst[ $key ][] = array( $val, $token['line'] );
			}
		} elseif ( \in_array( $token['code'], array( \T_CONSTANT_ENCAPSED_STRING, \T_DOUBLE_QUOTED_STRING ), true ) ) {
			// $foo = 'bar=taz&other=thing';
			if ( preg_match_all( '#(?:^|&)([a-z_]+)=([^&]*)#i', $this->strip_quotes( $token['content'] ), $matches ) <= 0 ) {
				return; // No assignments here, nothing to check.
			}
			foreach ( $matches[1] as $i => $_k ) {
				$inst[ $_k ][] = array( $matches[2][ $i ], $token['line'] );
			}
		}

		if ( empty( $inst ) ) {
			return;
		}

		foreach ( $this->groups_cache as $groupName => $group ) {

			if ( isset( $this->excluded_groups[ $groupName ] ) ) {
				continue;
			}

			$callback = ( isset( $group['callback'] ) && is_callable( $group['callback'] ) ) ? $group['callback'] : array( $this, 'callback' );

			foreach ( $inst as $key => $assignments ) {
				foreach ( $assignments as $occurance ) {
					list( $val, $line ) = $occurance;

					if ( ! \in_array( $key, $group['keys'], true ) ) {
						continue;
					}

					$output = \call_user_func( $callback, $key, $val, $line, $group );

					if ( ! isset( $output ) || false === $output ) {
						continue;
					} elseif ( true === $output ) {
						$message = $group['message'];
					} else {
						$message = $output;
					}

					$this->addMessage(
						$message,
						$stackPtr,
						( 'error' === $group['type'] ),
						$this->string_to_errorcode( $groupName . '_' . $key ),
						array( $key, $val )
					);
				}
			}
		}
	}

	/**
	 * Callback to process each confirmed key, to check value.
	 *
	 * This method must be extended to add the logic to check assignment value.
	 *
	 * @param  string $key   Array index / key.
	 * @param  mixed  $val   Assigned value.
	 * @param  int    $line  Token line.
	 * @param  array  $group Group definition.
	 * @return mixed         FALSE if no match, TRUE if matches, STRING if matches
	 *                       with custom error message passed to ->process().
	 */
	abstract public function callback( $key, $val, $line, $group );

}
