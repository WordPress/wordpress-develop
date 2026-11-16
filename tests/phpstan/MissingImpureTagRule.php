<?php
/**
 * PHPStan rule reporting methods that modify state without being marked impure.
 *
 * PHPStan never inspects a callee's body at a call site. It classifies a
 * method's side effects from its signature alone: `@phpstan-pure` means none,
 * while `@phpstan-impure`, a `void` return type, or returning `$this` means
 * there are some. Any other method only "maybe" has side effects, and after
 * such a call PHPStan keeps the narrowed types of `$this->…` properties that
 * the caller established before the call. So a method that returns a value and
 * also changes `$this->state` leaves its callers with a stale narrowed type for
 * that state, and comparisons against it are then reported as always true or
 * always false.
 *
 * This rule finds such methods by looking for writes in the method body to
 * properties of `$this`, to static properties of the class, or to global
 * variables, in methods whose side effects PHPStan only considers "maybe".
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\StaticType;

/**
 * Reports non-void methods which write object, class, or global state but are
 * not marked `@phpstan-impure`.
 *
 * @implements Rule<InClassMethodNode>
 */
class MissingImpureTagRule implements Rule {

	/**
	 * Whether to also report a method that calls a method of `$this` which
	 * PHPStan already considers to have side effects (one that is `void`,
	 * `@phpstan-impure`, or returns `$this`).
	 *
	 * @var bool
	 */
	private bool $reportTransitive;

	/**
	 * Constructor.
	 *
	 * @param bool $report_transitive Whether to report writes made via calls to impure methods of `$this`.
	 */
	public function __construct( bool $report_transitive = false ) {
		$this->reportTransitive = $report_transitive;
	}

	/**
	 * Returns the node type this rule processes.
	 *
	 * @return string
	 */
	public function getNodeType(): string {
		return InClassMethodNode::class;
	}

	/**
	 * Processes a class method node.
	 *
	 * @param InClassMethodNode $node  Class method node.
	 * @param Scope             $scope Analysis scope.
	 * @return list<IdentifierRuleError>
	 * @throws ShouldNotHappenException
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$class_method = $node->getOriginalNode();
		if ( null === $class_method->stmts || '__construct' === $class_method->name->toLowerString() ) {
			return array();
		}

		$class_reflection = $node->getClassReflection();
		$method_name      = $class_method->name->toString();
		if ( ! $class_reflection->hasNativeMethod( $method_name ) ) {
			return array();
		}

		/*
		 * Use the reflection that call sites see, so that everything PHPStan already
		 * accounts for is skipped: a void return type, `@phpstan-pure`/`@phpstan-impure`
		 * (and their `@psalm-`/`@pure` variants), the class-level
		 * `@phpstan-all-methods-pure`/`-impure` tags, tags inherited from a parent, and
		 * methods returning `$this`.
		 */
		$method_reflection = $class_reflection->getNativeMethod( $method_name );
		if ( ! $method_reflection->hasSideEffects()->maybe() ) {
			return array();
		}

		// A fluent method returning `static` is also treated as having side effects here.
		$return_type = $node->getMethodReflection()->getReturnType();
		if ( ! $class_method->isStatic() && $return_type instanceof StaticType ) {
			return array();
		}

		$write = $this->findWrite( $class_method->stmts, $class_reflection->getName(), $method_reflection->getDeclaringClass()->getName() );
		if ( null === $write && $this->reportTransitive ) {
			$write = $this->findImpureThisCall( $class_method->stmts, $class_reflection );
		}
		if ( null === $write ) {
			return array();
		}

