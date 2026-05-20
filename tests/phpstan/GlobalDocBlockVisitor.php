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
 * Hand-written `@var` annotations on a `global` statement are honored
 * per-variable: in `global $a, $b;`, an existing `@var Foo $a` is left
 * alone, but `$b` will still receive a synthetic `@var` if the function
 * documents it via `@global`.
 *
 * Registered as `phpstan.parser.richParserNodeVisitor` in `base.neon`.
 */
final class GlobalDocBlockVisitor extends NodeVisitorAbstract {

	/**
	 * Stack of `@global` tag maps, one frame per enclosing function-like node.
	 *
	 * Each frame maps variable names (without `$`) to their declared type.
	 *
	 * @var list<array<non-empty-string, non-empty-string>>
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

		/*
		 * Collect variable names that already have a handwritten `@var` on this
		 * statement so we can leave them alone but still inject `@var` lines for
		 * the remaining variables in a multi-variable `global $a, $b;` statement.
		 */
		$existing       = $node->getDocComment();
		$existing_text  = $existing !== null ? $existing->getText() : '';
		$already_typed  = array();
		if ( $existing_text !== '' && preg_match_all( '/@(?:phpstan-)?var\s+[^\n]*?\$(\w+)/', $existing_text, $existing_matches ) > 0 ) {
			$already_typed = array_flip( $existing_matches[1] );
		}

		$lines = array();
		foreach ( $node->vars as $var ) {
			if ( ! $var instanceof Node\Expr\Variable || ! is_string( $var->name ) ) {
				continue;
			}
			if ( isset( $already_typed[ $var->name ] ) || ! isset( $map[ $var->name ] ) ) {
				continue;
			}
			$lines[] = sprintf( ' * @var %s $%s', $map[ $var->name ], $var->name );
		}

		if ( $lines === array() ) {
			return null;
		}

		if ( $existing_text === '' ) {
			$node->setDocComment( new Doc( "/**\n" . implode( "\n", $lines ) . "\n */" ) );
		} else {
			// Insert the new `@var` lines just before the closing `*/`.
			$merged = preg_replace( '#\s*\*/\s*$#', "\n" . implode( "\n", $lines ) . "\n */", $existing_text, 1 );
			$node->setDocComment( new Doc( (string) $merged ) );
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
	 * @return array<non-empty-string, non-empty-string> Map of variable name (no `$`) to type.
	 */
	private function parse_global_tags( string $docblock ): array {
		$map = array();
		if ( preg_match_all( '/@global\s+(?P<type>\S.*?)\s+\$(?P<variable>\w+)/', $docblock, $matches, PREG_SET_ORDER ) > 0 ) {
			foreach ( $matches as $match ) {
				$type = preg_replace( '/\s+/', '', $match['type'] );
				assert( is_string( $type ) && '' !== $type );
				$map[ $match['variable'] ] = $type;
			}
		}
		return $map;
	}
}
