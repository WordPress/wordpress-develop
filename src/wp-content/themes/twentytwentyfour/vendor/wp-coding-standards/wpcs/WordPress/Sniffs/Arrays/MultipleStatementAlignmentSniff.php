<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\Arrays;

use WordPressCS\WordPress\Sniff;

/**
 * Enforces alignment of the double arrow assignment operator for multi-item, multi-line arrays.
 *
 * - Align the double arrow operator to the same column for each item in a multi-item array.
 * - Allows for setting a maxColumn property to aid in managing line-length.
 * - Allows for new line(s) before a double arrow (configurable).
 * - Allows for handling multi-line array items differently if so desired (configurable).
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#indentation
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.14.0
 *
 * {@internal This sniff should eventually be pulled upstream as part of a solution
 * for https://github.com/squizlabs/PHP_CodeSniffer/issues/582 }}
 */
class MultipleStatementAlignmentSniff extends Sniff {

	/**
	 * Whether or not to ignore an array item for the purpose of alignment
	 * when a new line is found between the array key and the double arrow.
	 *
	 * @since 0.14.0
	 *
	 * @var bool
	 */
	public $ignoreNewlines = true;

	/**
	 * Whether the alignment should be exact.
	 *
	 * Exact in this context means "largest index key + 1 space".
	 * When `false`, that is seen as the minimum alignment.
	 *
	 * @since 0.14.0
	 *
	 * @var bool
	 */
	public $exact = true;

	/**
	 * The maximum column on which the double arrow alignment should be set.
	 *
	 * This property allows for limiting the whitespace padding to prevent
	 * overly long lines.
	 *
	 * If this value is set to, for instance, 60, it will:
	 * - if the expected column < 60, align at the expected column.
	 * - if the expected column >= 60, align at column 60.
	 *   - for the outliers, i.e. the array indexes where the end position
	 *     goes past column 60, it will not align the arrow, the sniff will
	 *     just make sure there is only one space between the end of the
	 *     array index and the double arrow.
	 *
	 * The column value is regarded as a hard value, i.e. includes indentation,
	 * so setting it very low is not a good idea.
	 *
	 * @since 0.14.0
	 *
	 * @var int
	 */
	public $maxColumn = 1000;

	/**
	 * Whether or not to align the arrow operator for multi-line array items.
	 *
	 * Whether or not an item is regarded as multi-line is based on the **value**
	 * of the item, not the key.
	 *
	 * Valid values are:
	 * - 'always':   Default. Align all arrays items regardless of single/multi-line.
	 * - 'never':    Never align array items which span multiple lines.
	 *               This will enforce one space between the array index and the
	 *               double arrow operator for multi-line array items, independently
	 *               of the alignment of the rest of the array items.
	 *               Multi-line items where the arrow is already aligned with the
	 *               "expected" alignment, however, will be left alone.
	 * - operator :  Only align the operator for multi-line arrays items if the
	 *   + number    percentage of multi-line items passes the comparison.
	 *               - As it is a percentage, the number has to be between 0 and 100.
	 *               - Supported operators: <, <=, >, >=, ==, =, !=, <>
	 *               - The percentage is calculated against all array items
	 *                 (with and without assignment operator).
	 *               - The (new) expected alignment will be calculated based only
	 *                 on the items being aligned.
	 *               - Multi-line items where the arrow is already aligned with the
	 *                 (new) "expected" alignment, however, will be left alone.
	 *               Examples:
	 *               * Setting this to `!=100` or `<100` means that alignment will
	 *                 be enforced, unless *all* array items are multi-line.
	 *                 This is probably the most commonly desired situation.
	 *               * Setting this to `=100` means that alignment will only
	 *                 be enforced, if *all* array items are multi-line.
	 *               * Setting this to `<50` means that the majority of array items
	 *                 need to be single line before alignment is enforced for
	 *                 multi-line items in the array.
	 *               * Setting this to `=0` is useless as in that case there are
	 *                 no multi-line items in the array anyway.
	 *
	 * This setting will respect the `ignoreNewlines` and `maxColumnn` settings.
	 *
	 * @since 0.14.0
	 *
	 * @var string|int
	 */
	public $alignMultilineItems = 'always';

