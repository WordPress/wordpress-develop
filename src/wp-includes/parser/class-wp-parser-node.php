<?php

/**
 * A node in parse tree.
 *
 * This class represents a node in the parse tree that is produced by WP_Parser.
 * A node corresponds to the related grammar rule that was matched by the parser.
 * Each node can contain children, consisting of other nodes and grammar tokens.
 * In this way, a parser node constitutes a recursive structure that represents
 * a parse (sub)tree at each level of the full grammar tree.
 *
 * @access private
 */
class WP_Parser_Node {
	/**
	 * @TODO: Review and document these properties and their visibility.
	 */
	public $rule_id;
	public $rule_name;
	protected $children = array();

	public function __construct( $rule_id, $rule_name ) {
		$this->rule_id   = $rule_id;
		$this->rule_name = $rule_name;
	}

	public function append_child( $node ) {
		$this->children[] = $node;
	}

	/**
	 * Flatten the matched rule fragments as if their children were direct
	 * descendants of the current rule.
	 *
	 * What are rule fragments?
	 *
	 * When we initially parse the grammar file, it has compound rules such
	 * as this one:
	 *
	 *      query ::= EOF | ((simpleStatement | beginWork) ((SEMICOLON_SYMBOL EOF?) | EOF))
	 *
	 * Building a parser that can understand such rules is way more complex than building
	 * a parser that only follows simple rules, so we flatten those compound rules into
	 * simpler ones. The above rule would be flattened to:
	 *
	 *      query ::= EOF | %query0
	 *      %query0 ::= %%query01 %%query02
	 *      %%query01 ::= simpleStatement | beginWork
	 *      %%query02 ::= SEMICOLON_SYMBOL EOF_zero_or_one | EOF
	 *      EOF_zero_or_one ::= EOF | ε
	 *
	 * This factorization happens in "convert-grammar.php".
	 *
	 * "Fragments" are intermediate artifacts whose names are not in the original grammar.
	 * They are extremely useful for the parser, but the API consumer should never have to
	 * worry about them. Fragment names start with a percent sign ("%").
	 *
	 * The code below inlines every fragment back in its parent rule.
	 *
	 * We could optimize this. The current $match may be discarded later on so any inlining
	 * effort here would be wasted. However, inlining seems cheap and doing it bottom-up here
	 * is **much** easier than reprocessing the parse tree top-down later on.
	 *
	 * The following parse tree:
	 *
	 * [
	 *      'query' => [
	 *          [
	 *              '%query01' => [
	 *                  [
	 *                      'simpleStatement' => [
	 *                          MySQLToken(MySQLLexer::WITH_SYMBOL, 'WITH')
	 *                      ],
	 *                      '%query02' => [
	 *                          [
	 *                              'simpleStatement' => [
	 *                                  MySQLToken(MySQLLexer::WITH_SYMBOL, 'WITH')
	 *                          ]
	 *                      ],
	 *                  ]
	 *              ]
	 *          ]
	 *      ]
	 * ]
	 *
	 * Would be inlined as:
	 *
	 * [
	 *      'query' => [
	 *          [
	 *              'simpleStatement' => [
	 *                  MySQLToken(MySQLLexer::WITH_SYMBOL, 'WITH')
	 *              ]
	 *          ],
	 *          [
	 *              'simpleStatement' => [
	 *                  MySQLToken(MySQLLexer::WITH_SYMBOL, 'WITH')
	 *              ]
	 *          ]
	 *      ]
	 * ]
	 */
	public function merge_fragment( $node ) {
		$this->children = array_merge( $this->children, $node->children );
	}

	/**
	 * Check if this node has any child nodes or tokens.
	 *
	 * @return bool True if this node has any child nodes or tokens, false otherwise.
	 */
	public function has_child(): bool {
		return count( $this->children ) > 0;
	}

	/**
	 * Check if this node has any child nodes.
	 *
	 * @param  string|null $rule_name Optional. A node rule name to check for.
	 * @return bool                   True if any child nodes are found, false otherwise.
	 */
	public function has_child_node( ?string $rule_name = null ): bool {
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Node
				&& ( null === $rule_name || $child->rule_name === $rule_name )
			) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if this node has any child tokens.
	 *
	 * @param  int|null $token_id Optional. A token ID to check for.
	 * @return bool               True if any child tokens are found, false otherwise.
	 */
	public function has_child_token( ?int $token_id = null ): bool {
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Token
				&& ( null === $token_id || $child->id === $token_id )
			) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the first child node or token of this node.
	 *
	 * @return WP_Parser_Node|WP_Parser_Token|null The first child node or token;
	 *                                             null when no children are found.
	 */
	public function get_first_child() {
		return $this->children[0] ?? null;
	}

