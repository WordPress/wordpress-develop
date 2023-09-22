<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\PluginTerritory;

use WordPressCS\WordPress\AbstractFunctionParameterSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Discourages removal of the admin bar.
 *
 * @link  https://make.wordpress.org/themes/handbook/review/required/#core-functionality-and-features
 *
 * @since WPCS 0.3.0
 * @since WPCS 0.11.0 - Extends the WPCS native `AbstractFunctionParameterSniff` class.
 *                    - Added the $remove_only property.
 *                    - Now also sniffs for manipulation of the admin bar visibility through CSS.
 * @since WPCS 0.13.0 Class name changed: this class is now namespaced.
 *
 * @since TRTCS 0.1.0 As this sniff will be removed from WPCS in version 2.0, the
 *                    sniff has been cherry-picked into the WPThemeReview standard.
 */
class AdminBarRemovalSniff extends AbstractFunctionParameterSniff {

	/**
	 * A list of tokenizers this sniff supports.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @var array
	 */
	public $supportedTokenizers = [ 'PHP', 'CSS' ];

	/**
	 * Whether or not the sniff only checks for removal of the admin bar
	 * or any manipulation to the visibility of the admin bar.
	 *
	 * Defaults to true: only check for removal of the admin bar.
	 * Set to false to check for any form of manipulation of the visibility
	 * of the admin bar.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @var bool
	 */
	public $remove_only = true;

	/**
	 * Functions this sniff is looking for.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @var array
	 */
	protected $target_functions = [
		'show_admin_bar' => true,
		'add_filter'     => true,
	];

	/**
	 * CSS properties this sniff is looking for.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @var array
	 */
	protected $target_css_properties = [
		'visibility' => [
			'type'  => '!=',
			'value' => 'hidden',
		],
		'display' => [
			'type'  => '!=',
			'value' => 'none',
		],
		'opacity' => [
			'type'  => '>',
			'value' => 0.3,
		],
	];

	/**
	 * CSS selectors this sniff is looking for.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @var array
	 */
	protected $target_css_selectors = [
		'.show-admin-bar',
		'#wpadminbar',
	];

	/**
	 * Regex template for use with the CSS selectors in combination with PHP text strings.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @var string
	 */
	private $target_css_selectors_regex = '`(?:%s).*?\{(.*)$`';

	/**
	 * Property to keep track of whether a <style> open tag has been encountered.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @var array
	 */
	private $in_style;

	/**
	 * Property to keep track of whether a one of the target selectors has been encountered.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @var array
	 */
	private $in_target_selector;

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		$targets = Tokens::$textStringTokens;

		// Add CSS style target.
		$targets[] = \T_STYLE;

		// Set the target selectors regex only once.
		$selectors = array_map(
			'preg_quote',
			$this->target_css_selectors,
			array_fill( 0, \count( $this->target_css_selectors ), '`' )
		);
		// Parse the selectors array into the regex string.
		$this->target_css_selectors_regex = sprintf( $this->target_css_selectors_regex, implode( '|', $selectors ) );

		// Add function call targets.
		$parent = parent::register();
		if ( ! empty( $parent ) ) {
			$targets[] = \T_STRING;
		}

		return $targets;
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

		$file_name      = $this->phpcsFile->getFileName();
		$file_extension = substr( strrchr( $file_name, '.' ), 1 );

