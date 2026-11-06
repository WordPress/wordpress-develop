<?php

/**
 * A token, representing a leaf in the parse tree.
 *
 * This class represents a token that is consumed and recognized by WP_Parser.
 * In a parse tree, a token represent a leaf, that is, a node without children.
 * It is a simple generic container for a token ID and value, that can be used
 * as a base class and extended for specific use cases.
 *
 * @access private
 */
class WP_Parser_Token {
	/**
	 * Token ID represented as an integer constant.
	 *
	 * @var int $id
	 */
	public $id;

	/**
	 * Byte offset in the input where the token begins.
	 *
	 * @var int
	 */
	public $start;

	/**
	 * Byte length of the token in the input.
	 *
	 * @var int
	 */
	public $length;

	/**
	 * Input bytes from which the token was parsed.
	 *
	 * @var string
	 */
	protected $input;

	/**
	 * Constructor.
	 *
	 * @param int    $id      Token type.
	 * @param int    $start   Byte offset in the input where the token begins.
	 * @param int    $length  Byte length of the token in the input.
	 * @param string $input   Input bytes from which the token was parsed.
	 */
	public function __construct(
		int $id,
		int $start,
		int $length,
		string $input
	) {
		$this->id     = $id;
		$this->start  = $start;
		$this->length = $length;
		$this->input  = $input;
	}

	/**
	 * Get the raw bytes of the token from the input.
	 *
	 * @return string The token bytes.
	 */
	public function get_bytes(): string {
		return substr( $this->input, $this->start, $this->length );
	}

	/**
	 * Get the real unquoted value of the token.
	 *
	 * @return string The token value.
	 */
	public function get_value(): string {
		return $this->get_bytes();
	}
}
