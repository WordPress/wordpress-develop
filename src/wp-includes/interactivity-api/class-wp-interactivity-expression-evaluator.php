<?php
/**
 * Interactivity API: WP_Interactivity_Expression_Evaluator class.
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sentinel exception marking an expression as UNSUPPORTED.
 *
 * Approach B distinguishes three outcomes for `evaluate()`:
 *   - computed value   — safe, well-formed, supported expression
 *   - null (UNSUPPORTED) — valid JS that PHP cannot evaluate server-side
 *                           (assignments, function-call syntax, malformed,
 *                           unknown identifiers, JSON-unencodable values, …)
 *   - null (defensive)  — a derived-state closure threw; the call site
 *                           already reported via `_doing_it_wrong()` before
 *                           raising this exception to abort evaluation.
 *
 * Throwing a sentinel exception is preferred over returning a marker value
 * because the parser and evaluator recurse and a marker value would have to
 * be threaded through every operator path. The outer `evaluate()` method
 * catches the sentinel (and any other expression error) and returns null.
 *
 * @since 6.9.0
 */
class WP_Interactivity_UnsupportedExpression extends Exception {}

/**
 * Strict JS-subset expression evaluator for the Interactivity API SSR.
 *
 * Tokenizes a JS-like expression, parses it into an AST using a recursive
 * descent parser with correct operator precedence, validates the AST, and
 * evaluates it — all without calling `eval()`. This eliminates the code
 * injection surface that `eval()` would otherwise create.
 *
 * Supported grammar (lowest-precedence first):
 *   Expression      := Ternary
 *   Ternary         := Nullish ( '?' Expression ':' Expression )?
 *   Nullish         := Or ( '??' Or )*
 *   Or              := And ( '||' And )*
 *   And             := BitwiseOr ( '&&' BitwiseOr )*
 *   BitwiseOr       := BitwiseXor ( '|' BitwiseXor )*
 *   BitwiseXor      := BitwiseAnd ( '^' BitwiseAnd )*
 *   BitwiseAnd      := Equality ( '&' Equality )*
 *   Equality        := Comparison ( ('=='|'!='|'==='|'!==') Comparison )*
 *   Comparison      := Shift ( ('<'|'>'|'<='|'>=') Shift )*
 *   Shift           := Addition ( ('<<'|'>>') Addition )*
 *   Addition        := Multiplication ( ('+'|'-') Multiplication )*
 *   Multiplication  := Power ( ('*'|'/'|'%') Power )*
 *   Power           := Unary ( '**' Power )?
 *   Unary           := ('!'|'-'|'~') Unary | Primary
 *   Primary         := number | string | true | false | null
 *                    | identifierPath ( '(' argList ')' )?  // call → UNSUPPORTED
 *                    | '(' Expression ')'
 *
 * Rejected structurally / returned as UNSUPPORTED (null):
 *   - Assignment (`=`, `+=`, …) and increment/decrement (`++`, `--`):
 *     the `=` single-character operator and the `++`/`--` two-character
 *     operators tokenize but are not consumed by any grammar rule, leaving
 *     unconsumed tokens → `Parser::is_complete()` returns false → null.
 *   - Function-call syntax `identifier(...)`: a bare identifier immediately
 *     followed by `(` is rejected as UNSUPPORTED at parse time — no `$functions`
 *     whitelist is wired server-side in this phase (these are client-only
 *     store references defined in view.js).
 *   - Comma operator `(a, b)`: leaves unconsumed tokens → null.
 *   - Multi-statement `;`-delimited expressions: `;` is not a token in the
 *     lexer's operator set, so it throws `WP_Interactivity_UnsupportedExpression`.
 *   - Object/static access (`.` outside dotted state/context paths,
 *     `->`, `::`), `new`, `include`, backticks, etc.: lexer/parser reject.
 *
 * Closures (derived-state getters with a PHP implementation) encountered as
 * stored values at a `state.X` / `context.X` leaf ARE invoked server-side,
 * mirroring the existing dotted-path branch of `WP_Interactivity_API::evaluate()`.
 * The evaluator does not know how to resolve identifiers by itself; instead,
 * it receives a resolver callback from the caller. This keeps the evaluator
 * independent from `WP_Interactivity_API` internals and makes eventual cleanup
 * (keeping only one approach) much simpler.
 *
 * @since 6.9.0
 */
class WP_Interactivity_Expression_Evaluator {

