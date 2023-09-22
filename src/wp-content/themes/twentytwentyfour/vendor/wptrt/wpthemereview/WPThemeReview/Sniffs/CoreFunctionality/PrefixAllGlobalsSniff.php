<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\CoreFunctionality;

use WordPressCS\WordPress\Sniffs\NamingConventions\PrefixAllGlobalsSniff as WPCSPrefixAllGlobalsSniff;

/**
 * Verify that everything defined in the global namespace is prefixed with a theme specific prefix.
 *
 * This sniff extends the upstream WPCS PrefixAllGlobalsSniff. The differences are:
 * - For non-prefixed global variables, an error will only be thrown when the variable
 *   is created outside of a theme template file.
 *
 * @link https://github.com/WPTRT/WPThemeReview/issues/205
 * @link https://github.com/WPTRT/WPThemeReview/issues/201
 * @link https://github.com/WPTRT/WPThemeReview/issues/200
 *
 * The sniff does not currently allow for mimetype sublevel only file names,
 * such as `plain.php`.
 *
 * @since 0.2.0
 */
class PrefixAllGlobalsSniff extends WPCSPrefixAllGlobalsSniff {

	/**
	 * Regex to recognize more complex typical theme template file names.
	 *
	 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
	 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#custom-taxonomies
	 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#custom-post-types
	 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#embeds
	 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#attachment
	 * @link https://developer.wordpress.org/themes/template-files-section/partial-and-miscellaneous-template-files/#content-slug-php
	 * @link https://wphierarchy.com/
	 * @link https://en.wikipedia.org/wiki/Media_type#Naming
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	const COMPLEX_THEME_TEMPLATE_NAME_REGEX = '`
		^                    # Anchor to the beginning of the string.
		(?:
							 # Template file prefixes with subtype.
			(?:archive|author|category|content|embed|page|single|tag|taxonomy)
			-[^\.]+          # These need to be followed by a dash and some chars.
		|
			(?:application|audio|example|font|image|message|model|multipart|text|video) #Top-level mime-types
			(?:_[^\.]+)?     # Optionally followed by an underscore and a sub-type.
		)\.php$              # End in .php and anchor to the end of the string.
	`Dx';

	/**
	 * The list of allowed folders to check the file path against.
	 *
	 * The WPThemereview standards contains a base set for this property in the ruleset.xml.
	 * This array can be extended in a custom ruleset.
	 *
	 * @since 0.2.0
	 *
	 * @var array
	 */
	public $allowed_folders = [];

	/**
	 * List of plain template file names.
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
	protected $simple_theme_template_file_names = [
		// Plain primary template file names.
		'404.php'         => true,
		'archive.php'     => true,
		'home.php'        => true,
		'index.php'       => true,
		'page.php'        => true,
		'search.php'      => true,
		'single.php'      => true,
		'singular.php'    => true,

		// Plain secondary template file names.
		'attachment.php'  => true,
		'author.php'      => true,
		'category.php'    => true,
		'date.php'        => true,
		'embed.php'       => true,
		'front-page.php'  => true,
		'single-post.php' => true,
		'tag.php'         => true,
		'taxonomy.php'    => true,

		// Plain partial and miscellaneous template file names.
		'comments.php'    => true,
		'footer.php'      => true,
		'header.php'      => true,
		'sidebar.php'     => true,

		// Top-leve mime types.
		'application.php' => true,
		'audio.php'       => true,
		'example.php'     => true,
		'font.php'        => true,
		'image.php'       => true,
		'message.php'     => true,
		'model.php'       => true,
		'multipart.php'   => true,
		'text.php'        => true,
		'video.php'       => true,
	];

	/**
	 * Check that defined global variables are prefixed.
	 *
	 * This overloads the parent method to allow for non-prefixed variables to be declared
	 * in template files as those are included from within a function and therefore would be
	 * local to that function.
	 *
	 * @since 0.2.0
	 * @since 0.2.1  Added $in_list parameter as introduced in WPCS 2.2.0.
	 *
	 * @param int  $stackPtr The position of the current token in the stack.
	 * @param bool $in_list  Whether or not this is a variable in a list assignment.
	 *                       Defaults to false.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	protected function process_variable_assignment( $stackPtr, $in_list = false ) {

		// Usage of `strip_quotes` is to ensure `stdin_path` passed by IDEs does not include quotes.
		$file = $this->strip_quotes( $this->phpcsFile->getFileName() );

		$fileName = basename( $file );

		if ( \defined( '\PHP_CODESNIFFER_IN_TESTS' ) ) {
			$fileName = str_replace( '.inc', '.php', $fileName );
		}

		// Don't process in case of a template file or folder.
		if ( isset( $this->simple_theme_template_file_names[ $fileName ] ) === true ) {
			return;
		}

		if ( preg_match( self::COMPLEX_THEME_TEMPLATE_NAME_REGEX, $fileName ) === 1 ) {
			return;
		}

		if ( $this->is_from_allowed_folder( $file ) ) {
			return;
		}

		// Not a typical template file name, defer to the prefix checking in the parent sniff.
		return parent::process_variable_assignment( $stackPtr, $in_list );
	}

	/**
	 * Checks if the given file path is located in the $allowed_folders array.
	 *
	 * @since 0.2.0
	 *
	 * @param  string $path Full path of the sniffed file.
	 * @return boolean
	 */
	private function is_from_allowed_folder( $path ) {
		if ( empty( $this->allowed_folders ) || ! is_array( $this->allowed_folders ) ) {
			return false;
		}

		foreach ( $this->allowed_folders as $folder ) {
			if ( strrpos( $path, DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
