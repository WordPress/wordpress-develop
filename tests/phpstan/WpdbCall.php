<?php
/**
 * Recognizes a call to one of the `$wpdb` methods that take a query.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;

/**
 * A call such as `$wpdb->query( ... )`, `$wpdb?->prepare( ... )` or `wpdb::prepare( ... )`.
 *
 * The WordPress Coding Standards recognize the receiver by name: the `$wpdb`
 * variable, or the global `wpdb` class called statically. Those are recognized
 * here too, and so is any other expression PHPStan knows to be a `wpdb`, such as
 * a property documented as one.
 */
final class WpdbCall {

	/**
	 * The `$wpdb` methods that take a query as their first parameter.
	 */
	public const QUERY_METHODS = array(
		'get_var',
		'get_col',
		'get_row',
		'get_results',
		'prepare',
		'query',
	);

	/**
	 * Method name, in lowercase.
	 *
	 * @var string
	 */
	private string $method;

	/**
	 * Arguments passed, in order.
	 *
	 * @var list<Arg>
	 */
	private array $args;

	/**
	 * The call as it is written, for messages, such as `$wpdb->query()`.
	 *
	 * @var string
	 */
	private string $display;

	/**
	 * Constructor.
	 *
	 * @param string    $method  Method name, in lowercase.
	 * @param list<Arg> $args    Arguments passed, in order.
	 * @param string    $display The call as it is written.
	 */
	private function __construct( string $method, array $args, string $display ) {
		$this->method  = $method;
		$this->args    = $args;
		$this->display = $display;
	}

	/**
	 * Recognizes a call to one of the given `$wpdb` methods.
	 *
	 * @param Node         $node    Node being analyzed.
	 * @param Scope        $scope   Analysis scope.
	 * @param list<string> $methods Method names to recognize, in lowercase.
	 * @return self|null Null when the node is not such a call.
	 */
	public static function fromNode( Node $node, Scope $scope, array $methods ): ?self {
		// PHPStan analyzes a nullsafe call as a plain call too, marked by an attribute,
		// and hands rules both. Only the plain one is examined, so each call is examined once.
		if ( $node instanceof NullsafeMethodCall ) {
			return null;
		}

		if ( $node instanceof MethodCall ) {
			if ( ! $node->name instanceof Identifier || $node->isFirstClassCallable() ) {
				return null;
			}

			$method = strtolower( $node->name->toString() );
			if ( ! in_array( $method, $methods, true ) || ! self::isWpdb( $node->var, $scope ) ) {
				return null;
			}

			$operator = true === $node->getAttribute( 'virtualNullsafeMethodCall' ) ? '?->' : '->';

			return new self( $method, $node->getArgs(), self::print( $node->var ) . $operator . $node->name->toString() . '()' );
		}

		if ( $node instanceof StaticCall ) {
			if ( ! $node->name instanceof Identifier || ! $node->class instanceof Name || $node->isFirstClassCallable() ) {
				return null;
			}

			$method = strtolower( $node->name->toString() );
			if ( ! in_array( $method, $methods, true ) || 'wpdb' !== strtolower( $scope->resolveName( $node->class ) ) ) {
				return null;
			}

			return new self( $method, $node->getArgs(), 'wpdb::' . $node->name->toString() . '()' );
		}

		return null;
	}

	/**
	 * Determines whether an expression is the database object.
	 *
	 * @param Expr  $expr  Expression.
	 * @param Scope $scope Analysis scope.
	 * @return bool
	 */
	public static function isWpdb( Expr $expr, Scope $scope ): bool {
		if ( $expr instanceof Variable && 'wpdb' === $expr->name ) {
			return true;
		}

		return ( new ObjectType( 'wpdb' ) )->isSuperTypeOf( $scope->getType( $expr ) )->yes();
	}

	/**
	 * Returns the method name, in lowercase.
	 *
	 * @return string
	 */
	public function getMethod(): string {
		return $this->method;
	}

	/**
	 * Returns the arguments passed, in order.
	 *
	 * @return list<Arg>
	 */
	public function getArgs(): array {
		return $this->args;
	}

	/**
	 * Returns the call as it is written, for messages.
	 *
	 * @return string
	 */
	public function getDisplay(): string {
		return $this->display;
	}

	/**
	 * Returns the argument holding the query.
	 *
	 * That is the first argument, or the one named `query`. An unpacked argument
	 * carries no query to examine.
	 *
	 * @return Arg|null
	 */
	public function getQueryArg(): ?Arg {
		$arg = self::findArg( $this->args, 1, 'query' );

		return null === $arg || $arg->unpack ? null : $arg;
	}

	/**
	 * Finds an argument by position or by name, as PHPCSUtils does.
	 *
	 * @param list<Arg> $args     Arguments passed, in order.
	 * @param int       $position 1-based position among the positional arguments.
	 * @param string    $name     Parameter name, for a named argument.
	 * @return Arg|null
	 */
	public static function findArg( array $args, int $position, string $name ): ?Arg {
		foreach ( $args as $arg ) {
			if ( null !== $arg->name && $arg->name->toString() === $name ) {
				return $arg;
			}
		}

		$positional = 0;
		foreach ( $args as $arg ) {
			if ( null !== $arg->name ) {
				break;
			}

			++$positional;
			if ( $positional === $position ) {
				return $arg;
			}
		}

		return null;
	}

	/**
	 * Prints an expression as PHP source.
	 *
	 * @param Expr $expr Expression.
	 * @return string
	 */
	public static function print( Expr $expr ): string {
		return ( new Standard() )->prettyPrintExpr( $expr );
	}
}