	/**
	 * Identifier resolver callback.
	 *
	 * Signature: function( string $path ) { return mixed; }
	 *
	 * The callback is responsible for resolving dotted `state.*` / `context.*`
	 * paths and for invoking any derived-state closures along the way.
	 *
	 * @var callable
	 */
	private $resolver;

	/**
	 * Lexed token stream consumed by the parser.
	 *
	 * @var array
	 */
	private $tokens;

	/**
	 * Current parser position in `$tokens`.
	 *
	 * @var int
	 */
	private $pos = 0;

	/**
	 * Constructor.
	 *
	 * @param callable $resolver Identifier resolver callback.
	 */
	public function __construct( callable $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Evaluates a JS-like expression string.
	 *
	 * @param string $input The original (pre-regex-transform) directive value.
	 * @return mixed The computed value, or null if the expression is
	 *               unsupported, malformed, or referenced an unknown path.
	 */
	public function evaluate( string $input ) {
		if ( '' === $input ) {
			return null;
		}

		try {
			$this->tokens = $this->lex( $input );
			$this->pos     = 0;

			$ast = $this->parse_ternary();

			// Verify ALL tokens were consumed. Unconsumed tokens signal a
			// construct the grammar doesn't support (assignment, comma
			// operator, trailing garbage) — bail to the client.
			if ( ! $this->is_complete() ) {
				return null;
			}

			return $this->eval_node( $ast );
		} catch ( WP_Interactivity_UnsupportedExpression $e ) {
			return null;
		} catch ( Throwable $e ) {
			// Defensive: any unexpected error during lex/parse/evaluate is
			// treated as unsupported so the directive falls through to the
			// client rather than crashing SSR.
			return null;
		}
	}

	/* =====================================================================
	 * LEXER
	 * ===================================================================== */

	/**
	 * Tokenizes the input string into a list of token arrays.
	 *
	 * Each token is `['type' => 'num'|'str'|'bool'|'null'|'op'|'id', 'value' => mixed]`.
	 * `bool` tokens carry the boolean value; `null` tokens carry null; `id`
	 * tokens carry the full dotted identifier path (e.g. `state.foo.bar`).
	 *
	 * @param string $src Source expression.
	 * @return array Token list.
	 * @throws WP_Interactivity_UnsupportedExpression On invalid characters.
	 */
	private function lex( string $src ): array {
		$tokens = array();
		$len    = strlen( $src );
		$i      = 0;

		while ( $i < $len ) {
			$c = $src[ $i ];

			// Whitespace — skipped.
			if ( ' ' === $c || "\t" === $c || "\n" === $c || "\r" === $c ) {
				++$i;
				continue;
			}

			// Numbers — integer if no '.', float if '.'.
			if ( ctype_digit( $c ) ) {
				$start = $i;
				$has_dot = false;
				while ( $i < $len && ( ctype_digit( $src[ $i ] ) || ( '.' === $src[ $i ] && ! $has_dot ) ) ) {
					if ( '.' === $src[ $i ] ) {
						$has_dot = true;
					}
					++$i;
				}
				$literal = substr( $src, $start, $i - $start );
				$tokens[] = array(
					'type'  => 'num',
					// Track integer-ness so `state.count === 100` matches when
					// the stored value is an int 100 (=== is type-strict).
					'value' => $has_dot ? (float) $literal : (int) $literal,
				);
				continue;
			}

			// Strings — single or double quoted, no escape handling (matches
			// the existing regex-transform/eval path's expectations for simple
			// string literals in directive expressions).
			if ( '"' === $c || "'" === $c ) {
				$quote = $c;
				++$i;
				$start = $i;
				while ( $i < $len && $src[ $i ] !== $quote ) {
					++$i;
				}
				if ( $i >= $len ) {
					// Unterminated string — bail to the client.
					throw new WP_Interactivity_UnsupportedExpression( 'Unterminated string' );
				}
				$tokens[] = array(
					'type'  => 'str',
					'value' => substr( $src, $start, $i - $start ),
				);
				++$i; // Skip closing quote.
				continue;
			}

			// Multi-character operators (3-char first, then 2-char).
			$three = ( $i + 2 < $len ) ? substr( $src, $i, 3 ) : '';
			if ( '===' === $three || '!==' === $three ) {
				$tokens[] = array( 'type' => 'op', 'value' => $three );
				$i += 3;
				continue;
			}
			$two = ( $i + 1 < $len ) ? substr( $src, $i, 2 ) : '';
			// 2-char operators. `++`/`--` are included so they tokenize
			// cleanly — they will leave the parser with unconsumed tokens
			// (no grammar rule consumes them) and the outer evaluate() will
			// return null, which is the UNSUPPORTED outcome we want.
			if ( in_array( $two, array( '??', '==', '!=', '<=', '>=', '&&', '||', '++', '--', '<<', '>>', '**' ), true ) ) {
				$tokens[] = array( 'type' => 'op', 'value' => $two );
				$i += 2;
				continue;
			}

			// Single-character operators. `=` is included so assignment
			// expressions tokenize cleanly (the parser will leave it
			// unconsumed → null, the UNSUPPORTED outcome).
			if ( false !== strpos( '+-*/%()?:=,!~&|^<>[]', $c ) ) {
				$tokens[] = array( 'type' => 'op', 'value' => $c );
				++$i;
				continue;
			}

			// Identifiers (greedy dotted paths like state.foo.bar). The `.`
			// is part of the identifier only when directly following an
			// alphanumeric/underscore char — never standalone, which would
			// otherwise be ambiguous with PHP concat.
			if ( ctype_alpha( $c ) || '_' === $c ) {
				$start = $i;
				++$i;
				while ( $i < $len ) {
					$ch = $src[ $i ];
					if ( ctype_alnum( $ch ) || '_' === $ch ) {
						++$i;
					} elseif ( '.' === $ch
						&& ( $i + 1 < $len )
						&& ( ctype_alpha( $src[ $i + 1 ] ) || '_' === $src[ $i + 1 ] )
					) {
						// Dot only continues the identifier when an
						// alphanumeric/underscore follows it.
						$i += 2;
					} else {
						break;
					}
				}
				$name = substr( $src, $start, $i - $start );

				// Recognize the JS literal keywords as dedicated literal
				// tokens so the evaluator's resolve() never sees them.
				$lower = strtolower( $name );
				if ( 'true' === $lower ) {
					$tokens[] = array( 'type' => 'bool', 'value' => true );
				} elseif ( 'false' === $lower ) {
					$tokens[] = array( 'type' => 'bool', 'value' => false );
				} elseif ( 'null' === $lower ) {
					$tokens[] = array( 'type' => 'null', 'value' => null );
				} else {
					$tokens[] = array( 'type' => 'id', 'value' => $name );
				}
				continue;
			}

			// Any other character is unsupported: backticks (template
			// literals / PHP shell exec), semicolons (multi-statement), `.`,
			// `@`, `#`, `\`, `;`, etc. Bail to the client.
			throw new WP_Interactivity_UnsupportedExpression( 'Invalid character: ' . $c );
		}

		return $tokens;
	}

	/* =====================================================================
	 * PARSER (recursive descent with precedence-chained binary operators)
	 * ===================================================================== */

	/**
	 * True when the parser has consumed every token. Used to detect
	 * unsupported constructs that leave trailing tokens (assignment, comma).
	 *
	 * @return bool
	 */
	private function is_complete(): bool {
		return $this->pos >= count( $this->tokens );
	}

	/**
	 * Parses a ternary expression (lowest precedence).
	 *
	 * @return array AST node.
	 */
	private function parse_ternary(): array {
		$cond = $this->parse_nullish();
		if ( $this->match_op( '?' ) ) {
			$then = $this->parse_ternary();
			$this->consume_op( ':' );
			$else = $this->parse_ternary();
			return array(
				'type' => 'ternary',
				'cond' => $cond,
				'then' => $then,
				'else' => $else,
			);
		}
		return $cond;
	}

	/**
	 * Parses the nullish-coalescing chain.
	 *
	 * @return array AST node.
	 */
	private function parse_nullish(): array {
		$node = $this->parse_or();
		while ( $this->match_op( '??' ) ) {
			$right = $this->parse_or();
			$node  = array(
				'type'  => 'bin',
				'op'    => '??',
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses the logical-OR chain.
	 *
	 * @return array AST node.
	 */
	private function parse_or(): array {
		$node = $this->parse_and();
		while ( $this->match_op( '||' ) ) {
			$right = $this->parse_and();
			$node  = array(
				'type'  => 'bin',
				'op'    => '||',
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses the logical-AND chain.
	 *
	 * @return array AST node.
	 */
	private function parse_and(): array {
		$node = $this->parse_bitwise_or();
		while ( $this->match_op( '&&' ) ) {
			$right = $this->parse_bitwise_or();
			$node  = array(
				'type'  => 'bin',
				'op'    => '&&',
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses the bitwise-OR chain (|).
	 *
	 * @return array AST node.
	 */
	private function parse_bitwise_or(): array {
		$node = $this->parse_bitwise_xor();
		while ( $this->match_op( '|' ) ) {
			$right = $this->parse_bitwise_xor();
			$node  = array(
				'type'  => 'bin',
				'op'    => '|',
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses the bitwise-XOR chain (^).
	 *
	 * @return array AST node.
	 */
	private function parse_bitwise_xor(): array {
		$node = $this->parse_bitwise_and();
		while ( $this->match_op( '^' ) ) {
			$right = $this->parse_bitwise_and();
			$node  = array(
				'type'  => 'bin',
				'op'    => '^',
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses the bitwise-AND chain (&).
	 *
	 * @return array AST node.
	 */
	private function parse_bitwise_and(): array {
		$node = $this->parse_equality();
		while ( $this->match_op( '&' ) ) {
			$right = $this->parse_equality();
			$node  = array(
				'type'  => 'bin',
				'op'    => '&',
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses the equality chain (==, !=, ===, !==).
	 *
	 * @return array AST node.
	 */
	private function parse_equality(): array {
		$node = $this->parse_comparison();
		while ( true ) {
			$op = $this->peek_op_in( array( '==', '!=', '===', '!==' ) );
			if ( null === $op ) {
				break;
			}
			$this->advance();
			$right = $this->parse_comparison();
			$node  = array(
				'type'  => 'bin',
				'op'    => $op,
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses the comparison chain (<, >, <=, >=).
	 *
	 * @return array AST node.
	 */
	private function parse_comparison(): array {
		$node = $this->parse_shift();
		while ( true ) {
			$op = $this->peek_op_in( array( '<', '>', '<=', '>=' ) );
			if ( null === $op ) {
				break;
			}
			$this->advance();
			$right = $this->parse_shift();
			$node  = array(
				'type'  => 'bin',
				'op'    => $op,
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses the shift chain (<<, >>).
	 *
	 * @return array AST node.
	 */
	private function parse_shift(): array {
		$node = $this->parse_add();
		while ( true ) {
			$op = $this->peek_op_in( array( '<<', '>>' ) );
			if ( null === $op ) {
				break;
			}
			$this->advance();
			$right = $this->parse_add();
			$node  = array(
				'type'  => 'bin',
				'op'    => $op,
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses the addition chain (+, -).
	 *
	 * @return array AST node.
	 */
	private function parse_add(): array {
		$node = $this->parse_mul();
		while ( true ) {
			$op = $this->peek_op_in( array( '+', '-' ) );
			if ( null === $op ) {
				break;
			}
			$this->advance();
			$right = $this->parse_mul();
			$node  = array(
				'type'  => 'bin',
				'op'    => $op,
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses the multiplication chain (*, /, %).
	 *
	 * @return array AST node.
	 */
	private function parse_mul(): array {
		$node = $this->parse_power();
		while ( true ) {
			$op = $this->peek_op_in( array( '*', '/', '%' ) );
			if ( null === $op ) {
				break;
			}
			$this->advance();
			$right = $this->parse_power();
			$node  = array(
				'type'  => 'bin',
				'op'    => $op,
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses exponentiation (**), right-associative.
	 *
	 * @return array AST node.
	 */
	private function parse_power(): array {
		$node = $this->parse_unary();
		if ( $this->match_op( '**' ) ) {
			$right = $this->parse_power();
			$node  = array(
				'type'  => 'bin',
				'op'    => '**',
				'left'  => $node,
				'right' => $right,
			);
		}
		return $node;
	}

	/**
	 * Parses a unary expression (!x, -x).
	 *
	 * @return array AST node.
	 */
	private function parse_unary(): array {
		$t = $this->peek();
		if ( null !== $t && 'op' === $t['type'] && ( '!' === $t['value'] || '-' === $t['value'] || '~' === $t['value'] ) ) {
			$op = $t['value'];
			$this->advance();
			$expr = $this->parse_unary();
			return array(
				'type' => 'unary',
				'op'   => $op,
				'expr' => $expr,
			);
		}
		return $this->parse_primary();
	}

	/**
	 * Parses a primary expression (literals, identifiers, grouping, calls).
	 *
	 * @return array AST node.
	 * @throws WP_Interactivity_UnsupportedExpression On call syntax or
	 *         unexpected tokens.
	 */
	private function parse_primary(): array {
		$t = $this->peek();
		if ( null === $t ) {
			throw new WP_Interactivity_UnsupportedExpression( 'Unexpected end of expression' );
		}

		// Literals.
		if ( in_array( $t['type'], array( 'num', 'str', 'bool', 'null' ), true ) ) {
			$this->advance();
			return $t;
		}

		// Identifiers / function-call syntax.
		if ( 'id' === $t['type'] ) {
			$name = $t['value'];
			$this->advance();

			// Call syntax `identifier(...)` is UNSUPPORTED server-side: no
			// `$functions` whitelist is wired (these are client-only store
			// references defined in view.js). Throw the sentinel immediately
			// so we don't build a doomed AST node and waste effort on the
			// arguments.
			$next = $this->peek();
			if ( null !== $next && 'op' === $next['type'] && '(' === $next['value'] ) {
				throw new WP_Interactivity_UnsupportedExpression( 'Function call not supported: ' . $name );
			}

			return array(
				'type'  => 'id',
				'value' => $name,
			);
		}

		// Grouping: `( expression )`.
		if ( 'op' === $t['type'] && '(' === $t['value'] ) {
			$this->advance();
			$inner = $this->parse_ternary();
			$this->consume_op( ')' );
			return $inner;
		}

		throw new WP_Interactivity_UnsupportedExpression( 'Unexpected token' );
	}

	/* ---------- Parser helpers ---------- */

	/**
	 * Returns the current token without consuming it, or null at end.
	 *
	 * @return array|null
	 */
	private function peek() {
		return $this->pos < count( $this->tokens ) ? $this->tokens[ $this->pos ] : null;
	}

	/**
	 * Consumes and returns the current token.
	 */
	private function advance() {
		$t = $this->tokens[ $this->pos ] ?? null;
		if ( $this->pos < count( $this->tokens ) ) {
			++$this->pos;
		}
		return $t;
	}

	/**
	 * Returns the operator value if the current token is the given op,
	 * consuming it; otherwise returns false.
	 *
	 * @param string $op Operator value to match.
	 * @return bool
	 */
	private function match_op( string $op ): bool {
		$t = $this->peek();
		if ( null !== $t && 'op' === $t['type'] && $op === $t['value'] ) {
			$this->advance();
			return true;
		}
		return false;
	}

	/**
	 * Returns the operator value if the current token is an op in `$ops`,
	 * without consuming it; otherwise null.
	 *
	 * @param array $ops Operator values to match.
	 * @return string|null
	 */
	private function peek_op_in( array $ops ) {
		$t = $this->peek();
		if ( null !== $t && 'op' === $t['type'] && in_array( $t['value'], $ops, true ) ) {
			return $t['value'];
		}
		return null;
	}

	/**
	 * Consumes the current token if it is the given op, otherwise throws.
	 *
	 * @param string $op Expected operator value.
	 * @throws WP_Interactivity_UnsupportedExpression When the expected op
	 *         is not present.
	 */
	private function consume_op( string $op ): void {
		if ( ! $this->match_op( $op ) ) {
			throw new WP_Interactivity_UnsupportedExpression( 'Expected ' . $op );
		}
	}

	/* =====================================================================
	 * EVALUATOR
	 * ===================================================================== */

	/**
	 * Evaluates an AST node recursively.
	 *
	 * @param array $node AST node.
	 * @return mixed Computed value.
	 * @throws WP_Interactivity_UnsupportedExpression On unexpected node types.
	 */
	private function eval_node( array $node ) {
		switch ( $node['type'] ) {
			case 'num':
			case 'str':
			case 'bool':
				return $node['value'];
			case 'null':
				return null;
			case 'id':
				return $this->resolve_identifier( $node['value'] );
			case 'unary':
				return $this->eval_unary( $node );
			case 'bin':
				return $this->eval_bin( $node );
			case 'ternary':
				return $this->eval_ternary( $node );
			default:
				throw new WP_Interactivity_UnsupportedExpression( 'Unknown node type' );
		}
	}

	/**
	 * Resolves a dotted identifier path against the store, invoking any
	 * derived-state Closures encountered along the way.
	 *
	 * The actual lookup is delegated to the resolver callback supplied at
	 * construction time. Identifiers not starting with `state.` or `context.`
	 * are still treated as UNSUPPORTED here, before the callback is invoked.
	 *
	 * @param string $path Dotted path, e.g. `state.foo.bar` or `context.x`.
	 * @return mixed Resolved value, or null when the path does not exist.
	 * @throws WP_Interactivity_UnsupportedExpression When the identifier is
	 *         not a state./context. path.
	 */
	private function resolve_identifier( string $path ) {
		// Only state.* and context.* paths are resolvable server-side.
		// Anything else (actions.x, callbacks.y, Math.max, etc.) is a
		// client-side reference → UNSUPPORTED.
		if ( 0 !== strpos( $path, 'state.' ) && 0 !== strpos( $path, 'context.' ) ) {
			throw new WP_Interactivity_UnsupportedExpression( 'Unsupported identifier: ' . $path );
		}

		return call_user_func( $this->resolver, $path );
	}

	/**
	 * Evaluates a unary node.
	 *
	 * @param array $node Unary AST node.
	 * @return mixed
	 */
	private function eval_unary( array $node ) {
		$v = $this->eval_node( $node['expr'] );
		switch ( $node['op'] ) {
			case '!':
				return ! $this->is_js_truthy( $v );
			case '-':
				return - $this->js_to_number( $v );
			case '~':
				return ~ (int) $this->js_to_number( $v );
			default:
				throw new WP_Interactivity_UnsupportedExpression( 'Unknown unary operator' );
		}
	}

	/**
	 * Evaluates a binary node.
	 *
	 * Short-circuit operators (&&, ||, ??) return their JS-semantics operand
	 * (falsy left returns left for &&, truthy left returns left for ||, non-
	 * null left returns left for ??). All other operators evaluate both sides
	 * before applying.
	 *
	 * @param array $node Binary AST node.
	 * @return mixed
	 * @throws WP_Interactivity_UnsupportedExpression On unknown operators.
	 */
	private function eval_bin( array $node ) {
		$l = $this->eval_node( $node['left'] );

		// Short-circuit operators.
		if ( '&&' === $node['op'] ) {
			if ( ! $this->is_js_truthy( $l ) ) {
				return $l; // JS: falsy left returns left.
			}
			return $this->eval_node( $node['right'] );
		}
		if ( '||' === $node['op'] ) {
			if ( $this->is_js_truthy( $l ) ) {
				return $l; // JS: truthy left returns left.
			}
			return $this->eval_node( $node['right'] );
		}
		if ( '??' === $node['op'] ) {
			if ( null !== $l ) {
				return $l;
			}
			return $this->eval_node( $node['right'] );
		}

		$r = $this->eval_node( $node['right'] );

		switch ( $node['op'] ) {
			case '**':
				return $this->js_to_number( $l ) ** $this->js_to_number( $r );
			case '+':
				if ( is_string( $l ) || is_string( $r ) ) {
					return $this->js_to_string( $l ) . $this->js_to_string( $r );
				}
				return $this->js_to_number( $l ) + $this->js_to_number( $r );
			case '-':
				return $this->js_to_number( $l ) - $this->js_to_number( $r );
			case '*':
				return $this->js_to_number( $l ) * $this->js_to_number( $r );
			case '/':
				if ( 0 === $r ) {
					// Mirrors PHP's behaviour: division by zero yields INF
					// (float) for non-zero dividend and 0/0 throws a warning.
					// We let it propagate; the catch in evaluate() handles it.
					return $this->js_to_number( $l ) / $this->js_to_number( $r );
				}
				return $this->js_to_number( $l ) / $this->js_to_number( $r );
			case '%':
				if ( 0 === $this->js_to_number( $r ) ) {
					return false; // Division by zero modulo → falsy fallback.
				}
				return (int) $this->js_to_number( $l ) % (int) $this->js_to_number( $r );
			case '==':
				return $this->js_loose_equal( $l, $r );
			case '!=':
				return ! $this->js_loose_equal( $l, $r );
			case '===':
				return $l === $r;
			case '!==':
				return $l !== $r;
			case '<':
				return $this->js_to_number( $l ) < $this->js_to_number( $r );
			case '>':
				return $this->js_to_number( $l ) > $this->js_to_number( $r );
			case '<=':
				return $this->js_to_number( $l ) <= $this->js_to_number( $r );
			case '>=':
				return $this->js_to_number( $l ) >= $this->js_to_number( $r );
			case '&':
				return (int) $this->js_to_number( $l ) & (int) $this->js_to_number( $r );
			case '^':
				return (int) $this->js_to_number( $l ) ^ (int) $this->js_to_number( $r );
			case '|':
				return (int) $this->js_to_number( $l ) | (int) $this->js_to_number( $r );
			case '<<':
				return (int) $this->js_to_number( $l ) << (int) $this->js_to_number( $r );
			case '>>':
				return (int) $this->js_to_number( $l ) >> (int) $this->js_to_number( $r );
			default:
				throw new WP_Interactivity_UnsupportedExpression( 'Unknown binary operator: ' . $node['op'] );
		}
	}

	/**
	 * Evaluates a ternary node.
	 *
	 * @param array $node Ternary AST node.
	 * @return mixed
	 */
	private function eval_ternary( array $node ) {
		$cond = $this->eval_node( $node['cond'] );
		return $this->is_js_truthy( $cond ) ? $this->eval_node( $node['then'] ) : $this->eval_node( $node['else'] );
	}

	/**
	 * Determines JS-style truthiness for the subset of value types the SSR
	 * evaluator works with.
	 *
	 * Key divergences from PHP:
	 * - empty arrays are truthy in JS, falsy in PHP
	 * - the string '0' is truthy in JS, falsy in PHP
	 *
	 * @param mixed $value Value to test.
	 * @return bool
	 */
	private function is_js_truthy( $value ): bool {
		if ( null === $value ) {
			return false;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return 0 != $value;
		}
		if ( is_string( $value ) ) {
			return '' !== $value;
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			return true;
		}
		return (bool) $value;
	}

	/**
	 * Converts a value to a JS-like number for the scalar cases relevant to
	 * directive expressions.
	 *
	 * @param mixed $value Value to convert.
	 * @return float|int
	 */
	private function js_to_number( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}
		if ( null === $value ) {
			return 0;
		}
		if ( is_string( $value ) ) {
			if ( '' === trim( $value ) ) {
				return 0;
			}
			if ( is_numeric( $value ) ) {
				return false === strpos( $value, '.' ) ? (int) $value : (float) $value;
			}
			return NAN;
		}
		return NAN;
	}

	/**
	 * Converts a value to a JS-like string for the subset of values our tests
	 * exercise.
	 *
	 * @param mixed $value Value to stringify.
	 * @return string
	 */
	private function js_to_string( $value ): string {
		if ( null === $value ) {
			return 'null';
		}
		if ( true === $value ) {
			return 'true';
		}
		if ( false === $value ) {
			return 'false';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		if ( is_string( $value ) ) {
			return $value;
		}
		if ( is_array( $value ) ) {
			return implode( ',', array_map( array( $this, 'js_to_string' ), $value ) );
		}
		if ( is_object( $value ) ) {
			return '[object Object]';
		}
		return (string) $value;
	}

	/**
	 * Implements a JS-like loose equality for the primitive cases relevant to
	 * directive expressions.
	 *
	 * This is intentionally narrower than the full ECMAScript abstract
	 * equality algorithm, but it covers the cases we test and the cases most
	 * likely to appear in templates: number/string/bool/null combinations.
	 *
	 * @param mixed $a Left value.
	 * @param mixed $b Right value.
	 * @return bool
	 */
	private function js_loose_equal( $a, $b ): bool {
		if ( gettype( $a ) === gettype( $b ) ) {
			return $a == $b;
		}

		if ( null === $a || null === $b ) {
			return false;
		}

		if ( is_bool( $a ) ) {
			return $this->js_loose_equal( $this->js_to_number( $a ), $b );
		}
		if ( is_bool( $b ) ) {
			return $this->js_loose_equal( $a, $this->js_to_number( $b ) );
		}

		if ( ( is_int( $a ) || is_float( $a ) ) && is_string( $b ) ) {
			$bn = $this->js_to_number( $b );
			return ! is_nan( $bn ) && $a == $bn;
		}
		if ( is_string( $a ) && ( is_int( $b ) || is_float( $b ) ) ) {
			$an = $this->js_to_number( $a );
			return ! is_nan( $an ) && $an == $b;
		}

		return false;
	}
}