		if ( 'css' === $file_extension ) {
			if ( \T_STYLE === $this->tokens[ $stackPtr ]['code'] ) {
				return $this->process_css_style( $stackPtr );
			}
		} elseif ( isset( Tokens::$textStringTokens[ $this->tokens[ $stackPtr ]['code'] ] ) ) {
			/*
			 * Set $in_style && $in_target_selector to false if it is the first time
			 * this sniff is run on a file.
			 */
			if ( ! isset( $this->in_style[ $file_name ] ) ) {
				$this->in_style[ $file_name ] = false;
			}
			if ( ! isset( $this->in_target_selector[ $file_name ] ) ) {
				$this->in_target_selector[ $file_name ] = false;
			}

			return $this->process_text_for_style( $stackPtr, $file_name );

		} else {
			return parent::process_token( $stackPtr );
		}
	}

	/**
	 * Process the parameters of a matched function.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param array  $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 * @param array  $parameters      Array with information about the parameters.
	 *
	 * @return void
	 */
	public function process_parameters( $stackPtr, $group_name, $matched_content, $parameters ) {
		$error = false;
		switch ( $matched_content ) {
			case 'show_admin_bar':
				$error = true;
				if ( true === $this->remove_only ) {
					if ( 'true' === $parameters[1]['raw'] ) {
						$error = false;
					}
				}
				break;

			case 'add_filter':
				$filter_name = $this->strip_quotes( $parameters[1]['raw'] );
				if ( 'show_admin_bar' !== $filter_name ) {
					break;
				}

				$error = true;
				if ( true === $this->remove_only && isset( $parameters[2]['raw'] ) ) {
					if ( '__return_true' === $this->strip_quotes( $parameters[2]['raw'] ) ) {
						$error = false;
					}
				}
				break;

			default:
				// Left empty on purpose.
				break;
		}

		if ( true === $error ) {
			$this->phpcsFile->addError( 'Removal of admin bar is prohibited.', $stackPtr, 'RemovalDetected' );
		}
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @param int    $stackPtr  The position of the current token in the stack.
	 * @param string $file_name The file name of the current file being processed.
	 *
	 * @return void
	 */
	public function process_text_for_style( $stackPtr, $file_name ) {
		$content = trim( $this->tokens[ $stackPtr ]['content'] );

		// No need to check an empty string.
		if ( '' === $content ) {
			return;
		}

		// Are we in a <style> tag ?
		if ( true === $this->in_style[ $file_name ] ) {
			if ( false !== strpos( $content, '</style>' ) ) {
				// Make sure we check any content on this line before the closing style tag.
				$this->in_style[ $file_name ] = false;
				$content                      = trim( substr( $content, 0, strpos( $content, '</style>' ) ) );
			}
		} elseif ( false !== strpos( $content, '<style' ) ) {
			// Ok, found a <style> open tag.
			if ( false === strpos( $content, '</style>' ) ) {
				// Make sure we check any content on this line after the opening style tag.
				$this->in_style[ $file_name ] = true;
				$content                      = trim( substr( $content, ( strpos( $content, '<style' ) + 6 ) ) );
			} else {
				// Ok, we have open and close style tag on the same line with possibly content within.
				$start   = ( strpos( $content, '<style' ) + 6 );
				$end     = strpos( $content, '</style>' );
				$content = trim( substr( $content, $start, ( $end - $start ) ) );
				unset( $start, $end );
			}
		} else {
			return;
		}

		// Are we in one of the target selectors ?
		if ( true === $this->in_target_selector[ $file_name ] ) {
			if ( false !== strpos( $content, '}' ) ) {
				// Make sure we check any content on this line before the selector closing brace.
				$this->in_target_selector[ $file_name ] = false;
				$content                                = trim( substr( $content, 0, strpos( $content, '}' ) ) );
			}
		} elseif ( preg_match( $this->target_css_selectors_regex, $content, $matches ) > 0 ) {
			// Ok, found a new target selector.
			$content = '';

			if ( isset( $matches[1] ) && '' !== $matches[1] ) {
				if ( false === strpos( $matches[1], '}' ) ) {
					// Make sure we check any content on this line before the closing brace.
					$this->in_target_selector[ $file_name ] = true;
					$content                                = trim( $matches[1] );
				} else {
					// Ok, we have the selector open and close brace on the same line.
					$content = trim( substr( $matches[1], 0, strpos( $matches[1], '}' ) ) );
				}
			} else {
				$this->in_target_selector[ $file_name ] = true;
			}
		} else {
			return;
		}
		unset( $matches );

		// Now let's do the check for the CSS properties.
		if ( ! empty( $content ) ) {
			foreach ( $this->target_css_properties as $property => $requirements ) {
				if ( false !== strpos( $content, $property ) ) {
					$error = true;

					if ( true === $this->remove_only ) {
						// Check the value of the CSS property.
						if ( preg_match( '`' . preg_quote( $property, '`' ) . '\s*:\s*(.+?)\s*(?:!important)?;`', $content, $matches ) > 0 ) {
							$value = trim( $matches[1] );
							$valid = $this->validate_css_property_value( $value, $requirements['type'], $requirements['value'] );
							if ( true === $valid ) {
								$error = false;
							}
						}
					}

					if ( true === $error ) {
						$this->phpcsFile->addError( 'Hiding of the admin bar is not allowed.', $stackPtr, 'HidingDetected' );
					}
				}
			}
		}
	}

	/**
	 * Processes this test for T_STYLE tokens in CSS files.
	 *
	 * @since WPCS 0.11.0
	 *
	 * @param int $stackPtr  The position of the current token in the stack passed in $tokens.
	 *
	 * @return void
	 */
	protected function process_css_style( $stackPtr ) {
		if ( ! isset( $this->target_css_properties[ $this->tokens[ $stackPtr ]['content'] ] ) ) {
			// Not one of the CSS properties we're interested in.
			return;
		}

		$css_property = $this->target_css_properties[ $this->tokens[ $stackPtr ]['content'] ];

		// Check if the CSS selector matches.
		$opener = $this->phpcsFile->findPrevious( \T_OPEN_CURLY_BRACKET, $stackPtr );
		if ( false !== $opener ) {
			for ( $i = ( $opener - 1 ); $i >= 0; $i-- ) {
				if ( isset( Tokens::$commentTokens[ $this->tokens[ $i ]['code'] ] )
					|| \T_CLOSE_CURLY_BRACKET === $this->tokens[ $i ]['code']
				) {
					break;
				}
			}
			$start    = ( $i + 1 );
			$selector = trim( $this->phpcsFile->getTokensAsString( $start, ( $opener - $start ) ) );
			unset( $i );

			foreach ( $this->target_css_selectors as $target_selector ) {
				if ( false !== strpos( $selector, $target_selector ) ) {
					$error = true;

					if ( true === $this->remove_only ) {
						// Check the value of the CSS property.
						$valuePtr = $this->phpcsFile->findNext( [ \T_COLON, \T_WHITESPACE ], ( $stackPtr + 1 ), null, true );
						$value    = $this->tokens[ $valuePtr ]['content'];
						$valid    = $this->validate_css_property_value( $value, $css_property['type'], $css_property['value'] );
						if ( true === $valid ) {
							$error = false;
						}
					}

					if ( true === $error ) {
						$this->phpcsFile->addError( 'Hiding of the admin bar is not allowed.', $stackPtr, 'HidingDetected' );
					}
				}
			}
		}
	}

	/**
	 * Verify if a CSS property value complies with an expected value.
	 *
	 * {@internal This is a method stub, doing only what is needed for this sniff.
	 * If at some point in the future other sniff would need similar functionality,
	 * this method should be moved to the WordPress_Sniff class and expanded to cover
	 * all types of comparisons.}}
	 *
	 * @since WPCS 0.11.0
	 *
	 * @param mixed  $value         The value of CSS property.
	 * @param string $compare_type  The type of comparison to use for the validation.
	 * @param string $compare_value The value to compare against.
	 *
	 * @return bool True if the property value complies, false otherwise.
	 */
	protected function validate_css_property_value( $value, $compare_type, $compare_value ) {
		switch ( $compare_type ) {
			case '!=':
				return $value !== $compare_value;

			case '>':
				return $value > $compare_value;

			default:
				return false;
		}
	}

}
