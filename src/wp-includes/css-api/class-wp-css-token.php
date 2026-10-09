<?php
/**
 * CSS API: WP_CSS_Token class
 *
 * @package WordPress
 * @subpackage CSS-API
 * @since 7.2.0
 */

/**
 * A token read by WP_CSS_Token_Processor.
 *
 * A token keeps its values after the processor moves to the next token, so
 * callers can collect tokens and read them later. The value is decoded from
 * the CSS source on the first call to get_value().
 *
 * Example:
 *
 *     $processor = WP_CSS_Token_Processor::create( 'font-family: "Exo\20 2"' );
 *     $tokens    = array();
 *     while ( $processor->next_token() ) {
 *         $tokens[] = $processor->get_token();
 *     }
 *     $tokens[3]->get_type();  // 'string-token'
 *     $tokens[3]->get_value(); // 'Exo 2'
 *
 * @since 7.2.0
 *
 * @see WP_CSS_Token_Processor::get_token()
 */
final class WP_CSS_Token {
	/**
	 * CSS source the offsets index.
	 *
	 * @since 7.2.0
	 *
	 * @var string
	 */
	private $css;

	/**
	 * Token type.
	 *
	 * @since 7.2.0
	 *
	 * @var string
	 * @phpstan-var WP_CSS_Token_Processor::TOKEN_*
	 */
	private $type;

	/**
	 * Byte offset at which the token starts.
	 *
	 * @since 7.2.0
	 *
	 * @var int
	 */
	private $start;

	/**
	 * Byte length of the token.
	 *
	 * @since 7.2.0
	 *
	 * @var int
	 */
	private $length;

	/**
	 * Type flag of a hash, number or dimension token.
	 *
	 * @since 7.2.0
	 *
	 * @var string|null
	 * @phpstan-var 'id'|'unrestricted'|'integer'|'number'|null
	 */
	private $flag;

	/**
	 * Decoded unit of a dimension token.
	 *
	 * @since 7.2.0
	 *
	 * @var string|null
	 */
	private $unit;

	/**
	 * Byte offset at which the value of a string or url token starts.
	 *
	 * @since 7.2.0
	 *
	 * @var int|null
	 */
	private $value_start;

	/**
	 * Byte length of the value of a string or url token.
	 *
	 * @since 7.2.0
	 *
	 * @var int|null
	 */
	private $value_length;

	/**
	 * Whether the token ended at its closing delimiter.
	 *
	 * @since 7.2.0
	 *
	 * @var bool
	 */
	private $is_terminated;

	/**
	 * Decoded value, or false before the first call to get_value().
	 *
	 * @since 7.2.0
	 *
	 * @var string|null|false
	 */
	private $value = false;

	/**
	 * Constructor.
	 *
	 * Use WP_CSS_Token_Processor::get_token() to create tokens.
	 *
	 * @since 7.2.0
	 * @access private
	 *
	 * @param string      $css           CSS source the offsets index.
	 * @param string      $type          Token type, one of the WP_CSS_Token_Processor::TOKEN_* constants.
	 * @param int         $start         Byte offset at which the token starts.
	 * @param int         $length        Byte length of the token.
	 * @param string|null $flag          Type flag of a hash, number or dimension token.
	 * @param string|null $unit          Decoded unit of a dimension token.
	 * @param int|null    $value_start   Byte offset at which the value of a string or url token starts.
	 * @param int|null    $value_length  Byte length of the value of a string or url token.
	 * @param bool        $is_terminated Whether the token ended at its closing delimiter.
	 */
	public function __construct(
		string $css,
		string $type,
		int $start,
		int $length,
		?string $flag,
		?string $unit,
		?int $value_start,
		?int $value_length,
		bool $is_terminated
	) {
		$this->css           = $css;
		$this->type          = $type;
		$this->start         = $start;
		$this->length        = $length;
		$this->flag          = $flag;
		$this->unit          = $unit;
		$this->value_start   = $value_start;
		$this->value_length  = $value_length;
		$this->is_terminated = $is_terminated;
	}

