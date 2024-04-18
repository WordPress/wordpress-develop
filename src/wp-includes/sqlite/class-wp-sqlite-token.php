<?php
/**
 * This file is a port of the Token class from the PHPMyAdmin/sql-parser library.
 *
 * @package wp-sqlite-integration
 * @see https://github.com/phpmyadmin/sql-parser
 */

/**
 * Defines a token along with a set of types and flags and utility functions.
 *
 * An array of tokens will result after parsing the query.
 *
 * A structure representing a lexeme that explicitly indicates its categorization for the purpose of parsing.
 */
class WP_SQLite_Token {

	/**
	 * This type is used when the token is invalid or its type cannot be
	 * determined because of the ambiguous context. Further analysis might be
	 * required to detect its type.
	 */
	const TYPE_NONE = 0;

	/**
	 * SQL specific keywords: SELECT, UPDATE, INSERT, etc.
	 */
	const TYPE_KEYWORD = 1;

	/**
	 * Any type of legal operator.
	 *
	 * Arithmetic operators: +, -, *, /, etc.
	 * Logical operators: ===, <>, !==, etc.
	 * Bitwise operators: &, |, ^, etc.
	 * Assignment operators: =, +=, -=, etc.
	 * SQL specific operators: . (e.g. .. WHERE database.table ..),
	 *                         * (e.g. SELECT * FROM ..)
	 */
	const TYPE_OPERATOR = 2;

	/**
	 * Spaces, tabs, new lines, etc.
	 */
	const TYPE_WHITESPACE = 3;

	/**
	 * Any type of legal comment.
	 *
	 * Bash (#), C (/* *\/) or SQL (--) comments:
	 *
	 *      -- SQL-comment
	 *
	 *      #Bash-like comment
	 *
	 *      /*C-like comment*\/
	 *
	 * or:
	 *
	 *      /*C-like
	 *        comment*\/
	 *
	 * Backslashes were added to respect PHP's comments syntax.
	 */
	const TYPE_COMMENT = 4;

	/**
	 * Boolean values: true or false.
	 */
	const TYPE_BOOL = 5;

	/**
	 * Numbers: 4, 0x8, 15.16, 23e42, etc.
	 */
	const TYPE_NUMBER = 6;

	/**
	 * Literal strings: 'string', "test".
	 * Some of these strings are actually symbols.
	 */
	const TYPE_STRING = 7;

	/**
	 * Database, table names, variables, etc.
	 * For example: ```SELECT `foo`, `bar` FROM `database`.`table`;```.
	 */
	const TYPE_SYMBOL = 8;

	/**
	 * Delimits an unknown string.
	 * For example: ```SELECT * FROM test;```, `test` is a delimiter.
	 */
	const TYPE_DELIMITER = 9;

	/**
	 * Labels in LOOP statement, ITERATE statement etc.
	 * For example (only for begin label):
	 *  begin_label: BEGIN [statement_list] END [end_label]
	 *  begin_label: LOOP [statement_list] END LOOP [end_label]
	 *  begin_label: REPEAT [statement_list] ... END REPEAT [end_label]
	 *  begin_label: WHILE ... DO [statement_list] END WHILE [end_label].
	 */
	const TYPE_LABEL = 10;

	// Flags that describe the tokens in more detail.
	// All keywords must have flag 1 so `Context::isKeyword` method doesn't
	// require strict comparison.
	const FLAG_KEYWORD_RESERVED  = 2;
	const FLAG_KEYWORD_COMPOSED  = 4;
	const FLAG_KEYWORD_DATA_TYPE = 8;
	const FLAG_KEYWORD_KEY       = 16;
	const FLAG_KEYWORD_FUNCTION  = 32;

	// Numbers related flags.
	const FLAG_NUMBER_HEX         = 1;
	const FLAG_NUMBER_FLOAT       = 2;
	const FLAG_NUMBER_APPROXIMATE = 4;
	const FLAG_NUMBER_NEGATIVE    = 8;
	const FLAG_NUMBER_BINARY      = 16;

	// Strings related flags.
	const FLAG_STRING_SINGLE_QUOTES = 1;
	const FLAG_STRING_DOUBLE_QUOTES = 2;

	// Comments related flags.
	const FLAG_COMMENT_BASH      = 1;
	const FLAG_COMMENT_C         = 2;
	const FLAG_COMMENT_SQL       = 4;
	const FLAG_COMMENT_MYSQL_CMD = 8;

	// Operators related flags.
	const FLAG_OPERATOR_ARITHMETIC = 1;
	const FLAG_OPERATOR_LOGICAL    = 2;
	const FLAG_OPERATOR_BITWISE    = 4;
	const FLAG_OPERATOR_ASSIGNMENT = 8;
	const FLAG_OPERATOR_SQL        = 16;

	// Symbols related flags.
	const FLAG_SYMBOL_VARIABLE  = 1;
	const FLAG_SYMBOL_BACKTICK  = 2;
	const FLAG_SYMBOL_USER      = 4;
	const FLAG_SYMBOL_SYSTEM    = 8;
	const FLAG_SYMBOL_PARAMETER = 16;