	/**
	 * Storage for parsed $alignMultilineItems operator part.
	 *
	 * @since 0.14.0
	 *
	 * @var string
	 */
	private $operator;

	/**
	 * Storage for parsed $alignMultilineItems numeric part.
	 *
	 * Stored as a string as the comparison will be done string based.
	 *
	 * @since 0.14.0
	 *
	 * @var string
	 */
	private $number;

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @since 0.14.0
	 *
	 * @return array
	 */
	public function register() {
		return array(
			\T_ARRAY,
			\T_OPEN_SHORT_ARRAY,
		);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @since 0.14.0
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	public function process_token( $stackPtr ) {

		if ( \T_OPEN_SHORT_ARRAY === $this->tokens[ $stackPtr ]['code']
			&& $this->is_short_list( $stackPtr )
		) {
			// Short list, not short array.
			return;
		}

		/*
		 * Determine the array opener & closer.
		 */
		$array_open_close = $this->find_array_open_close( $stackPtr );
		if ( false === $array_open_close ) {
			// Array open/close could not be determined.
			return;
		}

		$opener = $array_open_close['opener'];
		$closer = $array_open_close['closer'];

		$array_items = $this->get_function_call_parameters( $stackPtr );
		if ( empty( $array_items ) ) {
			return;
		}

		// Pass off to either the single line or multi-line array analysis.
		if ( $this->tokens[ $opener ]['line'] === $this->tokens[ $closer ]['line'] ) {
			return $this->process_single_line_array( $stackPtr, $array_items, $opener, $closer );
		} else {
			return $this->process_multi_line_array( $stackPtr, $array_items, $opener, $closer );
		}
	}

	/**
	 * Process a single-line array.
	 *
	 * While the WP standard does not allow single line multi-item associative arrays,
	 * this sniff should function independently of that.
	 *
	 * The `WordPress.WhiteSpace.OperatorSpacing` sniff already covers checking that
	 * there is a space between the array key and the double arrow, but doesn't
	 * enforce it to be exactly one space for single line arrays.
	 * That is what this method covers.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $stackPtr The position of the current token in the stack.
	 * @param array $items    Info array containing information on each array item.
	 * @param int   $opener   The position of the array opener.
	 * @param int   $closer   The position of the array closer.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	protected function process_single_line_array( $stackPtr, $items, $opener, $closer ) {
		/*
		 * For single line arrays, we don't care about what level the arrow is from.
		 * Just find and fix them all.
		 */
		$next_arrow = $this->phpcsFile->findNext(
			\T_DOUBLE_ARROW,
			( $opener + 1 ),
			$closer
		);

		while ( false !== $next_arrow ) {
			if ( \T_WHITESPACE === $this->tokens[ ( $next_arrow - 1 ) ]['code'] ) {
				$space_length = $this->tokens[ ( $next_arrow - 1 ) ]['length'];
				if ( 1 !== $space_length ) {
					$error = 'Expected 1 space between "%s" and double arrow; %s found';
					$data  = array(
						$this->tokens[ ( $next_arrow - 2 ) ]['content'],
						$space_length,
					);

					$fix = $this->phpcsFile->addFixableWarning( $error, $next_arrow, 'SpaceBeforeDoubleArrow', $data );
					if ( true === $fix ) {
						$this->phpcsFile->fixer->replaceToken( ( $next_arrow - 1 ), ' ' );
					}
				}
			}

			// Find the position of the next double arrow.
			$next_arrow = $this->phpcsFile->findNext(
				\T_DOUBLE_ARROW,
				( $next_arrow + 1 ),
				$closer
			);
		}

		// Ignore any child-arrays as the double arrows in these will already have been handled.
		return ( $closer + 1 );
	}

