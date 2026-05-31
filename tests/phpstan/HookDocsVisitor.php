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
 * descendants, including a hook embedded in a multi-line condition or used as an
 * array element value. The docblock is cleared when entering a new statement that
 * has none of its own, which bounds how far it reaches while still letting a
 * statement-level docblock document a hook nested inside an `array(...)`.
 *
 * Adapted from szepeviktor/phpstan-wordpress (HookDocsVisitor):
 * https://github.com/szepeviktor/phpstan-wordpress/blob/master/src/HookDocsVisitor.php
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Records the docblock applying to the current node and exposes it via the
 * `latestDocComment` attribute.
 */
final class HookDocsVisitor extends NodeVisitorAbstract {

	/**
	 * Docblock that currently applies.
	 *
	 * @var \PhpParser\Comment\Doc|null
	 */
	protected $latestDocComment = null;

	/**
	 * Resets state before traversing a new set of nodes.
	 *
	 * @param Node[] $nodes Nodes about to be traversed.
	 * @return Node[]|null
	 */
	public function beforeTraverse( array $nodes ): ?array {
		unset( $nodes );

		$this->latestDocComment = null;

		return null;
	}

	/**
	 * Tracks the applicable docblock and attaches it to the node.
	 *
	 * @param Node $node Node being entered.
	 * @return Node|null
	 */
	public function enterNode( Node $node ): ?Node {
		$doc = $node->getDocComment();

		if ( null !== $doc ) {
			// A docblock here documents this node and everything nested within it.
			$this->latestDocComment = $doc;
		} elseif ( $node instanceof Stmt ) {
			// A new statement without its own docblock ends the reach of the
			// previous one. Array items are intentionally not reset here so that a
			// statement-level docblock can still document a hook nested in an array.
			$this->latestDocComment = null;
		}

		$node->setAttribute( 'latestDocComment', $this->latestDocComment );

		return null;
	}
}
