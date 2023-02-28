<?php
/**
 * Class WP_SQLite_Query_Rewriter
 *
 * @package wp-sqlite-integration
 */

/**
 * The query rewriter class.
 */
class WP_SQLite_Query_Rewriter {

	/**
	 * An array of input token objects.
	 *
	 * @var WP_SQLite_Token[]
	 */
	public $input_tokens = array();

	/**
	 * An array of output token objects.
	 *
	 * @var WP_SQLite_Token[]
	 */
	public $output_tokens = array();

	/**
	 * The current index.
	 *
	 * @var int
	 */
	public $index = -1;

	/**
	 * The maximum index.
	 *
	 * @var int
	 */
	public $max = -1;

	/**
	 * The call stack.
	 *
	 * @var array
	 */
	public $call_stack = array();

	/**
	 * The current depth.
	 *
	 * @var int
	 */
	public $depth = 0;

	/**
	 * The current token.
	 *
	 * @var WP_SQLite_Token
	 */
	private $token;

	/**
	 * The last function call.
	 *
	 * @var WP_SQLite_Token
	 */
	private $last_function_call;

	/**
	 * Constructor.
	 *
	 * @param WP_SQLite_Token[] $input_tokens Array of token objects.
	 */
	public function __construct( $input_tokens ) {
		$this->input_tokens = $input_tokens;
		$this->max          = count( $input_tokens );
	}

	/**
	 * Returns the updated query.
	 *
	 * @return string
	 */
	public function get_updated_query() {
		$query = '';
		foreach ( $this->output_tokens as $token ) {
			$query .= $token->token;
		}
		return $query;
	}

	/**
	 * Add a token to the output.
	 *
	 * @param WP_SQLite_Token $token Token object.
	 */
	public function add( $token ) {
		if ( $token ) {
			$this->output_tokens[] = $token;
		}
	}

	/**
	 * Add multiple tokens to the output.
	 *
	 * @param WP_SQLite_Token[] $tokens Array of token objects.
	 */
	public function add_many( $tokens ) {
		$this->output_tokens = array_merge( $this->output_tokens, $tokens );
	}

	/**
	 * Replaces all tokens.
	 *
	 * @param WP_SQLite_Token[] $tokens Array of token objects.
	 */
	public function replace_all( $tokens ) {
		$this->output_tokens = $tokens;
	}

	/**
	 * Peek at the next tokens and return one that matches the given criteria.
	 *
	 * @param array $query Optional. Search query.
	 *                     [
	 *                         'type'   => string|null, // Token type.
	 *                         'flags'  => int|null,    // Token flags.
	 *                         'values' => string|null, // Token values.
	 *                     ].
	 *
	 * @return WP_SQLite_Token
	 */
	public function peek( $query = array() ) {
		$type   = isset( $query['type'] ) ? $query['type'] : null;
		$flags  = isset( $query['flags'] ) ? $query['flags'] : null;
		$values = isset( $query['value'] )
			? ( is_array( $query['value'] ) ? $query['value'] : array( $query['value'] ) )
			: null;

		$i = $this->index;
		while ( ++$i < $this->max ) {
			if ( $this->input_tokens[ $i ]->matches( $type, $flags, $values ) ) {
				return $this->input_tokens[ $i ];
			}
		}
	}

	/**
	 * Move forward and return the next tokens that match the given criteria.
	 *
	 * @param int $nth The nth token to return.
	 *
	 * @return WP_SQLite_Token
	 */
	public function peek_nth( $nth ) {
		$found = 0;
		for ( $i = $this->index + 1;$i < $this->max;$i++ ) {
			$token = $this->input_tokens[ $i ];
			if ( ! $token->is_semantically_void() ) {
				++$found;
			}
			if ( $found === $nth ) {
				return $this->input_tokens[ $i ];
			}
		}
	}

	/**
	 * Consume all the tokens.
	 *
	 * @param array $query Search query.
	 *
	 * @return void
	 */
	public function consume_all( $query = array() ) {
		while ( $this->consume( $query ) ) {
			// Do nothing.
		}
	}