	/**
	 * Process a multi-line array.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $stackPtr The position of the current token in the stack.
	 * @param array $items    Info array containing information on each array item.
	 * @param int   $opener   The position of the array opener.
	 * @param int   $closer   The position of the array closer.
	 *
	 * @return void
	 */
	protected function process_multi_line_array( $stackPtr, $items, $opener, $closer ) {

		$this->maxColumn = (int) $this->maxColumn;
		$this->validate_align_multiline_items();

		/*
		 * Determine what the spacing before the arrow should be.
		 *
		 * Will unset any array items without double arrow and with new line whitespace
		 * if newlines are to be ignored, so the second foreach loop only has to deal
		 * with items which need attention.
		 *
		 * This sniff does not take incorrect indentation of array keys into account.
		 * That's for the `WordPress.Arrays.ArrayIndentation` sniff to fix.
		 * If that would affect the alignment, a second (or third) loop of the fixer
		 * will correct it (again) after the indentation has been fixed.
		 */
		$index_end_cols    = array(); // Keep track of the end column position of index keys.
		$double_arrow_cols = array(); // Keep track of arrow column position and count.
		$multi_line_count  = 0;
		$total_items       = \count( $items );

		foreach ( $items as $key => $item ) {
			if ( strpos( $item['raw'], '=>' ) === false ) {
				// Ignore items without assignment operators.
				unset( $items[ $key ] );
				continue;
			}

			// Find the position of the first double arrow.
			$double_arrow = $this->phpcsFile->findNext(
				\T_DOUBLE_ARROW,
				$item['start'],
				( $item['end'] + 1 )
			);

			if ( false === $double_arrow ) {
				// Shouldn't happen, just in case.
				unset( $items[ $key ] );
				continue;
			}

			// Make sure the arrow is for this item and not for a nested array value assignment.
			$has_array_opener = $this->phpcsFile->findNext(
				$this->register(),
				$item['start'],
				$double_arrow
			);

			if ( false !== $has_array_opener ) {
				// Double arrow is for a nested array.
				unset( $items[ $key ] );
				continue;
			}

			// Find the end of the array key.
			$last_index_token = $this->phpcsFile->findPrevious(
				\T_WHITESPACE,
				( $double_arrow - 1 ),
				$item['start'],
				true
			);

			if ( false === $last_index_token ) {
				// Shouldn't happen, but just in case.
				unset( $items[ $key ] );
				continue;
			}

			if ( true === $this->ignoreNewlines
				&& $this->tokens[ $last_index_token ]['line'] !== $this->tokens[ $double_arrow ]['line']
			) {
				// Ignore this item as it has a new line between the item key and the double arrow.
				unset( $items[ $key ] );
				continue;
			}

			$index_end_position                = ( $this->tokens[ $last_index_token ]['column'] + ( $this->tokens[ $last_index_token ]['length'] - 1 ) );
			$items[ $key ]['operatorPtr']      = $double_arrow;
			$items[ $key ]['last_index_token'] = $last_index_token;
			$items[ $key ]['last_index_col']   = $index_end_position;

			if ( $this->tokens[ $last_index_token ]['line'] === $this->tokens[ $item['end'] ]['line'] ) {
				$items[ $key ]['single_line'] = true;
			} else {
				$items[ $key ]['single_line'] = false;
				$multi_line_count++;
			}

			if ( ( $index_end_position + 2 ) <= $this->maxColumn ) {
				$index_end_cols[] = $index_end_position;
			}

			if ( ! isset( $double_arrow_cols[ $this->tokens[ $double_arrow ]['column'] ] ) ) {
				$double_arrow_cols[ $this->tokens[ $double_arrow ]['column'] ] = 1;
			} else {
				$double_arrow_cols[ $this->tokens[ $double_arrow ]['column'] ]++;
			}
		}
		unset( $key, $item, $double_arrow, $has_array_opener, $last_index_token );

		if ( empty( $items ) || empty( $index_end_cols ) ) {
			// No actionable array items found.
			return;
		}

		/*
		 * Determine whether the operators for multi-line items should be aligned.
		 */
		if ( 'always' === $this->alignMultilineItems ) {
			$alignMultilineItems = true;
		} elseif ( 'never' === $this->alignMultilineItems ) {
			$alignMultilineItems = false;
		} else {
			$percentage = (string) round( ( $multi_line_count / $total_items ) * 100, 0 );

			// Bit hacky, but this is the only comparison function in PHP which allows to
			// pass the comparison operator. And hey, it works ;-).
			$alignMultilineItems = version_compare( $percentage, $this->number, $this->operator );
		}

		/*
		 * If necessary, rebuild the $index_end_cols and $double_arrow_cols arrays
		 * excluding multi-line items.
		 */
		if ( false === $alignMultilineItems ) {
			$select_index_end_cols = array();
			$double_arrow_cols     = array();

			foreach ( $items as $item ) {
				if ( false === $item['single_line'] ) {
					continue;
				}

				if ( ( $item['last_index_col'] + 2 ) <= $this->maxColumn ) {
					$select_index_end_cols[] = $item['last_index_col'];
				}

				if ( ! isset( $double_arrow_cols[ $this->tokens[ $item['operatorPtr'] ]['column'] ] ) ) {
					$double_arrow_cols[ $this->tokens[ $item['operatorPtr'] ]['column'] ] = 1;
				} else {
					$double_arrow_cols[ $this->tokens[ $item['operatorPtr'] ]['column'] ]++;
				}
			}
		}

		/*
		 * Determine the expected position of the double arrows.
		 */
		if ( ! empty( $select_index_end_cols ) ) {
			$max_index_width = max( $select_index_end_cols );
		} else {
			$max_index_width = max( $index_end_cols );
		}

		$expected_col = ( $max_index_width + 2 );

		if ( false === $this->exact && ! empty( $double_arrow_cols ) ) {
			/*
			 * If the alignment does not have to be exact, see if a majority
			 * group of the arrows is already at an acceptable position.
			 */
			arsort( $double_arrow_cols, \SORT_NUMERIC );
			reset( $double_arrow_cols );
			$count = current( $double_arrow_cols );

			if ( $count > 1 || ( 1 === $count && \count( $items ) === 1 ) ) {
				// Allow for several groups of arrows having the same $count.
				$filtered_double_arrow_cols = array_keys( $double_arrow_cols, $count, true );

				foreach ( $filtered_double_arrow_cols as $col ) {
					if ( $col > $expected_col && $col <= $this->maxColumn ) {
						$expected_col = $col;
						break;
					}
				}
			}
		}
		unset( $max_index_width, $count, $filtered_double_arrow_cols, $col );

		/*
		 * Verify and correct the spacing around the double arrows.
		 */
		foreach ( $items as $item ) {
			if ( $this->tokens[ $item['operatorPtr'] ]['column'] === $expected_col
				&& $this->tokens[ $item['operatorPtr'] ]['line'] === $this->tokens[ $item['last_index_token'] ]['line']
			) {
				// Already correctly aligned.
				continue;
			}

			if ( \T_WHITESPACE !== $this->tokens[ ( $item['operatorPtr'] - 1 ) ]['code'] ) {
				$before = 0;
			} else {
				if ( $this->tokens[ $item['last_index_token'] ]['line'] !== $this->tokens[ $item['operatorPtr'] ]['line'] ) {
					$before = 'newline';
				} else {
					$before = $this->tokens[ ( $item['operatorPtr'] - 1 ) ]['length'];
				}
			}

			/*
			 * Deal with index sizes larger than maxColumn and with multi-line
			 * array items which should not be aligned.
			 */
			if ( ( $item['last_index_col'] + 2 ) > $this->maxColumn
				|| ( false === $alignMultilineItems && false === $item['single_line'] )
			) {

				if ( ( $item['last_index_col'] + 2 ) === $this->tokens[ $item['operatorPtr'] ]['column']
					&& $this->tokens[ $item['operatorPtr'] ]['line'] === $this->tokens[ $item['last_index_token'] ]['line']
				) {
					// MaxColumn/Multi-line item exception, already correctly aligned.
					continue;
				}

				$prefix = 'LongIndex';
				if ( false === $alignMultilineItems && false === $item['single_line'] ) {
					$prefix = 'MultilineItem';
				}

				$error_code = $prefix . 'SpaceBeforeDoubleArrow';
				if ( 0 === $before ) {
					$error_code = $prefix . 'NoSpaceBeforeDoubleArrow';
				}

				$fix = $this->phpcsFile->addFixableWarning(
					'Expected 1 space between "%s" and double arrow; %s found.',
					$item['operatorPtr'],
					$error_code,
					array(
						$this->tokens[ $item['last_index_token'] ]['content'],
						$before,
					)
				);

				if ( true === $fix ) {
					$this->phpcsFile->fixer->beginChangeset();

					// Remove whitespace tokens between the end of the index and the arrow, if any.
					for ( $i = ( $item['last_index_token'] + 1 ); $i < $item['operatorPtr']; $i++ ) {
						$this->phpcsFile->fixer->replaceToken( $i, '' );
					}

					// Add the correct whitespace.
					$this->phpcsFile->fixer->addContent( $item['last_index_token'], ' ' );

					$this->phpcsFile->fixer->endChangeset();
				}
				continue;
			}

			/*
			 * Deal with the space before double arrows in all other cases.
			 */
			$expected_whitespace = $expected_col - ( $this->tokens[ $item['last_index_token'] ]['column'] + $this->tokens[ $item['last_index_token'] ]['length'] );

			$fix = $this->phpcsFile->addFixableWarning(
				'Array double arrow not aligned correctly; expected %s space(s) between "%s" and double arrow, but found %s.',
				$item['operatorPtr'],
				'DoubleArrowNotAligned',
				array(
					$expected_whitespace,
					$this->tokens[ $item['last_index_token'] ]['content'],
					$before,
				)
			);

			if ( true === $fix ) {
				if ( 0 === $before || 'newline' === $before ) {
					$this->phpcsFile->fixer->beginChangeset();

					// Remove whitespace tokens between the end of the index and the arrow, if any.
					for ( $i = ( $item['last_index_token'] + 1 ); $i < $item['operatorPtr']; $i++ ) {
						$this->phpcsFile->fixer->replaceToken( $i, '' );
					}

					// Add the correct whitespace.
					$this->phpcsFile->fixer->addContent(
						$item['last_index_token'],
						str_repeat( ' ', $expected_whitespace )
					);

					$this->phpcsFile->fixer->endChangeset();
				} elseif ( $expected_whitespace > $before ) {
					// Add to the existing whitespace to prevent replacing tabs with spaces.
					// That's the concern of another sniff.
					$this->phpcsFile->fixer->addContent(
						( $item['operatorPtr'] - 1 ),
						str_repeat( ' ', ( $expected_whitespace - $before ) )
					);
				} else {
					// Too much whitespace found.
					$this->phpcsFile->fixer->replaceToken(
						( $item['operatorPtr'] - 1 ),
						str_repeat( ' ', $expected_whitespace )
					);
				}
			}
		}
	}