	/**
	 * Get the first child node of this node.
	 *
	 * @param  string|null $rule_name Optional. A node rule name to check for.
	 * @return WP_Parser_Node|null    The first matching child node; null when no children are found.
	 */
	public function get_first_child_node( ?string $rule_name = null ): ?WP_Parser_Node {
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Node
				&& ( null === $rule_name || $child->rule_name === $rule_name )
			) {
				return $child;
			}
		}
		return null;
	}

	/**
	 * Get the first child token of this node.
	 *
	 * @param  int|null $token_id   Optional. A token ID to check for.
	 * @return WP_Parser_Token|null The first matching child token; null when no children are found.
	 */
	public function get_first_child_token( ?int $token_id = null ): ?WP_Parser_Token {
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Token
				&& ( null === $token_id || $child->id === $token_id )
			) {
				return $child;
			}
		}
		return null;
	}

	/**
	 * Get the first descendant node of this node.
	 *
	 * The node children are traversed recursively in a depth-first order until
	 * a matching descendant node is found, or the entire subtree is searched.
	 *
	 * @param  string|null $rule_name Optional. A node rule name to check for.
	 * @return WP_Parser_Node|null    The first matching descendant node; null when no descendants are found.
	 */
	public function get_first_descendant_node( ?string $rule_name = null ): ?WP_Parser_Node {
		for ( $i = 0; $i < count( $this->children ); $i++ ) {
			$child = $this->children[ $i ];
			if ( ! $child instanceof WP_Parser_Node ) {
				continue;
			}
			if ( null === $rule_name || $child->rule_name === $rule_name ) {
				return $child;
			}
			$node = $child->get_first_descendant_node( $rule_name );
			if ( $node ) {
				return $node;
			}
		}
		return null;
	}

	/**
	 * Get the first descendant token of this node.
	 *
	 * The node children are traversed recursively in a depth-first order until
	 * a matching descendant token is found, or the entire subtree is searched.
	 *
	 * @param  int|null $token_id   Optional. A token ID to check for.
	 * @return WP_Parser_Token|null The first matching descendant token; null when no descendants are found.
	 */
	public function get_first_descendant_token( ?int $token_id = null ): ?WP_Parser_Token {
		for ( $i = 0; $i < count( $this->children ); $i++ ) {
			$child = $this->children[ $i ];
			if ( $child instanceof WP_Parser_Token ) {
				if ( null === $token_id || $child->id === $token_id ) {
					return $child;
				}
			} else {
				$token = $child->get_first_descendant_token( $token_id );
				if ( $token ) {
					return $token;
				}
			}
		}
		return null;
	}

	/**
	 * Get all children of this node.
	 *
	 * @return array<WP_Parser_Node|WP_Parser_Token> An array of all child nodes and tokens of this node.
	 */
	public function get_children(): array {
		return $this->children;
	}

	/**
	 * Get all child nodes of this node.
	 *
	 * @param  string|null $rule_name Optional. A node rule name to check for.
	 * @return WP_Parser_Node[]       An array of all matching child nodes.
	 */
	public function get_child_nodes( ?string $rule_name = null ): array {
		$nodes = array();
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Node
				&& ( null === $rule_name || $child->rule_name === $rule_name )
			) {
				$nodes[] = $child;
			}
		}
		return $nodes;
	}

	/**
	 * Get all child tokens of this node.
	 *
	 * @param  int|null $token_id Optional. A token ID to check for.
	 * @return WP_Parser_Token[]  An array of all matching child tokens.
	 */
	public function get_child_tokens( ?int $token_id = null ): array {
		$tokens = array();
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Token
				&& ( null === $token_id || $child->id === $token_id )
			) {
				$tokens[] = $child;
			}
		}
		return $tokens;
	}

	/**
	 * Get all descendants of this node.
	 *
	 * The descendants are collected using a depth-first pre-order NLR traversal.
	 * This produces a natural ordering that corresponds to the original input.
	 *
	 * @return array<WP_Parser_Node|WP_Parser_Token> An array of all descendant nodes and tokens of this node.
	 */
	public function get_descendants(): array {
		$descendants = array();
		foreach ( $this->children as $child ) {
			if ( $child instanceof WP_Parser_Node ) {
				$descendants[] = $child;
				$descendants   = array_merge( $descendants, $child->get_descendants() );
			} else {
				$descendants[] = $child;
			}
		}
		return $descendants;
	}

	/**
	 * Get all descendant nodes of this node.
	 *
	 * The descendants are collected using a depth-first pre-order NLR traversal.
	 * This produces a natural ordering that corresponds to the original input.
	 * All matching nodes are collected during the traversal.
	 *
	 * @param  string|null $rule_name Optional. A node rule name to check for.
	 * @return WP_Parser_Node[]       An array of all matching descendant nodes.
	 */
	public function get_descendant_nodes( ?string $rule_name = null ): array {
		$nodes = array();
		foreach ( $this->children as $child ) {
			if ( ! $child instanceof WP_Parser_Node ) {
				continue;
			}
			if ( null === $rule_name || $child->rule_name === $rule_name ) {
				$nodes[] = $child;
			}
			$nodes = array_merge( $nodes, $child->get_descendant_nodes( $rule_name ) );
		}
		return $nodes;
	}

	/**
	 * Get all descendant tokens of this node.
	 *
	 * The descendants are collected using a depth-first pre-order NLR traversal.
	 * This produces a natural ordering that corresponds to the original input.
	 * All matching tokens are collected during the traversal.
	 *
	 * @param  int|null $token_id Optional. A token ID to check for.
	 * @return WP_Parser_Token[]  An array of all matching descendant tokens.
	 */
	public function get_descendant_tokens( ?int $token_id = null ): array {
		$tokens = array();
		foreach ( $this->children as $child ) {
			if ( $child instanceof WP_Parser_Token ) {
				if ( null === $token_id || $child->id === $token_id ) {
					$tokens[] = $child;
				}
			} else {
				$tokens = array_merge( $tokens, $child->get_descendant_tokens( $token_id ) );
			}
		}
		return $tokens;
	}

	/**
	 * Get the byte offset in the input string where this node begins.
	 *
	 * @return int The byte offset in the input string where this node begins.
	 */
	public function get_start(): int {
		return $this->get_first_descendant_token()->start;
	}

	/**
	 * Get the byte length of this node in the input string.
	 *
	 * @return int The byte length of this node in the input string.
	 */
	public function get_length(): int {
		$tokens      = $this->get_descendant_tokens();
		$first_token = $tokens[0];
		$last_token  = $tokens[ count( $tokens ) - 1 ];
		return $last_token->start + $last_token->length - $first_token->start;
	}
}