	/**
	 * Gets the token type.
	 *
	 * @since 7.2.0
	 *
	 * @return string One of the WP_CSS_Token_Processor::TOKEN_* constants.
	 * @phpstan-return WP_CSS_Token_Processor::TOKEN_*
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Gets the type flag.
	 *
	 * Hash tokens have an "id" or "unrestricted" flag. Number and dimension
	 * tokens have an "integer" or "number" flag. Other tokens have none.
	 *
	 * @since 7.2.0
	 *
	 * @see WP_CSS_Token_Processor::get_token_type_flag()
	 *
	 * @return string|null The type flag, or null.
	 * @phpstan-return 'id'|'unrestricted'|'integer'|'number'|null
	 */
	public function get_flag(): ?string {
		return $this->flag;
	}

	/**
	 * Gets the decoded value.
	 *
	 * The value is decoded on the first call and kept for later calls.
	 *
	 * @since 7.2.0
	 *
	 * @see WP_CSS_Token_Processor::get_token_value()
	 *
	 * @return string|null The decoded value, or null for token types without a value.
	 */
	public function get_value(): ?string {
		if ( false === $this->value ) {
			$this->value = $this->decode_value();
		}

		return $this->value;
	}

	/**
	 * Gets the decoded unit of a dimension token.
	 *
	 * @since 7.2.0
	 *
	 * @return string|null The unit, or null for other token types.
	 */
	public function get_unit(): ?string {
		return $this->unit;
	}

	/**
	 * Gets the byte offset at which the token starts in the CSS source.
	 *
	 * @since 7.2.0
	 *
	 * @return int Byte offset.
	 */
	public function get_start(): int {
		return $this->start;
	}

	/**
	 * Gets the byte length of the token in the CSS source.
	 *
	 * @since 7.2.0
	 *
	 * @return int Byte length.
	 */
	public function get_length(): int {
		return $this->length;
	}

	/**
	 * Reports whether the token ended at its closing delimiter.
	 *
	 * @since 7.2.0
	 *
	 * @see WP_CSS_Token_Processor::is_token_terminated()
	 *
	 * @return bool Whether the token ended at its closing delimiter.
	 */
	public function is_terminated(): bool {
		return $this->is_terminated;
	}

	/**
	 * Decodes the value from the CSS source.
	 *
	 * @since 7.2.0
	 *
	 * @return string|null The decoded value, or null for token types without a value.
	 */
	private function decode_value(): ?string {
		switch ( $this->type ) {
			case WP_CSS_Token_Processor::TOKEN_HASH:
			case WP_CSS_Token_Processor::TOKEN_AT_KEYWORD:
				// The value starts after the `#` or `@`.
				return WP_CSS_Token_Processor::decode_range( $this->css, $this->start + 1, $this->length - 1 );

			case WP_CSS_Token_Processor::TOKEN_FUNCTION:
				// The value ends before the `(`.
				return WP_CSS_Token_Processor::decode_range( $this->css, $this->start, $this->length - 1 );

			case WP_CSS_Token_Processor::TOKEN_IDENT:
			case WP_CSS_Token_Processor::TOKEN_DELIM:
				return WP_CSS_Token_Processor::decode_range( $this->css, $this->start, $this->length );

			case WP_CSS_Token_Processor::TOKEN_STRING:
				return WP_CSS_Token_Processor::decode_range( $this->css, $this->value_start, $this->value_length, true );

			case WP_CSS_Token_Processor::TOKEN_URL:
				return WP_CSS_Token_Processor::decode_range( $this->css, $this->value_start, $this->value_length );

			case WP_CSS_Token_Processor::TOKEN_NUMBER:
				// Numbers are ASCII and have no escapes.
				return substr( $this->css, $this->start, $this->length );

			case WP_CSS_Token_Processor::TOKEN_PERCENTAGE:
				// The value ends before the `%`.
				return substr( $this->css, $this->start, $this->length - 1 );

			case WP_CSS_Token_Processor::TOKEN_DIMENSION:
				// The value ends before the unit.
				return substr( WP_CSS_Token_Processor::decode_range( $this->css, $this->start, $this->length ), 0, -strlen( $this->unit ) );

			default:
				return null;
		}
	}
}
