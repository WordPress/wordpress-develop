<?php
/**
 * PHPStan parser node visitor that attaches the nearest preceding docblock to
 * each AST node so that hook function calls (e.g. `apply_filters()`) can resolve
 * the `@param` types documented immediately above them.
 *
 * Adapted from szepeviktor/phpstan-wordpress (HookDocsVisitor):
 * https://github.com/szepeviktor/phpstan-wordpress/blob/master/src/HookDocsVisitor.php
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Records the latest docblock seen while traversing and exposes it on every node
 * via the `latestDocComment` attribute.
 */
final class HookDocsVisitor extends NodeVisitorAbstract {

	/**
	 * Start line of the most recently visited node.
	 *
	 * @var int|null
	 */
	protected $latestStartLine = null;

	/**
	 * Most recently encountered docblock.
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

		$this->latestStartLine  = null;
		$this->latestDocComment = null;

		return null;
	}

	/**
	 * Tracks the latest docblock and attaches it to the current node.
	 *
	 * @param Node $node Node being entered.
	 * @return Node|null
	 */
	public function enterNode( Node $node ): ?Node {
		if ( $node->getStartLine() !== $this->latestStartLine ) {
			$this->latestDocComment = null;
		}

		$this->latestStartLine = $node->getStartLine();

		$doc = $node->getDocComment();

		if ( null !== $doc ) {
			$this->latestDocComment = $doc;
		}

		$node->setAttribute( 'latestDocComment', $this->latestDocComment );

		return null;
	}
}