	/**
	 * Consume the next tokens and return one that matches the given criteria.
	 *
	 * @param array $query Search query.
	 *                     [
	 *                         'type'   => null, // Optional. Token type.
	 *                         'flags'  => null, // Optional. Token flags.
	 *                         'values' => null, // Optional. Token values.
	 *                     ].
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function consume( $query = array() ) {
		$tokens              = $this->move_forward( $query );
		$this->output_tokens = array_merge( $this->output_tokens, $tokens );
		return $this->token;
	}

	/**
	 * Drop the last consumed token and return it.
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function drop_last() {
		return array_pop( $this->output_tokens );
	}

	/**
	 * Skip over the next tokens and return one that matches the given criteria.
	 *
	 * @param array $query Search query.
	 *                     [
	 *                         'type'   => null, // Optional. Token type.
	 *                         'flags'  => null, // Optional. Token flags.
	 *                         'values' => null, // Optional. Token values.
	 *                     ].
	 *
	 * @return WP_SQLite_Token|null
	 */
	public function skip( $query = array() ) {
		$this->skip_and_return_all( $query );
		return $this->token;
	}

	/**
	 * Skip over the next tokens until one matches the given criteria,
	 * and return all the skipped tokens.
	 *
	 * @param array $query Search query.
	 *                     [
	 *                         'type'   => null, // Optional. Token type.
	 *                         'flags'  => null, // Optional. Token flags.
	 *                         'values' => null, // Optional. Token values.
	 *                     ].
	 *
	 * @return WP_SQLite_Token[]
	 */
	public function skip_and_return_all( $query = array() ) {
		$tokens = $this->move_forward( $query );

		/*
		 * When skipping over whitespaces, make sure to consume
		 * at least one to avoid SQL syntax errors.
		 */
		foreach ( $tokens as $token ) {
			if ( $token->matches( WP_SQLite_Token::TYPE_WHITESPACE ) ) {
				$this->add( $token );
				break;
			}
		}

		return $tokens;
	}

	/**
	 * Returns the next tokens that match the given criteria.
	 *
	 * @param array $query Search query.
	 *                     [
	 *                         'type'   => string|null, // Optional. Token type.
	 *                         'flags'  => int|null,    // Optional. Token flags.
	 *                         'values' => string|null, // Optional. Token values.
	 *                     ].
	 *
	 * @return array
	 */
	private function move_forward( $query = array() ) {
		$type   = isset( $query['type'] ) ? $query['type'] : null;
		$flags  = isset( $query['flags'] ) ? $query['flags'] : null;
		$values = isset( $query['value'] )
			? ( is_array( $query['value'] ) ? $query['value'] : array( $query['value'] ) )
			: null;
		$depth  = isset( $query['depth'] ) ? $query['depth'] : null;

		$buffered = array();
		while ( true ) {
			if ( ++$this->index >= $this->max ) {
				$this->token      = null;
				$this->call_stack = array();
				break;
			}
			$this->token = $this->input_tokens[ $this->index ];
			$this->update_call_stack();
			$buffered[] = $this->token;
			if (
				( null === $depth || $this->depth === $depth )
				&& $this->token->matches( $type, $flags, $values )
			) {
				break;
			}
		}

		return $buffered;
	}

	/**
	 * Returns the last call stack element.
	 *
	 * @return array|null
	 */
	public function last_call_stack_element() {
		return count( $this->call_stack ) ? $this->call_stack[ count( $this->call_stack ) - 1 ] : null;
	}

	/**
	 * Updates the call stack.
	 *
	 * @return void
	 */
	private function update_call_stack() {
		if ( $this->token->flags & WP_SQLite_Token::FLAG_KEYWORD_FUNCTION ) {
			$this->last_function_call = $this->token->value;
		}
		if ( WP_SQLite_Token::TYPE_OPERATOR === $this->token->type ) {
			switch ( $this->token->value ) {
				case '(':
					if ( $this->last_function_call ) {
						array_push(
							$this->call_stack,
							array(
								'function' => $this->last_function_call,
								'depth'    => $this->depth,
							)
						);
						$this->last_function_call = null;
					}
					++$this->depth;
					break;

				case ')':
					--$this->depth;
					$call_parent = $this->last_call_stack_element();
					if (
						$call_parent &&
						$call_parent['depth'] === $this->depth
					) {
						array_pop( $this->call_stack );
					}
					break;
			}
		}
	}
}
