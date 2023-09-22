<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\NamingConventions;

use WordPressCS\WordPress\AbstractFunctionParameterSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Validates post type names.
 *
 * Checks the post type slug for invalid characters, long function names
 * and reserved names.
 *
 * @link https://developer.wordpress.org/reference/functions/register_post_type/
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since 2.2.0
 */
class ValidPostTypeSlugSniff extends AbstractFunctionParameterSniff {

	/**
	 * Max length of a post type name is limited by the SQL field.
	 *
	 * @since 2.2.0
	 *
	 * @var int
	 */
	const POST_TYPE_MAX_LENGTH = 20;

	/**
	 * Regex that whitelists characters that can be used as the post type slug.
	 *
	 * @link https://developer.wordpress.org/reference/functions/register_post_type/
	 * @since 2.2.0
	 *
	 * @var string
	 */
	const POST_TYPE_CHARACTER_WHITELIST = '/^[a-z0-9_-]+$/';

	/**
	 * Array of functions that must be checked.
	 *
	 * @since 2.2.0
	 *
	 * @var array List of function names as keys. Value irrelevant.
	 */
	protected $target_functions = array(
		'register_post_type' => true,
	);

	/**
	 * Array of reserved post type names which can not be used by themes and plugins.
	 *
	 * @since 2.2.0
	 *
	 * @var array
	 */
	protected $reserved_names = array(
		'post'                => true,
		'page'                => true,
		'attachment'          => true,
		'revision'            => true,
		'nav_menu_item'       => true,
		'custom_css'          => true,
		'customize_changeset' => true,
		'oembed_cache'        => true,
		'user_request'        => true,
		'wp_block'            => true,
		'action'              => true,
		'author'              => true,
		'order'               => true,
		'theme'               => true,
	);

	/**
	 * All valid tokens for in the first parameter of register_post_type().
	 *
	 * Set in `register()`.
	 *
	 * @since 2.2.0
	 *
	 * @var string
	 */
	private $valid_tokens = array();

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @since 2.2.0
	 *
	 * @return array
	 */
	public function register() {
		$this->valid_tokens = Tokens::$textStringTokens + Tokens::$heredocTokens + Tokens::$emptyTokens;
		return parent::register();
	}

	/**
	 * Process the parameter of a matched function.
	 *
	 * Errors on invalid post type names when reserved keywords are used,
	 * the post type is too long, or contains invalid characters.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param array  $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 * @param array  $parameters      Array with information about the parameters.
	 *
	 * @return void
	 */
	public function process_parameters( $stackPtr, $group_name, $matched_content, $parameters ) {

		$string_pos         = $this->phpcsFile->findNext( Tokens::$textStringTokens, $parameters[1]['start'], ( $parameters[1]['end'] + 1 ) );
		$has_invalid_tokens = $this->phpcsFile->findNext( $this->valid_tokens, $parameters[1]['start'], ( $parameters[1]['end'] + 1 ), true );
		if ( false !== $has_invalid_tokens || false === $string_pos ) {
			// Check for non string based slug parameter (we cannot determine if this is valid).
			$this->phpcsFile->addWarning(
				'The post type slug is not a string literal. It is not possible to automatically determine the validity of this slug. Found: %s.',
				$stackPtr,
				'NotStringLiteral',
				array(
					$parameters[1]['raw'],
				),
				3
			);
			return;
		}

		$post_type = $this->strip_quotes( $this->tokens[ $string_pos ]['content'] );

		if ( strlen( $post_type ) === 0 ) {
			// Error for using empty slug.
			$this->phpcsFile->addError(
				'register_post_type() called without a post type slug. The slug must be a non-empty string.',
				$parameters[1]['start'],
				'Empty'
			);
			return;
		}

		$data = array(
			$this->tokens[ $string_pos ]['content'],
		);

		// Warn for dynamic parts in the slug parameter.
		if ( 'T_DOUBLE_QUOTED_STRING' === $this->tokens[ $string_pos ]['type'] || ( 'T_HEREDOC' === $this->tokens[ $string_pos ]['type'] && strpos( $this->tokens[ $string_pos ]['content'], '$' ) !== false ) ) {
			$this->phpcsFile->addWarning(
				'The post type slug may, or may not, get too long with dynamic contents and could contain invalid characters. Found: %s.',
				$string_pos,
				'PartiallyDynamic',
				$data
			);
			$post_type = $this->strip_interpolated_variables( $post_type );
		}

		if ( preg_match( self::POST_TYPE_CHARACTER_WHITELIST, $post_type ) === 0 ) {
			// Error for invalid characters.
			$this->phpcsFile->addError(
				'register_post_type() called with invalid post type %s. Post type contains invalid characters. Only lowercase alphanumeric characters, dashes, and underscores are allowed.',
				$string_pos,
				'InvalidCharacters',
				$data
			);
		}

		if ( isset( $this->reserved_names[ $post_type ] ) ) {
			// Error for using reserved slug names.
			$this->phpcsFile->addError(
				'register_post_type() called with reserved post type %s. Reserved post types should not be used as they interfere with the functioning of WordPress itself.',
				$string_pos,
				'Reserved',
				$data
			);
		} elseif ( stripos( $post_type, 'wp_' ) === 0 ) {
			// Error for using reserved slug prefix.
			$this->phpcsFile->addError(
				'The post type passed to register_post_type() uses a prefix reserved for WordPress itself. Found: %s.',
				$string_pos,
				'ReservedPrefix',
				$data
			);
		}

		// Error for slugs that are too long.
		if ( strlen( $post_type ) > self::POST_TYPE_MAX_LENGTH ) {
			$this->phpcsFile->addError(
				'A post type slug must not exceed %d characters. Found: %s (%d characters).',
				$string_pos,
				'TooLong',
				array(
					self::POST_TYPE_MAX_LENGTH,
					$this->tokens[ $string_pos ]['content'],
					strlen( $post_type ),
				)
			);
		}
	}
}
