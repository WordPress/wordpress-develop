<?php
/**
 * PHPStan rule reporting variables interpolated into a `$wpdb` query.
 *
 * A port of the `InterpolatedNotPrepared` check of the WordPress Coding
 * Standards' `WordPress.DB.PreparedSQL` sniff, which it replaces. The error carries
 * that code as its identifier.
 *
 * @link https://github.com/WordPress/WordPress-Coding-Standards/blob/3.4.1/WordPress/Sniffs/DB/PreparedSQLSniff.php
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports a variable interpolated into a double-quoted string or heredoc passed as
 * the query of a `$wpdb` method, unless the variable is a `$wpdb` property such as
 * `$wpdb->posts`.
 *
 * Strings inside a call to a function that escapes its argument for SQL, such as
 * `esc_sql()`, are not examined, and neither is a nested `$wpdb` call, which is
 * examined on its own.
 *
 * @implements Rule<CallLike>
 */
final class PreparedSQLRule implements Rule {

	/**
	 * Error identifier, the code of the sniff check this rule ports.
	 */
	public const IDENTIFIER = 'WordPress.DB.PreparedSQL.InterpolatedNotPrepared';

	/**
	 * Functions whose call, arguments included, is not examined: those that escape
	 * a value for use in a query, and those whose result is escaped already.
	 */
	private const SKIPPED_FUNCTIONS = array(
		'absint',
		'esc_sql',
		'floatval',
		'intval',
		'like_escape',
		'count',
	);

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
		$call = WpdbCall::fromNode( $node, $scope, WpdbCall::QUERY_METHODS );
		if ( null === $call ) {
			return array();
		}

		$query = $call->getQueryArg();
		if ( null === $query ) {
			return array();
		}

		$errors = array();
		$this->scan( $query->value, $scope, $call, $errors );

		return $errors;
	}

	/**
	 * Walks the query expression, reporting the interpolated variables it finds.
	 *
	 * @param Node                      $node   Node within the query expression.
	 * @param Scope                     $scope  Analysis scope.
	 * @param WpdbCall                  $call   The call whose query is examined.
	 * @param list<IdentifierRuleError> $errors Errors found so far.
	 */
	private function scan( Node $node, Scope $scope, WpdbCall $call, array &$errors ): void {
		if ( $node instanceof InterpolatedString ) {
			foreach ( $node->parts as $part ) {
				if ( ! $part instanceof Expr || self::isWpdbMember( $part, $scope ) ) {
					continue;
				}

				$errors[] = RuleErrorBuilder::message(
					sprintf(
						'Use placeholders and $wpdb->prepare(); found interpolated variable %s in the query passed to %s.',
						WpdbCall::print( $part ),
						$call->getDisplay()
					)
				)
					->identifier( self::IDENTIFIER )
					->line( $part->getStartLine() )
					->build();
			}

			return;
		}

		// A nested `$wpdb` call is examined when its own node is processed.
		if ( null !== WpdbCall::fromNode( $node, $scope, WpdbCall::QUERY_METHODS ) ) {
			return;
		}

		if ( self::isSkippedCall( $node ) ) {
			return;
		}

		foreach ( $node->getSubNodeNames() as $name ) {
			$child = $node->$name;

			if ( $child instanceof Node ) {
				$this->scan( $child, $scope, $call, $errors );
			} elseif ( is_array( $child ) ) {
				foreach ( $child as $item ) {
					if ( $item instanceof Node ) {
						$this->scan( $item, $scope, $call, $errors );
					}
				}
			}
		}
	}

	/**
	 * Determines whether a node is a call to a function whose call is not examined.
	 *
	 * The sniff recognizes the function by name wherever it appears, so a method of
	 * the same name is skipped as well.
	 *
	 * @param Node $node Node.
	 * @return bool
	 */
	private static function isSkippedCall( Node $node ): bool {
		if ( $node instanceof FuncCall ) {
			$name = $node->name instanceof Name ? $node->name->getLast() : null;
		} elseif ( $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall ) {
			$name = $node->name instanceof Identifier ? $node->name->toString() : null;
		} else {
			return false;
		}

		return null !== $name && in_array( strtolower( $name ), self::SKIPPED_FUNCTIONS, true );
	}

	/**
	 * Determines whether an interpolated expression reads a member of the database
	 * object, such as `$wpdb->posts` or `{$wpdb->get_blog_prefix()}`.
	 *
	 * The database object itself, interpolated bare, is not a member.
	 *
	 * @param Expr  $expr  Interpolated expression.
	 * @param Scope $scope Analysis scope.
	 * @return bool
	 */
	private static function isWpdbMember( Expr $expr, Scope $scope ): bool {
		// The `${expr}` syntax interpolates the value of a variable named by the expression.
		if ( $expr instanceof Variable && $expr->name instanceof Expr ) {
			$expr = $expr->name;
		}

		while ( true ) {
			if ( $expr instanceof ArrayDimFetch ) {
				$expr = $expr->var;
				continue;
			}

			if ( ! $expr instanceof PropertyFetch
				&& ! $expr instanceof NullsafePropertyFetch
				&& ! $expr instanceof MethodCall
				&& ! $expr instanceof NullsafeMethodCall
			) {
				return false;
			}

			$object = $expr->var;

			// `${wpdb->posts}` names the variable with a constant.
			if ( $object instanceof ConstFetch && 'wpdb' === $object->name->toString() ) {
				return true;
			}

			if ( WpdbCall::isWpdb( $object, $scope ) ) {
				return true;
			}

			$expr = $object;
		}
	}
}
