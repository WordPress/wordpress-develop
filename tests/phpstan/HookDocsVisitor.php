<?php
/**
 * PHPStan parser node visitor that attaches the docblock documenting a hook to
 * the hook's function-call node, so that `apply_filters()`/`do_action()` calls
 * can resolve the docblock written immediately above them.
 *
 * The WordPress documentation standards place a hook's docblock immediately
 * before the call, or — when the hook is inside a longer construct — before the
 * statement, conditional, or array item that contains it. To honor all of these,
 * the visitor records a docblock from any node that carries one (a statement, an
 * array item, a function argument, ...) and propagates it to that node's
 * descendants — including a hook embedded in a multi-line condition or used as an
 * array element value.
 *
 * The docblock's reach is bounded to the node that introduced it: it is restored
 * to the previous value when that node is left, so a docblock on one array item
 * does not leak to the next sibling item, while a statement-level docblock still
 * flows into the statement's nested expressions. A statement without its own
 * docblock likewise clears the docblock for its subtree.
 *
 * @link https://github.com/szepeviktor/phpstan-wordpress/blob/20f0406fcb96f8e1b8369d8c0df6f5c525a761aa/src/HookDocsVisitor.php Adapted from szepeviktor/phpstan-wordpress, MIT license.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Records the docblock applying to the current node and exposes it to function
 * calls via the `latestDocComment` attribute.
 */
final class HookDocsVisitor extends NodeVisitorAbstract {

	/**
	 * Docblock that currently applies.
	 *
	 * @var Doc|null
	 */
	protected ?Doc $latestDocComment = null;

	/**
	 * Stack of [node, previous docblock] frames for nodes that changed the
	 * applicable docblock, used to restore the previous value when the node is
	 * left so a docblock only applies to that node's descendants.
	 *
	 * @var list<array{
	 *     0: Node,
	 *     1: Doc|null,
	 * }>
	 */
	private array $stack = array();

	/**
	 * Resets state before traversing a new set of nodes.
	 *
	 * @param Node[] $nodes Nodes about to be traversed.
	 * @return Node[]|null
	 */
	public function beforeTraverse( array $nodes ): ?array {
		$this->latestDocComment = null;
		$this->stack            = array();

		return null;
	}

	/**
	 * Tracks the applicable docblock and attaches it to function-call nodes.
	 *
	 * @param Node $node Node being entered.
	 * @return Node|null
	 */
	public function enterNode( Node $node ): ?Node {
		$doc = $node->getDocComment();

		if ( null !== $doc ) {
			// A docblock here documents this node and everything nested within it.
			$this->stack[]          = array( $node, $this->latestDocComment );
			$this->latestDocComment = $doc;
		} elseif ( $node instanceof Stmt ) {
			// A new statement without its own docblock starts an undocumented scope
			// for its subtree, so a preceding docblock does not carry into it.
			$this->stack[]          = array( $node, $this->latestDocComment );
			$this->latestDocComment = null;
		}

		// Attributes are retained for as long as a parsed file is held in memory, so
		// the docblock is recorded only where it can be read: on a function call, and
		// only when there is one to record. Readers cannot tell an absent attribute
		// from a null one, so skipping the write costs them nothing.
		if ( null !== $this->latestDocComment && $node instanceof FuncCall ) {
			$node->setAttribute( 'latestDocComment', $this->latestDocComment );
		}

		return null;
	}

	/**
	 * Restores the docblock that applied before this node was entered, bounding a
	 * docblock's reach to the node that introduced it.
	 *
	 * @param Node $node Node being left.
	 * @return Node|null
	 */
	public function leaveNode( Node $node ): ?Node {
		$top = end( $this->stack );

		if ( false !== $top && $top[0] === $node ) {
			$this->latestDocComment = $top[1];
			array_pop( $this->stack );
		}

		return null;
	}
}
