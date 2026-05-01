<?php
/**
 * PHPStan parser node visitor that bridges WordPress core's `@global` PHPDoc
 * convention to PHPStan's variable type resolution.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Reads `@global Type $varname` tags from function and method docblocks and
 * injects equivalent inline `@var` docblocks onto matching `global $foo;`
 * statements inside the function body.
 *
 * PHPStan does not consult bootstrap- or stub-declared variable types when
 * resolving `global $foo;` inside functions. It only honors `@var`
 * annotations placed directly on the `global` statement. WordPress core
 * documents globals with `@global` tags on function docblocks instead. This
 * visitor closes the gap so PHPStan can use the existing core annotations
 * without each `global` statement needing its own redundant `@var`.
 *
 * Functions that do not document a global, or that import a global the
 * function docblock does not list, are left untouched and continue to
 * resolve as `mixed` — preserving PHPStan's safety guarantees.
 *
 * Registered as `phpstan.parser.richParserNodeVisitor` in `base.neon`.
 */
final class GlobalDocBlockVisitor extends NodeVisitorAbstract {

	/**
	 * Stack of `@global` tag maps, one frame per enclosing function-like node.
	 *
	 * Each frame maps variable names (without `$`) to their declared type.
	 *
	 * @var list<array<string, string>>
	 */
	private array $stack = array();

	/**
	 * Resets state at the start of each parser traversal.
	 *
	 * @param array<int, Node> $nodes Top-level nodes about to be traversed.
	 * @return array<int, Node>|null
	 */
	public function beforeTraverse( array $nodes ): ?array {
		$this->stack = array();
		return null;
	}

	/**
	 * Pushes a frame when entering a function/method, and injects synthetic
	 * `@var` doc comments on `global` statements that match a documented tag.
	 *
	 * @param Node $node The node being entered.
	 * @return null
	 */
	public function enterNode( Node $node ): ?Node {
		if ( $node instanceof Node\FunctionLike ) {
			$doc           = $node->getDocComment();
			$this->stack[] = $doc !== null ? $this->parse_global_tags( $doc->getText() ) : array();
			return null;
		}

		if ( ! ( $node instanceof Node\Stmt\Global_ ) || $this->stack === array() ) {
			return null;
		}

		$map = $this->stack[ count( $this->stack ) - 1 ];
		if ( $map === array() ) {
			return null;
		}

		// Respect a hand-written `@var` already on the statement.
		$existing = $node->getDocComment();
		if ( $existing !== null && str_contains( $existing->getText(), '@var' ) ) {
			return null;
		}

		$lines = array();
		foreach ( $node->vars as $var ) {
			if ( ! $var instanceof Node\Expr\Variable || ! is_string( $var->name ) ) {
				continue;
			}
			if ( isset( $map[ $var->name ] ) ) {
				$lines[] = sprintf( ' * @var %s $%s', $map[ $var->name ], $var->name );
			}
		}

		if ( $lines !== array() ) {
			$node->setDocComment( new Doc( "/**\n" . implode( "\n", $lines ) . "\n */" ) );
		}

		return null;
	}

	/**
	 * Pops the function-like frame on the way back up.
	 *
	 * @param Node $node The node being left.
	 * @return null
	 */
	public function leaveNode( Node $node ): ?Node {
		if ( $node instanceof Node\FunctionLike ) {
			array_pop( $this->stack );
		}
		return null;
	}

	/**
	 * Extracts `@global Type $varname` tags from a docblock.
	 *
	 * Handles union types (`A|B`) and namespaced/array forms (`A\B`, `A[]`).
	 * Whitespace inside the type is collapsed.
	 *
	 * @param string $docblock Raw docblock text including `/**` markers.
	 * @return array<string, string> Map of variable name (no `$`) to type.
	 */
	private function parse_global_tags( string $docblock ): array {
		$map = array();
		if ( preg_match_all( '/@global\s+(\S+(?:\s*\|\s*\S+)*)\s+\$(\w+)/', $docblock, $matches, PREG_SET_ORDER ) > 0 ) {
			foreach ( $matches as $match ) {
				$map[ $match[2] ] = (string) preg_replace( '/\s+/', '', $match[1] );
			}
		}
		return $map;
	}
}