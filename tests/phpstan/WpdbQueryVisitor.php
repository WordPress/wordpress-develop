<?php
/**
 * PHPStan parser node visitor that reconstructs the SQL passed to the `wpdb`
 * query methods, for `WpdbQueryDynamicMethodReturnTypeExtension`.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

/**
 * Attaches the text of the SQL query passed to `wpdb::get_var()`, `get_row()`,
 * `get_col()`, and `get_results()` to the method call node.
 *
 * The type of a query is rarely a constant string, since it almost always
 * interpolates a table name like `$wpdb->posts`, so the query text cannot be
 * read from its type. And a query is often built up in a variable before the
 * call, which a type extension cannot see the statements of. So this visitor
 * reconstructs the text from the syntax tree instead, before analysis:
 *
 * - String literals, interpolation, and concatenation are joined.
 * - A table property on `$wpdb`, like `$wpdb->posts`, becomes the placeholder
 *   `{wpdb:posts}`, so that the query parser knows which table it names.
 * - `$wpdb->prepare( $query, ... )` is replaced by its query, with the
 *   placeholder `{value}` for each of its placeholders, like `%d` or `%i`, and
 *   `%` for each `%%`.
 * - A variable is replaced by the value assigned to it by the nearest preceding
 *   `$var = ...;` statement, followed by any `$var .= ...;` statements after it.
 *   The search walks back through the preceding statements, out through the
 *   enclosing blocks, and stops at the enclosing function.
 * - Anything else is replaced by the placeholder `{?}`, for a part whose text
 *   is unknown.
 *
 * The search for a variable's value gives up, making the whole value unknown,
 * when the variable is written in a way that may not be straight-line: by a
 * statement nested in a preceding block, or by an enclosing loop or other block
 * (other than an `if`) which the call is in. A nested statement which only
 * appends to the variable is the exception: since text it appends can only
 * follow what came before, it is taken as an unknown suffix, and the start of
 * the query, which holds its select list, stays known.
 *
 * The text is only used to read the select list and the tables in the query,
 * so an unknown part elsewhere, like in the `WHERE` clause, does no harm.
 *
 * Registered as `phpstan.parser.richParserNodeVisitor` in `base.neon`.
 */
final class WpdbQueryVisitor extends NodeVisitorAbstract {

	/**
	 * Name of the attribute holding the reconstructed query on a method call node.
	 */
	public const ATTRIBUTE = 'wordpressWpdbQuery';

	/**
	 * Placeholder for a part of the query whose text is unknown.
	 */
	public const UNKNOWN = '{?}';

	/**
	 * Placeholder for a single value or identifier whose text is unknown, from a placeholder in a prepared query.
	 */
	public const VALUE = '{value}';

	/**
	 * Methods of `wpdb` whose first parameter is a query to reconstruct.
	 */
	public const METHODS = array( 'get_var', 'get_row', 'get_col', 'get_results' );

	/**
	 * Maximum number of variables followed in reconstructing one query.
	 */
	private const MAX_DEPTH = 10;

	/**
	 * Finder for writes to variables.
	 *
	 * @var NodeFinder
	 */
	private NodeFinder $node_finder;

