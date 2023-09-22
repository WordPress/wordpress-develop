<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\CoreFunctionality;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Check for hardcoded favicons instead of using core implementation.
 *
 * @link  https://make.wordpress.org/themes/handbook/review/required/#core-functionality-and-features
 *
 * @since 0.1.0
 */
class NoFaviconSniff implements Sniff {

	/**
	 * Regex template.
	 *
	 * Will be parsed together with the attribute blacklist in the register() method.
	 *
	 * @var string
	 */
	const REGEX_TEMPLATE = '` (?:%s)`i';

	/**
	 * (Partial) Regex template for recognizing a HTML attribute.
	 *
	 * Will be parsed together with the attribute blacklist in the register() method.
	 *
	 * @var string
	 */
	const REGEX_ATTR_TEMPLATE = '%1$s=[\'"](?:%2$s)[\'"]';

	/**
	 * List of link and meta attributes that are blacklisted.
	 *
	 * @var array
	 */
	protected $attribute_blacklist = [
		'rel' => [
			'icon',
			'shortcut icon',
			'bookmark icon',
			'apple-touch-icon',
			'apple-touch-icon-precomposed',
		],
		'name' => [
			'msapplication-config',
			'msapplication-TileImage',
			'msapplication-square70x70logo',
			'msapplication-square150x150logo',
			'msapplication-wide310x150logo',
			'msapplication-square310x310logo',
		],
	];

	/**
	 * The regex to catch the blacklisted attributes.
	 *
	 * @var string
	 */
	protected $favicon_regex;

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		// Build the regex to be used only once.
		$regex_parts = [];

		foreach ( $this->attribute_blacklist as $key => $values ) {
			$values        = array_map( 'preg_quote', $values, array_fill( 0, count( $values ), '`' ) );
			$values        = implode( '|', $values );
			$regex_parts[] = sprintf( self::REGEX_ATTR_TEMPLATE, preg_quote( $key, '`' ), $values );
		}

		$this->favicon_regex = sprintf( self::REGEX_TEMPLATE, implode( '|', $regex_parts ) );

		return Tokens::$textStringTokens;
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where the
	 *                                               token was found.
	 * @param int                         $stackPtr  The position of the current token
	 *                                               in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {

		$tokens = $phpcsFile->getTokens();
		$token  = $tokens[ $stackPtr ];

		if ( preg_match( $this->favicon_regex, $token['content'] ) > 0 ) {
			$phpcsFile->addError(
				'Code for favicon found. Favicons are handled by the "Site Icon" setting in the customizer since WP version 4.3.',
				$stackPtr,
				'NoFavicon'
			);
		}
	}

}