	/**
	 * The token it its raw string representation.
	 *
	 * @var string
	 */
	public $token;

	/**
	 * The value this token contains (i.e. token after some evaluation).
	 *
	 * @var mixed
	 */
	public $value;

	/**
	 * The keyword value this token contains, always uppercase.
	 *
	 * @var mixed|string|null
	 */
	public $keyword = null;

	/**
	 * The type of this token.
	 *
	 * @var int
	 */
	public $type;

	/**
	 * The flags of this token.
	 *
	 * @var int
	 */
	public $flags;

	/**
	 * The position in the initial string where this token started.
	 *
	 * The position is counted in chars, not bytes, so you should
	 * use mb_* functions to properly handle utf-8 multibyte chars.
	 *
	 * @var int|null
	 */
	public $position;

	/**
	 * Constructor.
	 *
	 * @param string $token The value of the token.
	 * @param int    $type  The type of the token.
	 * @param int    $flags The flags of the token.
	 */
	public function __construct( $token, $type = 0, $flags = 0 ) {
		$this->token = $token;
		$this->type  = $type;
		$this->flags = $flags;
		$this->value = $this->extract();
	}

	/**
	 * Check if the token matches the given parameters.
	 *
	 * @param int|null   $type   The type of the token.
	 * @param int|null   $flags  The flags of the token.
	 * @param array|null $values The values of the token.
	 *
	 * @return bool
	 */
	public function matches( $type = null, $flags = null, $values = null ) {
		if ( null === $type && null === $flags && ( null === $values || array() === $values ) ) {
			return ! $this->is_semantically_void();
		}

		return (
			( null === $type || $this->type === $type )
			&& ( null === $flags || ( $this->flags & $flags ) )
			&& ( null === $values || in_array( strtoupper( $this->value ), $values, true ) )
		);
	}

	/**
	 * Check if the token is semantically void (i.e. whitespace or comment).
	 *
	 * @return bool
	 */
	public function is_semantically_void() {
		return $this->matches( self::TYPE_WHITESPACE ) || $this->matches( self::TYPE_COMMENT );
	}

	/**
	 * Does little processing to the token to extract a value.
	 *
	 * If no processing can be done it will return the initial string.
	 *
	 * @return mixed
	 */
	private function extract() {
		switch ( $this->type ) {
			case self::TYPE_KEYWORD:
				$this->keyword = strtoupper( $this->token );
				if ( ! ( $this->flags & self::FLAG_KEYWORD_RESERVED ) ) {
					/*
					 * Unreserved keywords should stay the way they are
					 * because they might represent field names.
					 */
					return $this->token;
				}

				return $this->keyword;

			case self::TYPE_WHITESPACE:
				return ' ';

			case self::TYPE_BOOL:
				return strtoupper( $this->token ) === 'TRUE';

			case self::TYPE_NUMBER:
				$ret = str_replace( '--', '', $this->token ); // e.g. ---42 === -42.
				if ( $this->flags & self::FLAG_NUMBER_HEX ) {
					$ret = str_replace( array( '-', '+' ), '', $this->token );
					if ( $this->flags & self::FLAG_NUMBER_NEGATIVE ) {
						$ret = -hexdec( $ret );
					} else {
						$ret = hexdec( $ret );
					}
				} elseif ( ( $this->flags & self::FLAG_NUMBER_APPROXIMATE ) || ( $this->flags & self::FLAG_NUMBER_FLOAT ) ) {
					$ret = (float) $ret;
				} elseif ( ! ( $this->flags & self::FLAG_NUMBER_BINARY ) ) {
					$ret = (int) $ret;
				}

				return $ret;

			case self::TYPE_STRING:
				// Trims quotes.
				$str = $this->token;
				$str = mb_substr( $str, 1, -1, 'UTF-8' );

				// Removes surrounding quotes.
				$quote = $this->token[0];
				$str   = str_replace( $quote . $quote, $quote, $str );

				/*
				 * Finally unescapes the string.
				 *
				 * `stripcslashes` replaces escape sequences with their
				 * representation.
				 */
				$str = stripcslashes( $str );

				return $str;

			case self::TYPE_SYMBOL:
				$str = $this->token;
				if ( isset( $str[0] ) && ( '@' === $str[0] ) ) {
					/*
					 * `mb_strlen($str)` must be used instead of `null` because
					 * in PHP 5.3- the `null` parameter isn't handled correctly.
					 */
					$str = mb_substr(
						$str,
						! empty( $str[1] ) && ( '@' === $str[1] ) ? 2 : 1,
						mb_strlen( $str ),
						'UTF-8'
					);
				}

				if ( isset( $str[0] ) && ( ':' === $str[0] ) ) {
					$str = mb_substr( $str, 1, mb_strlen( $str ), 'UTF-8' );
				}

				if ( isset( $str[0] ) && ( ( '`' === $str[0] ) || ( '"' === $str[0] ) || ( '\'' === $str[0] ) ) ) {
					$quote = $str[0];
					$str   = str_replace( $quote . $quote, $quote, $str );
					$str   = mb_substr( $str, 1, -1, 'UTF-8' );
				}

				return $str;
		}

		return $this->token;
	}
}
