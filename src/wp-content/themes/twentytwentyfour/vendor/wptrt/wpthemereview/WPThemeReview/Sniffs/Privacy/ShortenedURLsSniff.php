<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\Privacy;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Detect the use of shortened URLs.
 *
 * Detection is based on a list of banned URL shortener services.
 *
 * @link https://make.wordpress.org/themes/handbook/review/required/#privacy
 *
 * @since 0.2.0
 */
class ShortenedURLsSniff implements Sniff {

	/**
	 * Error message template.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	const ERROR_MSG = 'Shortened URLs are not allowed in the theme. Found: "%s".';

	/**
	 * Regex template.
	 *
	 * Will be parsed together with the url_shorteners blacklist in the register() method.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	const REGEX_TEMPLATE = '`(?:%s)/[^\s\'"]+`i';

	/**
	 * Supported Tokenizers.
	 *
	 * @since 0.2.0
	 *
	 * @var array
	 */
	public $supportedTokenizers = [
		'PHP',
		'CSS',
		'JS',
	];

	/**
	 * Regex pattern.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	private $regex = '';

	/**
	 * List of url shorteners.
	 *
	 * @since 0.2.0
	 *
	 * @var array
	 */
	protected $url_shorteners = [
		'bit.do',
		'bit.ly',
		'df.ly',
		'goo.gl',
		'is.gd',
		'lc.chat',
		'ow.ly',
		'polr.me',
		's2r.co',
		'soo.gd',
		'tiny.cc',
		'tinyurl.com',
	];

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @since 0.2.0
	 *
	 * @return array
	 */
	public function register() {

		// Create the regex only once.
		$urls = array_map(
			'preg_quote',
			$this->url_shorteners,
			array_fill( 0, count( $this->url_shorteners ), '`' )
		);

		$this->regex = sprintf(
			self::REGEX_TEMPLATE,
			implode( '|', $urls )
		);

		return Tokens::$textStringTokens + [
			T_COMMENT,
			T_DOC_COMMENT_STRING,
			T_DOC_COMMENT,
		];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @since 0.2.0
	 *
	 * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where the
	 *                                               token was found.
	 * @param int                         $stackPtr  The position of the current token
	 *                                               in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens  = $phpcsFile->getTokens();
		$content = $tokens[ $stackPtr ]['content'];

		if ( stripos( $content, '.' ) === false ) {
			return;
		}

		if ( preg_match_all( $this->regex, $content, $matches ) > 0 ) {
			foreach ( $matches[0] as $matched_url ) {
				$phpcsFile->addError(
					self::ERROR_MSG,
					$stackPtr,
					'Found',
					[ $matched_url ]
				);
			}
		}
	}
}
