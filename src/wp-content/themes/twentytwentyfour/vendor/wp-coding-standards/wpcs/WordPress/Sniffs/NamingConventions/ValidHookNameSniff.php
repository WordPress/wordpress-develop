<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\NamingConventions;

use WordPressCS\WordPress\AbstractFunctionParameterSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Use lowercase letters in action and filter names. Separate words via underscores.
 *
 * This sniff is only testing the hook invoke functions as when using 'add_action'/'add_filter'
 * you can't influence the hook name.
 *
 * Hook names invoked with `do_action_deprecated()` and `apply_filters_deprecated()` are ignored.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#naming-conventions
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.10.0
 * @since   0.11.0 Extends the WordPressCS native `AbstractFunctionParameterSniff` class.
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class ValidHookNameSniff extends AbstractFunctionParameterSniff {

	/**
	 * Additional word separators.
	 *
	 * This public variable allows providing additional word separators which
	 * will be allowed in hook names via a property in the phpcs.xml config file.
	 *
	 * Example usage:
	 * <rule ref="WordPress.NamingConventions.ValidHookName">
	 *   <properties>
	 *     <property name="additionalWordDelimiters" value="-"/>
	 *   </properties>
	 * </rule>
	 *
	 * Provide several extra delimiters as one string:
	 * <rule ref="WordPress.NamingConventions.ValidHookName">
	 *   <properties>
	 *     <property name="additionalWordDelimiters" value="-/."/>
	 *   </properties>
	 * </rule>
	 *
	 * @var string
	 */
	public $additionalWordDelimiters = '';

	/**
	 * Regular expression to test for correct punctuation of a hook name.
	 *
	 * The placeholder will be replaced by potentially provided additional
	 * word delimiters in the `prepare_regex()` method.
	 *
	 * @var string
	 */
	protected $punctuation_regex = '`[^\w%s]`';

	/**
	 * Groups of functions to restrict.
	 *
	 * @since 0.11.0
	 *
	 * @return array
	 */
	public function getGroups() {
		$this->target_functions = $this->hookInvokeFunctions;

		// No need to examine the names of deprecated hooks.
		unset(
			$this->target_functions['do_action_deprecated'],
			$this->target_functions['apply_filters_deprecated']
		);

		return parent::getGroups();
	}

	/**
	 * Process the parameters of a matched function.
	 *
	 * @since 0.11.0
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param string $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 * @param array  $parameters      Array with information about the parameters.
	 *
	 * @return void
	 */
	public function process_parameters( $stackPtr, $group_name, $matched_content, $parameters ) {

		$regex = $this->prepare_regex();

		$case_errors = 0;
		$underscores = 0;
		$content     = array();
		$expected    = array();

		for ( $i = $parameters[1]['start']; $i <= $parameters[1]['end']; $i++ ) {
			// Skip past comment tokens.
			if ( isset( Tokens::$commentTokens[ $this->tokens[ $i ]['code'] ] ) !== false ) {
				continue;
			}

			$content[ $i ]  = $this->tokens[ $i ]['content'];
			$expected[ $i ] = $this->tokens[ $i ]['content'];

			// Skip past potential variable array access: $var['Key'].
			if ( \T_VARIABLE === $this->tokens[ $i ]['code'] ) {
				do {
					$open_bracket = $this->phpcsFile->findNext( Tokens::$emptyTokens, ( $i + 1 ), null, true );
					if ( false === $open_bracket
						|| \T_OPEN_SQUARE_BRACKET !== $this->tokens[ $open_bracket ]['code']
						|| ! isset( $this->tokens[ $open_bracket ]['bracket_closer'] )
					) {
						continue 2;
					}

					$i = $this->tokens[ $open_bracket ]['bracket_closer'];

				} while ( isset( $this->tokens[ $i ] ) && $i <= $parameters[1]['end'] );

				continue;
			}

			// Skip past non-string tokens.
			if ( isset( Tokens::$stringTokens[ $this->tokens[ $i ]['code'] ] ) === false ) {
				continue;
			}

			$string = $this->strip_quotes( $this->tokens[ $i ]['content'] );

			/*
			 * Here be dragons - a double quoted string can contain extrapolated variables
			 * which don't have to comply with these rules.
			 */
			if ( \T_DOUBLE_QUOTED_STRING === $this->tokens[ $i ]['code'] ) {
				$transform       = $this->transform_complex_string( $string, $regex );
				$case_transform  = $this->transform_complex_string( $string, $regex, 'case' );
				$punct_transform = $this->transform_complex_string( $string, $regex, 'punctuation' );
			} else {
				$transform       = $this->transform( $string, $regex );
				$case_transform  = $this->transform( $string, $regex, 'case' );
				$punct_transform = $this->transform( $string, $regex, 'punctuation' );
			}

			if ( $string === $transform ) {
				continue;
			}

			if ( \T_DOUBLE_QUOTED_STRING === $this->tokens[ $i ]['code'] ) {
				$expected[ $i ] = '"' . $transform . '"';
			} else {
				$expected[ $i ] = '\'' . $transform . '\'';
			}

			if ( $string !== $case_transform ) {
				$case_errors++;
			}
			if ( $string !== $punct_transform ) {
				$underscores++;
			}
		}

		$first_non_empty = $this->phpcsFile->findNext(
			Tokens::$emptyTokens,
			$parameters[1]['start'],
			( $parameters[1]['end'] + 1 ),
			true
		);

		$data = array(
			trim( implode( '', $expected ) ),
			trim( implode( '', $content ) ),
		);

		if ( $case_errors > 0 ) {
			$error = 'Hook names should be lowercase. Expected: %s, but found: %s.';
			$this->phpcsFile->addError( $error, $first_non_empty, 'NotLowercase', $data );
		}
		if ( $underscores > 0 ) {
			$error = 'Words in hook names should be separated using underscores. Expected: %s, but found: %s.';
			$this->phpcsFile->addWarning( $error, $first_non_empty, 'UseUnderscores', $data );
		}
	}

	/**
	 * Prepare the punctuation regular expression.
	 *
	 * Merges the existing regular expression with potentially provided extra word delimiters to allow.
	 * This is done 'late' and for each found token as otherwise inline `phpcs:set` directives
	 * would be ignored.
	 *
	 * @return string
	 */
	protected function prepare_regex() {
		$extra = '';
		if ( '' !== $this->additionalWordDelimiters && \is_string( $this->additionalWordDelimiters ) ) {
			$extra = preg_quote( $this->additionalWordDelimiters, '`' );
		}

		return sprintf( $this->punctuation_regex, $extra );
	}

	/**
	 * Transform an arbitrary string to lowercase and replace punctuation and spaces with underscores.
	 *
	 * @param string $string         The target string.
	 * @param string $regex          The punctuation regular expression to use.
	 * @param string $transform_type Whether to a partial or complete transform.
	 *                               Valid values are: 'full', 'case', 'punctuation'.
	 * @return string
	 */
	protected function transform( $string, $regex, $transform_type = 'full' ) {

		switch ( $transform_type ) {
			case 'case':
				return strtolower( $string );

			case 'punctuation':
				return preg_replace( $regex, '_', $string );

			case 'full':
			default:
				return preg_replace( $regex, '_', strtolower( $string ) );
		}
	}

	/**
	 * Transform a complex string which may contain variable extrapolation.
	 *
	 * @param string $string         The target string.
	 * @param string $regex          The punctuation regular expression to use.
	 * @param string $transform_type Whether to a partial or complete transform.
	 *                               Valid values are: 'full', 'case', 'punctuation'.
	 * @return string
	 */
	protected function transform_complex_string( $string, $regex, $transform_type = 'full' ) {
		$output = preg_split( '`([\{\}\$\[\] ])`', $string, -1, \PREG_SPLIT_DELIM_CAPTURE );

		$is_variable = false;
		$has_braces  = false;
		$braces      = 0;

		foreach ( $output as $i => $part ) {
			if ( \in_array( $part, array( '$', '{' ), true ) ) {
				$is_variable = true;
				if ( '{' === $part ) {
					$has_braces = true;
					$braces++;
				}
				continue;
			}

			if ( true === $is_variable ) {
				if ( '[' === $part ) {
					$has_braces = true;
					$braces++;
				}
				if ( \in_array( $part, array( '}', ']' ), true ) ) {
					$braces--;
				}
				if ( false === $has_braces && ' ' === $part ) {
					$is_variable  = false;
					$output[ $i ] = $this->transform( $part, $regex, $transform_type );
				}

				if ( ( true === $has_braces && 0 === $braces ) && false === \in_array( $output[ ( $i + 1 ) ], array( '{', '[' ), true ) ) {
					$has_braces  = false;
					$is_variable = false;
				}
				continue;
			}

			$output[ $i ] = $this->transform( $part, $regex, $transform_type );
		}

		return implode( '', $output );
	}

}
