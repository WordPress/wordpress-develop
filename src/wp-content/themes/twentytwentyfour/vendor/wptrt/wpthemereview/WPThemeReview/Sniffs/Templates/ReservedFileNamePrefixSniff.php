<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\Templates;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Check if the template file is using a prefix which would cause WP to interpret it as a specialised template
 * meant to apply to only one page on the site.
 *
 * You can check the documentation of each of the functions used in determining the template hierarchy.
 *
 * @link https://developer.wordpress.org/reference/functions/get_category_template
 * @link https://developer.wordpress.org/reference/functions/get_author_template
 * @link https://developer.wordpress.org/reference/functions/get_page_template
 * @link https://developer.wordpress.org/reference/functions/get_tag_template
 *
 * Or you can check the in depth documentation about the template hierarchy.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy
 *
 * @since 0.2.0
 */
class ReservedFileNamePrefixSniff implements Sniff {

	/**
	 * Error message template.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	const ERROR_MSG = 'Template files should not be slug specific. The file name used for this template will be interpreted by WP as %1$s-%2$s and only applied when a %1$s with the slug "%2$s" is loaded.';

	/**
	 * Found prefix in a file.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * File slug.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * List of reserved template file names.
	 *
	 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
	 * @link https://developer.wordpress.org/themes/template-files-section/partial-and-miscellaneous-template-files/#content-slug-php
	 * @link https://wphierarchy.com/
	 * @link https://en.wikipedia.org/wiki/Media_type#Naming
	 *
	 * @since 0.2.0
	 *
	 * @var array
	 */
	protected $reserved_file_name_prefixes = [
		'author'   => true,
		'category' => true,
		'page'     => true,
		'tag'      => true,
	];

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @since 0.2.0
	 *
	 * @return array
	 */
	public function register() {
		return [
			\T_OPEN_TAG,
			\T_OPEN_TAG_WITH_ECHO,
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
	 * @return int StackPtr to the end of the file, this sniff needs to only
	 *             check each file once.
	 */
	public function process( File $phpcsFile, $stackPtr ) {

		// Usage of `strip_quotes` is to ensure `stdin_path` passed by IDEs does not include quotes.
		$file = $this->strip_quotes( $phpcsFile->getFileName() );

		$fileName = basename( $file );
		$fileName = str_replace( [ '.inc', '.php' ], '', $fileName );

		// Check if the current file has a prefix in the reserved list.
		if ( ! $this->is_reserved_template_name_used( $fileName ) ) {
			return ( $phpcsFile->numTokens + 1 );
		}

		$phpcsFile->addError(
			self::ERROR_MSG,
			0,
			'ReservedTemplatePrefixFound',
			[ $this->prefix, $this->slug ]
		);

		return ( $phpcsFile->numTokens + 1 );
	}

	/**
	 * Checks if the given file name starts with one of the reserved prefixes.
	 *
	 * @since 0.2.0
	 *
	 * @param  string $file File name to check.
	 * @return boolean
	 */
	private function is_reserved_template_name_used( $file ) {
		$file_parts = explode( '-', $file, 2 );

		if ( empty( $file_parts[0] ) || empty( $file_parts[1] ) ) {
			// No dash or dash at start or end of filename, i.e. `-something.php`.
			return false;
		}

		if ( isset( $this->reserved_file_name_prefixes[ strtolower( $file_parts[0] ) ] ) ) {
			$this->prefix = $file_parts[0];
			$this->slug   = $file_parts[1];
			return true;
		}

		return false;
	}

	/**
	 * Strip quotes surrounding an arbitrary string.
	 *
	 * Intended for use with the contents of a T_CONSTANT_ENCAPSED_STRING / T_DOUBLE_QUOTED_STRING.
	 *
	 * Copied from the WordPressCS\WordPress\Sniff abstract class.
	 *
	 * @since 0.2.0
	 *
	 * @param string $string The raw string.
	 * @return string String without quotes around it.
	 */
	private function strip_quotes( $string ) {
		return preg_replace( '`^([\'"])(.*)\1$`Ds', '$2', $string );
	}
}
