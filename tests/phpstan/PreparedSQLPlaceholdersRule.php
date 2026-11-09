<?php
/**
 * PHPStan rule checking the placeholders of a `$wpdb->prepare()` call.
 *
 * A port of the `UnfinishedPrepare` check of the WordPress Coding Standards'
 * `WordPress.DB.PreparedSQLPlaceholders` sniff, which it replaces. The error carries
 * that code as its identifier.
 *
 * @link https://github.com/WordPress/WordPress-Coding-Standards/blob/3.4.1/WordPress/Sniffs/DB/PreparedSQLPlaceholdersSniff.php
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports a `$wpdb->prepare()` call that is passed replacements but whose query
 * holds no placeholder for them.
 *
 * The one exception is a query building its placeholders for an `IN` clause with
 * `implode( ',', array_fill( 0, count( $values ), '%s' ) )`, which the sniff
 * recognizes as a placeholder of variable length.
 *
 * @implements Rule<CallLike>
 */
final class PreparedSQLPlaceholdersRule implements Rule {

	/**
	 * Error identifier, the code of the sniff check this rule ports.
	 */
	public const IDENTIFIER = 'WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare';

	/**
	 * Matches a placeholder `$wpdb->prepare()` supports, as the sniff defines it.
	 *
	 * Written without delimiters and modifiers so that it can be embedded; use it
	 * with the `x` modifier.
	 */
	public const PLACEHOLDER_REGEX = '(?:
		(?<![^%]%)                     # Don\'t match a literal % (%%), including when it could overlap with a placeholder.
		(?:
			%                          # Start of placeholder.
			(?:[0-9]+\\\\?\$)?         # Optional ordering of the placeholders.
			[+-]?                      # Optional sign specifier.
			(?:
				(?:0|\'.)?                 # Optional padding specifier - excluding the space.
				-?                         # Optional alignment specifier.
				[0-9]*                     # Optional width specifier.
				(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
				|                      # Only recognize the space as padding in combination with a width specifier.
				(?:[ ])?                   # Optional space padding specifier.
				-?                         # Optional alignment specifier.
				[0-9]+                     # Width specifier.
				(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
			)
			[dfFsi]                    # Type specifier.
		)
	)';

	/**
	 * Matches an `IN` clause with a single placeholder, such as ` IN (%s)`.
	 */
	private const IN_CLAUSE_REGEX = '`\s+IN\s*\(\s*%s\s*\)`i';

	/**
	 * Matches query text ending in an opening `IN (`, with or without a quote.
	 */
	private const IN_CLAUSE_OPENING_REGEX = '`\s+IN\s*\(\s*(["\'])?$`i';

	/**
	 * Returns the node type this rule processes.
	 *
	 * @return string
	 */
	public function getNodeType(): string {
		return CallLike::class;
	}

	/**
	 * Processes a call node.
	 *
	 * @param Node  $node  Call node.
	 * @param Scope $scope Analysis scope.
	 * @return list<IdentifierRuleError>
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$call = WpdbCall::fromNode( $node, $scope, array( 'prepare' ) );
		if ( null === $call ) {
			return array();
		}

		$query = $call->getQueryArg();
		if ( null === $query ) {
			return array();
		}

		$found = array(
			'text'         => false,
			'placeholders' => 0,
			'in_clauses'   => 0,
		);
		$this->scan( $query->value, $found );

		// A query passed in as a variable or a function call has nothing to examine.
		if ( ! $found['text'] ) {
			return array();
		}

		if ( 0 !== $found['placeholders'] ) {
			return array();
		}

		// A query without placeholders needs no replacements, and the sniff warns
		// about preparing it at all. Replacements passed anyway are what is reported here.
		if ( 1 === count( $call->getArgs() ) || 0 !== $found['in_clauses'] ) {
			return array();
		}

		return array(
			RuleErrorBuilder::message(
				sprintf(
					'Replacement variables found, but no valid placeholders found in the query passed to %s.',
					$call->getDisplay()
				)
			)
				->identifier( self::IDENTIFIER )
				->line( $query->value->getEndLine() )
				->build(),
		);
	}

	/**
	 * Walks the query expression, counting what the sniff counts.
	 *
	 * @param Node                                            $node  Node within the query expression.
	 * @param array{text: bool, placeholders: int, in_clauses: int} $found Running counts.
	 */
	private function scan( Node $node, array &$found ): void {
		if ( $node instanceof String_ ) {
			$this->scanText( $node->value, $found );
			return;
		}

		if ( $node instanceof InterpolatedString ) {
			// Only the query text is examined; what an embedded variable holds is unknown.
			$this->scanText( self::stripEmbeds( $node ), $found );
			return;
		}

		if ( $node instanceof Variable ) {
			return;
		}

		if ( $node instanceof Concat ) {
			$this->scanConcatenation( self::flattenConcatenation( $node ), $found );
			return;
		}

		if ( $node instanceof FuncCall && self::isFunction( $node, 'sprintf' ) ) {
			$this->scanSprintf( $node, $found );
			return;
		}

		$this->scanChildren( $node, $found );
	}

	/**
	 * Walks the children of a node.
	 *
	 * @param Node                                            $node  Node.
	 * @param array{text: bool, placeholders: int, in_clauses: int} $found Running counts.
	 */
	private function scanChildren( Node $node, array &$found ): void {
		foreach ( $node->getSubNodeNames() as $name ) {
			$child = $node->$name;

			if ( $child instanceof Node ) {
				$this->scan( $child, $found );
			} elseif ( is_array( $child ) ) {
				foreach ( $child as $item ) {
					if ( $item instanceof Node ) {
						$this->scan( $item, $found );
					}
				}
			}
		}
	}

	/**
	 * Counts the placeholders and `IN` clauses in a piece of query text.
	 *
	 * @param string                                          $text  Query text.
	 * @param array{text: bool, placeholders: int, in_clauses: int} $found Running counts.
	 */
	private function scanText( string $text, array &$found ): void {
		$found['text']          = true;
		$found['placeholders'] += (int) preg_match_all( '`' . self::PLACEHOLDER_REGEX . '`x', $text );
		$found['in_clauses']   += (int) preg_match_all( self::IN_CLAUSE_REGEX, $text );
	}

	/**
	 * Walks the operands of a concatenation.
	 *
	 * An `implode()` building the placeholders of an `IN` clause, concatenated
	 * right after text ending in `IN (`, is a valid clause rather than text to
	 * count placeholders in.
	 *
	 * @param list<Expr>                                      $operands Operands, in order.
	 * @param array{text: bool, placeholders: int, in_clauses: int} $found    Running counts.
	 */
	private function scanConcatenation( array $operands, array &$found ): void {
		foreach ( $operands as $index => $operand ) {
			if ( $index > 0
				&& $operand instanceof FuncCall
				&& self::isFunction( $operand, 'implode' )
				&& self::endsWithInClauseOpening( $operands[ $index - 1 ] )
				&& self::isPlaceholderImplode( $operand )
			) {
				++$found['in_clauses'];
				continue;
			}

			$this->scan( $operand, $found );
		}
	}

	/**
	 * Walks a `sprintf()` call: its format is query text, its other arguments are not.
	 *
	 * A `sprintf()` passed named arguments cannot be analyzed, since the function
	 * is variadic, and is left alone entirely.
	 *
	 * @param FuncCall                                        $node  The `sprintf()` call.
	 * @param array{text: bool, placeholders: int, in_clauses: int} $found Running counts.
	 */
	private function scanSprintf( FuncCall $node, array &$found ): void {
		$args = $node->getArgs();

		foreach ( $args as $arg ) {
			if ( null !== $arg->name ) {
				return;
			}
		}

		if ( isset( $args[0] ) ) {
			$this->scan( $args[0], $found );
		}
	}

	/**
	 * Flattens a chain of concatenations into its operands, in order.
	 *
	 * @param Concat $node Concatenation.
	 * @return list<Expr>
	 */
	private static function flattenConcatenation( Concat $node ): array {
		$operands = array();

		while ( true ) {
			array_unshift( $operands, $node->right );

			if ( ! $node->left instanceof Concat ) {
				array_unshift( $operands, $node->left );
				break;
			}

			$node = $node->left;
		}

		return $operands;
	}

	/**
	 * Determines whether an operand is query text ending in an opening `IN (`.
	 *
	 * @param Expr $operand Operand preceding an `implode()` call.
	 * @return bool
	 */
	private static function endsWithInClauseOpening( Expr $operand ): bool {
		if ( $operand instanceof String_ ) {
			$text = $operand->value;
		} elseif ( $operand instanceof InterpolatedString ) {
			$text = self::stripEmbeds( $operand );
		} else {
			return false;
		}

		return 1 === preg_match( self::IN_CLAUSE_OPENING_REGEX, $text );
	}

	/**
	 * Determines whether an `implode()` call builds a list of value placeholders:
	 * `implode( ',', array_fill( 0, count( $values ), '%s' ) )`.
	 *
	 * The sniff accepts `','` and `', '` as separator, and a single unquoted
	 * value placeholder as what is repeated. An identifier placeholder is not a
	 * value and is not accepted.
	 *
	 * @param FuncCall $implode The `implode()` call.
	 * @return bool
	 */
	private static function isPlaceholderImplode( FuncCall $implode ): bool {
		$args = $implode->getArgs();
		if ( 2 !== count( $args ) ) {
			return false;
		}

		$separator = WpdbCall::findArg( $args, 1, 'separator' );
		if ( null === $separator
			|| ! $separator->value instanceof String_
			|| ! in_array( $separator->value->value, array( ',', ', ' ), true )
		) {
			return false;
		}

		$array = WpdbCall::findArg( $args, 2, 'array' );
		if ( null === $array || ! $array->value instanceof FuncCall || ! self::isFunction( $array->value, 'array_fill' ) ) {
			return false;
		}

		$value = WpdbCall::findArg( $array->value->getArgs(), 3, 'value' );
		if ( null === $value || ! $value->value instanceof String_ ) {
			return false;
		}

		return 1 === preg_match( '`^%[dfFs]$`', $value->value->value );
	}

	/**
	 * Determines whether a call is to the named function, in any namespace.
	 *
	 * @param FuncCall $node Call.
	 * @param string   $name Function name, in lowercase.
	 * @return bool
	 */
	private static function isFunction( FuncCall $node, string $name ): bool {
		return $node->name instanceof Name && strtolower( $node->name->getLast() ) === $name;
	}

	/**
	 * Returns the literal text of an interpolated string, without its embedded
	 * variables.
	 *
	 * @param InterpolatedString $node String.
	 * @return string
	 */
	private static function stripEmbeds( InterpolatedString $node ): string {
		$text = '';

		foreach ( $node->parts as $part ) {
			if ( $part instanceof InterpolatedStringPart ) {
				$text .= $part->value;
			}
		}

		return $text;
	}
}