		return array(
			RuleErrorBuilder::message(
				sprintf(
					'Method %s::%s() modifies %s but is not marked @phpstan-impure.',
					$class_reflection->getDisplayName(),
					$method_name,
					$write['description']
				)
			)
				->identifier( 'wordpress.missingImpureTag' )
				->line( $write['line'] )
				->tip( 'Without @phpstan-impure, callers keep narrowed types of this state across the call.' )
				->build(),
		);
	}

	/**
	 * Finds the first statement in a method body that writes state.
	 *
	 * Nested closures, arrow functions, and classes are not descended into, since
	 * they do not run as part of the call.
	 *
	 * @param Stmt[] $stmts           Method body.
	 * @param string $class_name      Name of the class being analysed.
	 * @param string $declaring_class Name of the class declaring the method.
	 * @return array{description: string, line: int}|null The written state and its line, or null if none.
	 */
	private function findWrite( array $stmts, string $class_name, string $declaring_class ): ?array {
		$global_names = array();
		$found        = null;

		$this->walk(
			$stmts,
			function ( Node $node ) use ( &$global_names, &$found, $class_name, $declaring_class ): bool {
				if ( $node instanceof Stmt\Global_ ) {
					foreach ( $node->vars as $var ) {
						if ( $var instanceof Expr\Variable && is_string( $var->name ) ) {
							$global_names[ $var->name ] = true;
						}
					}
					return false;
				}

				$targets = array();
				if (
					$node instanceof Expr\Assign
					|| $node instanceof Expr\AssignOp
					|| $node instanceof Expr\AssignRef
				) {
					$targets = $this->expandDestructuring( $node->var );
				} elseif (
					$node instanceof Expr\PreInc
					|| $node instanceof Expr\PostInc
					|| $node instanceof Expr\PreDec
					|| $node instanceof Expr\PostDec
				) {
					$targets = array( $node->var );
				} elseif ( $node instanceof Stmt\Unset_ ) {
					$targets = $node->vars;
				}

				foreach ( $targets as $target ) {
					$description = $this->describeTarget( $target, $global_names, $class_name, $declaring_class );
					if ( null !== $description ) {
						$found = array(
							'description' => $description,
							'line'        => $node->getStartLine(),
						);
						return true;
					}
				}
				return false;
			}
		);

		return $found;
	}

	/**
	 * Finds the first call to a method of `$this` which PHPStan already considers
	 * to have side effects.
	 *
	 * @param Stmt[]                              $stmts            Method body.
	 * @param \PHPStan\Reflection\ClassReflection $class_reflection Class being analysed.
	 * @return array{description: string, line: int}|null The call and its line, or null if none.
	 */
	private function findImpureThisCall( array $stmts, \PHPStan\Reflection\ClassReflection $class_reflection ): ?array {
		$found = null;

		$this->walk(
			$stmts,
			function ( Node $node ) use ( &$found, $class_reflection ): bool {
				if (
					! ( $node instanceof Expr\MethodCall || $node instanceof Expr\StaticCall )
					|| ! $node->name instanceof Node\Identifier
				) {
					return false;
				}

				if ( $node instanceof Expr\MethodCall ) {
					if ( ! $node->var instanceof Expr\Variable || 'this' !== $node->var->name ) {
						return false;
					}
				} elseif ( ! $node->class instanceof Name || ! in_array( $node->class->toLowerString(), array( 'self', 'static', 'parent' ), true ) ) {
					return false;
				}

				$callee = $node->name->toString();
				if ( ! $class_reflection->hasNativeMethod( $callee ) ) {
					return false;
				}
				if ( ! $class_reflection->getNativeMethod( $callee )->hasSideEffects()->yes() ) {
					return false;
				}

				$found = array(
					'description' => sprintf( 'state via %s()', $callee ),
					'line'        => $node->getStartLine(),
				);
				return true;
			}
		);

		return $found;
	}

	/**
	 * Walks statements depth-first in source order, without descending into
	 * nested function-likes or classes.
	 *
	 * @param Node[]                $nodes    Nodes to walk.
	 * @param callable(Node): bool $callback Visitor; returning true stops the walk.
	 * @return bool Whether the walk was stopped.
	 */
	private function walk( array $nodes, callable $callback ): bool {
		foreach ( $nodes as $node ) {
			if ( ! $node instanceof Node ) {
				continue;
			}
			if ( $callback( $node ) ) {
				return true;
			}
			if (
				$node instanceof Expr\Closure
				|| $node instanceof Expr\ArrowFunction
				|| $node instanceof Stmt\Function_
				|| $node instanceof Stmt\ClassLike
			) {
				continue;
			}
			foreach ( $node->getSubNodeNames() as $name ) {
				$sub = $node->$name;
				if ( $sub instanceof Node ) {
					$sub = array( $sub );
				}
				if ( is_array( $sub ) && $this->walk( $sub, $callback ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Expands a `list()`/`[]` destructuring target into the expressions it assigns.
	 *
	 * @param Expr $target Assignment target.
	 * @return Expr[] Assigned expressions.
	 */
	private function expandDestructuring( Expr $target ): array {
		if ( ! $target instanceof Expr\List_ && ! $target instanceof Expr\Array_ ) {
			return array( $target );
		}

		$targets = array();
		foreach ( $target->items as $item ) {
			if ( null !== $item ) {
				$targets = array_merge( $targets, $this->expandDestructuring( $item->value ) );
			}
		}
		return $targets;
	}

	/**
	 * Describes the state an assignment target writes, if it is not local.
	 *
	 * @param Expr                $target          Assignment target.
	 * @param array<string, true> $global_names    Variables imported with `global`.
	 * @param string              $class_name      Name of the class being analysed.
	 * @param string              $declaring_class Name of the class declaring the method.
	 * @return string|null Description of the written state, or null for a local variable.
	 */
	private function describeTarget( Expr $target, array $global_names, string $class_name, string $declaring_class ): ?string {
		$root = $target;
		while (
			$root instanceof Expr\ArrayDimFetch
			|| $root instanceof Expr\PropertyFetch
			|| $root instanceof Expr\NullsafePropertyFetch
		) {
			$root = $root->var;
		}

		if ( $root instanceof Expr\StaticPropertyFetch ) {
			if ( $root->class instanceof Name ) {
				$class = $root->class->toLowerString();
				if (
					in_array( $class, array( 'self', 'static', 'parent' ), true )
					|| strtolower( $root->class->toString() ) === strtolower( $class_name )
					|| strtolower( $root->class->toString() ) === strtolower( $declaring_class )
				) {
					return 'class state';
				}
			}
			return 'static state of another class';
		}

		if ( ! $root instanceof Expr\Variable || ! is_string( $root->name ) ) {
			return null;
		}

		if ( 'this' === $root->name ) {
			// Assigning to `$this` itself is impossible; only its properties count.
			return $root === $target ? null : 'object state';
		}
		if ( 'GLOBALS' === $root->name ) {
			return 'global state';
		}
		if ( isset( $global_names[ $root->name ] ) ) {
			return sprintf( 'global $%s', $root->name );
		}
		return null;
	}
}
