<?php declare( strict_types=1 );
/**
 * HTML API: WP_HTML_Unsupported_Exception class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.4.0
 */

/**
 * Core class used by the HTML processor during HTML parsing
 * for indicating that a given operation is unsupported.
 *
 * This class is designed for internal use by the HTML processor.
 *
 * The HTML API aims to operate in compliance with the HTML5
 * specification, but does not implement the full specification.
 * In cases where it lacks support it should not cause breakage
 * or unexpected behavior. In the cases where it recognizes that
 * it cannot proceed, this class is used to abort from any
 * operation and signify that the given HTML cannot be processed.
 *
 * @since 6.4.0
 *
 * @access private
 *
 * @see WP_HTML_Processor
 */
class WP_HTML_Unsupported_Exception extends Exception {
	public $token_name;
	public $token_at;

	public $token;
	public $stack_of_open_elements = array();
	public $active_formatting_elements = array();

	public function __construct( string $message, string $token_name, int $token_at, string $token, array $stack_of_open_elements, array $active_formatting_elements ) {
		parent::__construct( $message );

		$this->token_name = $token_name;
		$this->token_at   = $token_at;
		$this->token      = $token;

		$this->stack_of_open_elements     = $stack_of_open_elements;
		$this->active_formatting_elements = $active_formatting_elements;
	}
}
