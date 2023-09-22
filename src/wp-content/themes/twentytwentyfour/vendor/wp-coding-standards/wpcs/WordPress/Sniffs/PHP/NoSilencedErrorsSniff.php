<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\PHP;

use WordPressCS\WordPress\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Discourage the use of the PHP error silencing operator.
 *
 * This sniff allows the error operator to be used with a select list
 * of whitelisted functions, as no amount of error checking can prevent
 * PHP from throwing errors when those functions are used.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   1.1.0
 */
class NoSilencedErrorsSniff extends Sniff {

	/**
	 * Number of tokens to display in the error message to show
	 * the error silencing context.
	 *
	 * @since 1.1.0
	 *
	 * @var int
	 */
	public $context_length = 6;

	/**
	 * Whether or not the `$function_whitelist` should be used.
	 *
	 * Defaults to true.
	 *
	 * This property only affects whether the standard function whitelist is
	 * used. The custom whitelist, if set, will always be respected.
	 *
	 * @since 1.1.0
	 *
	 * @var bool
	 */
	public $use_default_whitelist = true;

	/**
	 * User defined whitelist.
	 *
	 * Allows users to pass a list of additional functions to whitelist
	 * from their custom ruleset.
	 *
	 * @since 1.1.0
	 *
	 * @var array
	 */
	public $custom_whitelist = array();

	/**
	 * PHP native function whitelist.
	 *
	 * Errors caused by calls to any of these native PHP functions
	 * are allowed to be silenced as file system permissions and such
	 * can cause E_WARNINGs to be thrown which cannot be prevented via
	 * error checking.
	 *
	 * Note: only calls to global functions - in contrast to class methods -
	 * are taken into account.
	 *
	 * Only functions for which the PHP manual annotates that an
	 * error will be thrown on failure are accepted into this list.
	 *
	 * @since 1.1.0
	 *
	 * @var array <string function name> => <bool true>
	 */
	protected $function_whitelist = array(
		// Directory extension.
		'chdir'                 => true,
		'opendir'               => true,
		'scandir'               => true,

		// File extension.
		'file_exists'           => true,
		'file_get_contents'     => true,
		'file'                  => true,
		'fileatime'             => true,
		'filectime'             => true,
		'filegroup'             => true,
		'fileinode'             => true,
		'filemtime'             => true,
		'fileowner'             => true,
		'fileperms'             => true,
		'filesize'              => true,
		'filetype'              => true,
		'fopen'                 => true,
		'is_dir'                => true,
		'is_executable'         => true,
		'is_file'               => true,
		'is_link'               => true,
		'is_readable'           => true,
		'is_writable'           => true,
		'is_writeable'          => true,
		'lstat'                 => true,
		'mkdir'                 => true,
		'move_uploaded_file'    => true,
		'readfile'              => true,
		'readlink'              => true,
		'rename'                => true,
		'rmdir'                 => true,
		'stat'                  => true,
		'unlink'                => true,

		// FTP extension.
		'ftp_chdir'             => true,
		'ftp_login'             => true,
		'ftp_rename'            => true,

		// Stream extension.
		'stream_select'         => true,
		'stream_set_chunk_size' => true,

		// Zlib extension.
		'deflate_add'           => true,
		'deflate_init'          => true,
		'inflate_add'           => true,
		'inflate_init'          => true,
		'readgzfile'            => true,

		// Miscellaneous other functions.
		'imagecreatefromstring' => true,
		'parse_url'             => true, // Pre-PHP 5.3.3 an E_WARNING was thrown when URL parsing failed.
		'unserialize'           => true,
	);

	/**
	 * Tokens which are regarded as empty for the purpose of determining
	 * the name of the called function.
	 *
	 * This property is set from within the register() method.
	 *
	 * @since 1.1.0
	 *
	 * @var array
	 */
	private $empty_tokens = array();

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public function register() {
		$this->empty_tokens                    = Tokens::$emptyTokens;
		$this->empty_tokens[ \T_NS_SEPARATOR ] = \T_NS_SEPARATOR;
		$this->empty_tokens[ \T_BITWISE_AND ]  = \T_BITWISE_AND;

		return array(
			\T_ASPERAND,
		);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @since 1.1.0
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 */
	public function process_token( $stackPtr ) {
		// Handle the user-defined custom function whitelist.
		$this->custom_whitelist = $this->merge_custom_array( $this->custom_whitelist, array(), false );
		$this->custom_whitelist = array_map( 'strtolower', $this->custom_whitelist );

		/*
		 * Check if the error silencing is done for one of the whitelisted functions.
		 *
		 * @internal The function call name determination is done even when there is no whitelist active
		 * to allow the metrics to be more informative.
		 */
		$next_non_empty = $this->phpcsFile->findNext( $this->empty_tokens, ( $stackPtr + 1 ), null, true, null, true );
		if ( false !== $next_non_empty && \T_STRING === $this->tokens[ $next_non_empty ]['code'] ) {
			$has_parenthesis = $this->phpcsFile->findNext( Tokens::$emptyTokens, ( $next_non_empty + 1 ), null, true, null, true );
			if ( false !== $has_parenthesis && \T_OPEN_PARENTHESIS === $this->tokens[ $has_parenthesis ]['code'] ) {
				$function_name = strtolower( $this->tokens[ $next_non_empty ]['content'] );
				if ( ( true === $this->use_default_whitelist
					&& isset( $this->function_whitelist[ $function_name ] ) === true )
					|| ( ! empty( $this->custom_whitelist )
					&& in_array( $function_name, $this->custom_whitelist, true ) === true )
				) {
					$this->phpcsFile->recordMetric( $stackPtr, 'Error silencing', 'whitelisted function call: ' . $function_name );
					return;
				}
			}
		}

		$this->context_length = (int) $this->context_length;
		$context_length       = $this->context_length;
		if ( $this->context_length <= 0 ) {
			$context_length = 2;
		}

		// Prepare the "Found" string to display.
		$end_of_statement = $this->phpcsFile->findEndOfStatement( $stackPtr, \T_COMMA );
		if ( ( $end_of_statement - $stackPtr ) < $context_length ) {
			$context_length = ( $end_of_statement - $stackPtr );
		}
		$found = $this->phpcsFile->getTokensAsString( $stackPtr, $context_length );
		$found = str_replace( array( "\t", "\n", "\r" ), ' ', $found ) . '...';

		$error_msg = 'Silencing errors is strongly discouraged. Use proper error checking instead.';
		$data      = array();
		if ( $this->context_length > 0 ) {
			$error_msg .= ' Found: %s';
			$data[]     = $found;
		}

		$this->phpcsFile->addWarning(
			$error_msg,
			$stackPtr,
			'Discouraged',
			$data
		);

		if ( isset( $function_name ) ) {
			$this->phpcsFile->recordMetric( $stackPtr, 'Error silencing', '@' . $function_name );
		} else {
			$this->phpcsFile->recordMetric( $stackPtr, 'Error silencing', $found );
		}
	}

}