	/**
	 * Names of the parameters of the arrow functions being walked, which shadow the variables outside them.
	 *
	 * @var array<string, true>
	 */
	private array $shadowed = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->node_finder = new NodeFinder();
	}

	/**
	 * Annotates the query method calls in the file before it is traversed.
	 *
	 * The whole file is walked here rather than node by node, since resolving a
	 * variable needs the statements preceding the call, which a visitor's
	 * `enterNode()` does not have.
	 *
	 * @param array<int, Node> $nodes Top-level nodes about to be traversed.
	 * @return array<int, Node>|null Null, since the nodes are not replaced.
	 */
	public function beforeTraverse( array $nodes ): ?array {
		$this->walk_statements( array_values( $nodes ), array() );
		return null;
	}

	/**
	 * Walks a list of statements.
	 *
	 * @param list<Node>                          $stmts  Statements.
	 * @param list<array{ 0: list<Node>, 1: int }> $frames Positions of the statements enclosing the list, outermost
	 *                                                     first. Each is a statement list and the index in it.
	 */
	private function walk_statements( array $stmts, array $frames ): void {
		foreach ( $stmts as $index => $stmt ) {
			$position   = $frames;
			$position[] = array( $stmts, $index );
			$this->walk_node( $stmt, $position );
		}
	}

	/**
	 * Walks a node, annotating the query method calls in it.
	 *
	 * @param Node                                 $node     Node.
	 * @param list<array{ 0: list<Node>, 1: int }> $position Position of the statement the node is in.
	 */
	private function walk_node( Node $node, array $position ): void {
		$shadowed = $this->shadowed;
		if ( $node instanceof FunctionLike ) {
			/*
			 * An arrow function has no statements of its own, so the variables in it are followed outside it, except
			 * its parameters. Another function starts a new scope, whose variables are followed in it alone.
			 */
			if ( $node instanceof ArrowFunction ) {
				foreach ( $node->getParams() as $param ) {
					if ( $param->var instanceof Variable && is_string( $param->var->name ) ) {
						$this->shadowed[ $param->var->name ] = true;
					}
				}
			} else {
				$this->shadowed = array();
			}
		}

		foreach ( $node->getSubNodeNames() as $name ) {
			$sub_node = $node->$name;
			if ( is_array( $sub_node ) ) {
				if ( 'stmts' === $name ) {
					// A function's body starts a new scope, so the variables outside it are not followed.
					$stmts = array_values( array_filter( $sub_node, static fn ( $stmt ): bool => $stmt instanceof Node ) );
					$this->walk_statements( $stmts, $node instanceof FunctionLike ? array() : $position );
				} else {
					foreach ( $sub_node as $item ) {
						if ( $item instanceof Node ) {
							$this->walk_node( $item, $position );
						}
					}
				}
			} elseif ( $sub_node instanceof Node ) {
				$this->walk_node( $sub_node, $position );
			}
		}
		$this->shadowed = $shadowed;

		if (
			$node instanceof MethodCall
			&& $node->name instanceof Identifier
			&& in_array( $node->name->toLowerString(), self::METHODS, true )
			&& isset( $node->args[0] )
			&& $node->args[0] instanceof Arg
		) {
			$query = $this->evaluate( $node->args[0]->value, $position, 0 );
			if ( self::UNKNOWN !== $query ) {
				$node->setAttribute( self::ATTRIBUTE, $query );
			}
		}
	}

	/**
	 * Reconstructs the text of a string expression.
	 *
	 * @param Expr                                 $expr     Expression.
	 * @param list<array{ 0: list<Node>, 1: int }> $position Position of the statement the expression is in.
	 * @param int                                  $depth    Number of variables followed so far.
	 * @return string Text, with `{wpdb:<table>}` for a table and `{?}` for each unknown part.
	 */
	private function evaluate( Expr $expr, array $position, int $depth ): string {
		if ( $expr instanceof String_ ) {
			return $expr->value;
		}

		if ( $expr instanceof InterpolatedString ) {
			$text = '';
			foreach ( $expr->parts as $part ) {
				$text .= $part instanceof InterpolatedStringPart ? $part->value : $this->evaluate( $part, $position, $depth );
			}
			return $text;
		}

		if ( $expr instanceof Concat ) {
			return $this->evaluate( $expr->left, $position, $depth ) . $this->evaluate( $expr->right, $position, $depth );
		}

		if ( $expr instanceof PropertyFetch && $this->is_wpdb( $expr->var ) && $expr->name instanceof Identifier ) {
			return '{wpdb:' . $expr->name->toString() . '}';
		}

		if (
			$expr instanceof MethodCall
			&& $this->is_wpdb( $expr->var )
			&& $expr->name instanceof Identifier
			&& 'prepare' === $expr->name->toLowerString()
			&& isset( $expr->args[0] )
			&& $expr->args[0] instanceof Arg
		) {
			// Each placeholder is a value, or for `%i` an identifier, which is not known.
			return (string) preg_replace_callback(
				'/%(?:%|(?:\d+\$)?[-+0 ]*(?:\'.)?\d*(?:\.\d+)?[dfFsi])/',
				static fn ( array $matches ): string => '%%' === $matches[0] ? '%' : self::VALUE,
				$this->evaluate( $expr->args[0]->value, $position, $depth )
			);
		}

		if (
			$expr instanceof Variable
			&& is_string( $expr->name )
			&& $depth < self::MAX_DEPTH
			// A parameter of an arrow function the call is in, whose value is not assigned by a statement.
			&& ! ( 0 === $depth && isset( $this->shadowed[ $expr->name ] ) )
		) {
			return $this->resolve_variable( $expr->name, $position, $depth + 1 );
		}

		return self::UNKNOWN;
	}

	/**
	 * Reconstructs the value of a variable at a position from the statements preceding it.
	 *
	 * @param string                               $name     Variable name.
	 * @param list<array{ 0: list<Node>, 1: int }> $position Position of the statement reading the variable.
	 * @param int                                  $depth    Number of variables followed so far, including this one.
	 * @return string Text, with `{wpdb:<table>}` for a table and `{?}` for each unknown part.
	 */
	private function resolve_variable( string $name, array $position, int $depth ): string {
		// Text appended to the variable between its assignment and the position.
		$appended = '';

		for ( $level = count( $position ) - 1; $level >= 0; $level-- ) {
			list( $stmts, $index ) = $position[ $level ];

			if ( $this->may_write_before( $stmts[ $index ], $name, count( $position ) - 1 === $level ) ) {
				return self::UNKNOWN;
			}

			for ( $i = $index - 1; $i >= 0; $i-- ) {
				$stmt = $stmts[ $i ];

				$stmt_position   = array_slice( $position, 0, $level );
				$stmt_position[] = array( $stmts, $i );

				if ( $stmt instanceof Stmt\Expression && $this->is_variable( $stmt->expr instanceof Assign ? $stmt->expr->var : null, $name ) ) {
					/** @var Assign $assign */
					$assign = $stmt->expr;
					return $this->evaluate( $assign->expr, $stmt_position, $depth ) . $appended;
				}

				if ( $stmt instanceof Stmt\Expression && $stmt->expr instanceof AssignOp\Concat && $this->is_variable( $stmt->expr->var, $name ) ) {
					$appended = $this->evaluate( $stmt->expr->expr, $stmt_position, $depth ) . $appended;
					continue;
				}

				if ( $this->writes_variable( $stmt, $name, true ) ) {
					// Something other than an append, which may replace the value.
					return self::UNKNOWN;
				}

				if ( $this->writes_variable( $stmt, $name, false ) ) {
					// A nested append, which may or may not have run.
					$appended = self::UNKNOWN . $appended;
				}
			}
		}

		// The variable is not assigned in the function, so it is a parameter, global or the like.
		return self::UNKNOWN;
	}

	/**
	 * Determines whether a statement the position is in may write to a variable before the position.
	 *
	 * A statement at an outer level is a block the position is in. A write to the variable anywhere in a loop may have
	 * run before the position, in an earlier iteration, and likewise in the try block for a position in a catch block,
	 * and so on. The exception is an `if`: of its branches, only the one the position is in has run, after the
	 * conditions.
	 *
	 * The innermost statement is the one the position is in directly. Any write to the variable in it is taken to
	 * have run before the position, since the order in which the parts of a statement run is not followed here, and a
	 * loop's body may have run in an earlier iteration. The exceptions are the branches of an `if`, which run after
	 * its conditions, and an assignment to the variable, which is made after the value being assigned.
	 *
	 * @param Node   $stmt      Statement.
	 * @param string $name      Variable name.
	 * @param bool   $innermost Whether the statement is the innermost one.
	 * @return bool
	 */
	private function may_write_before( Node $stmt, string $name, bool $innermost ): bool {
		if ( $stmt instanceof Stmt\If_ ) {
			$conditions = array( $stmt->cond );
			foreach ( $stmt->elseifs as $elseif ) {
				$conditions[] = $elseif->cond;
			}
			foreach ( $conditions as $condition ) {
				if ( $this->writes_variable( $condition, $name, false ) ) {
					return true;
				}
			}
			return false;
		}

		$is_loop = $stmt instanceof Stmt\While_ || $stmt instanceof Stmt\Do_ || $stmt instanceof Stmt\For_ || $stmt instanceof Stmt\Foreach_;
		if (
			$innermost
			&& ! $is_loop
			&& $stmt instanceof Stmt\Expression
			&& ( $stmt->expr instanceof Assign || $stmt->expr instanceof AssignOp )
			&& $this->is_variable( $stmt->expr->var, $name )
		) {
			// An assignment to the variable is made after its value, which the position is in, is evaluated.
			return $this->writes_variable( $stmt->expr->expr, $name, false );
		}
		return $this->writes_variable( $stmt, $name, false );
	}

	/**
	 * Determines whether a node may write to a variable.
	 *
	 * Writes through a reference, like a by-reference argument, are not detected.
	 *
	 * @param Node   $node            Node to search.
	 * @param string $name            Variable name.
	 * @param bool   $ignore_appends  Whether to ignore writes with `.=`.
	 * @return bool
	 */
	private function writes_variable( Node $node, string $name, bool $ignore_appends ): bool {
		$found = $this->node_finder->findFirst(
			$node,
			function ( Node $node ) use ( $name, $ignore_appends ): bool {
				if ( $node instanceof Assign || $node instanceof AssignRef ) {
					return $this->contains_variable( $node->var, $name );
				}
				if ( $node instanceof AssignOp ) {
					return ! ( $ignore_appends && $node instanceof AssignOp\Concat ) && $this->contains_variable( $node->var, $name );
				}
				if ( $node instanceof Stmt\Foreach_ ) {
					return $this->contains_variable( $node->valueVar, $name ) || ( null !== $node->keyVar && $this->contains_variable( $node->keyVar, $name ) );
				}
				if ( $node instanceof Stmt\Global_ || $node instanceof Stmt\Unset_ ) {
					return $this->contains_variable( $node, $name );
				}
				if ( $node instanceof Node\StaticVar ) {
					return $this->is_variable( $node->var, $name );
				}
				return false;
			}
		);
		return null !== $found;
	}

	/**
	 * Determines whether a node is or contains a variable, like a `list()` being assigned to.
	 *
	 * @param Node   $node Node.
	 * @param string $name Variable name.
	 * @return bool
	 */
	private function contains_variable( Node $node, string $name ): bool {
		return null !== $this->node_finder->findFirst(
			$node,
			function ( Node $node ) use ( $name ): bool {
				return $this->is_variable( $node, $name );
			}
		);
	}

	/**
	 * Determines whether a node is a variable.
	 *
	 * @param Node|null $node Node.
	 * @param string    $name Variable name.
	 * @return bool
	 */
	private function is_variable( ?Node $node, string $name ): bool {
		return $node instanceof Variable && $node->name === $name;
	}

	/**
	 * Determines whether an expression is the `$wpdb` global.
	 *
	 * @param Expr $expr Expression.
	 * @return bool
	 */
	private function is_wpdb( Expr $expr ): bool {
		return $this->is_variable( $expr, 'wpdb' );
	}
}