	/**
	 * Validate that a valid value has been received for the alignMultilineItems property.
	 *
	 * This message may be thrown more than once if the property is being changed inline in a file.
	 *
	 * @since 0.14.0
	 */
	protected function validate_align_multiline_items() {
		$alignMultilineItems = $this->alignMultilineItems;

		if ( 'always' === $alignMultilineItems || 'never' === $alignMultilineItems ) {
			return;
		} else {
			// Correct for a potentially added % sign.
			$alignMultilineItems = rtrim( $alignMultilineItems, '%' );

			if ( preg_match( '`^([=<>!]{1,2})(100|[0-9]{1,2})$`', $alignMultilineItems, $matches ) > 0 ) {
				$operator = $matches[1];
				$number   = (int) $matches[2];

				if ( \in_array( $operator, array( '<', '<=', '>', '>=', '==', '=', '!=', '<>' ), true ) === true
					&& ( $number >= 0 && $number <= 100 )
				) {
					$this->alignMultilineItems = $alignMultilineItems;
					$this->number              = (string) $number;
					$this->operator            = $operator;
					return;
				}
			}
		}

		$this->phpcsFile->addError(
			'Invalid property value passed: "%s". The value for the "alignMultilineItems" property for the "WordPress.Arrays.MultipleStatementAlignment" sniff should be either "always", "never" or an comparison operator + a number between 0 and 100.',
			0,
			'InvalidPropertyPassed',
			array( $this->alignMultilineItems )
		);

		// Reset to the default if an invalid value was received.
		$this->alignMultilineItems = 'always';
	}

}
