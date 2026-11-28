<?php
/**
 * Tests for HookDocsVisitor.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WordPress\PHPStan\HookDocsVisitor;

/**
 * Checks which docblock, if any, the visitor attaches to each hook call.
 */
final class HookDocsVisitorTest extends TestCase {

	/**
	 * Tests that a docblock reaches the calls nested in the node carrying it, and
	 * no further.
	 */
	public function test_docblock_reach(): void {
		$code = <<<'PHP'
		<?php
		/**
		 * Statement docblock.
		 */
		$value = apply_filters( 'on_statement', $value );

		/**
		 * Nested docblock.
		 */
		$value = strval( trim( apply_filters( 'nested_in_statement', $value ) ) );

		$value = apply_filters( 'undocumented', $value );

		/**
		 * Condition docblock.
		 */
		if ( apply_filters( 'in_condition', false ) ) {
			do_action( 'in_body' );
		}

		$list = array(
			/**
			 * First item docblock.
			 */
			'first'  => apply_filters( 'first_item', $value ),
			'second' => apply_filters( 'second_item', $value ),
		);

		/**
		 * Function docblock.
		 */
		function wp_tests_fn( $value ) {
			return apply_filters( 'in_function', $value );
		}

		/**
		 * Argument docblock.
		 */
		wp_tests_fn(
			/**
			 * Inner argument docblock.
			 */
			apply_filters( 'in_argument', $value ),
			apply_filters( 'sibling_argument', $value )
		);

		/** This filter is documented in wp-includes/canonical.php */
		$value = apply_filters( 'referenced', $value );

		$callback( 'dynamic_call', $value );
		PHP;

		$this->assertSame(
			array(
				'on_statement'        => 'Statement docblock.',
				'nested_in_statement' => 'Nested docblock.',
				'undocumented'        => null,
				'in_condition'        => 'Condition docblock.',
				'in_body'             => null,
				'first_item'          => 'First item docblock.',
				'second_item'         => null,
				'in_function'         => null,
				'in_argument'         => 'Inner argument docblock.',
				'sibling_argument'    => 'Argument docblock.',
				'referenced'          => 'This filter is documented in wp-includes/canonical.php',
				'dynamic_call'        => null,
			),
			self::attached_docblocks( $code )
		);
	}

	/**
	 * Tests that state does not leak from one traversal into the next.
	 *
	 * A traversal that runs to completion leaves no state behind, since every node
	 * entered is also left. So the first traversal is aborted inside a documented
	 * statement, as when another visitor throws, leaving that statement's docblock
	 * current. The second traverses a bare call, with no statement around it to
	 * start an undocumented scope, so only the reset at the start of a traversal
	 * keeps that docblock from being attached to it.
	 */
	public function test_state_is_reset_between_traversals(): void {
		$parser  = ( new ParserFactory() )->createForHostVersion();
		$visitor = new HookDocsVisitor();

		$aborting = new NodeTraverser();
		$aborting->addVisitor( $visitor );
		$aborting->addVisitor(
			new class() extends NodeVisitorAbstract {
				public function enterNode( Node $node ): ?Node {
					if ( $node instanceof FuncCall ) {
						throw new RuntimeException( 'Aborted.' );
					}
					return null;
				}
			}
		);

		$documented = $parser->parse( "<?php\n/**\n * Docblock.\n */\nwp_tests_fn( 1 );" );
		$this->assertNotNull( $documented );
		try {
			$aborting->traverse( $documented );
			$this->fail( 'The traversal was not aborted.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'Aborted.', $e->getMessage() );
		}

		$undocumented = $parser->parse( "<?php\napply_filters( 'b', 1 );" );
		$this->assertNotNull( $undocumented );
		$call = ( new NodeFinder() )->findFirstInstanceOf( $undocumented, FuncCall::class );
		$this->assertInstanceOf( FuncCall::class, $call );

		$traverser = new NodeTraverser();
		$traverser->addVisitor( $visitor );
		$traverser->traverse( array( $call ) );

		$this->assertNull( $call->getAttribute( 'latestDocComment' ) );
	}

	/**
	 * Returns the summary line of the docblock attached to each hook call, keyed
	 * by hook name.
	 *
	 * @param string $code PHP source.
	 * @return array<string, string|null>
	 */
	private static function attached_docblocks( string $code ): array {
		$stmts = ( new ParserFactory() )->createForHostVersion()->parse( $code );
		self::assertNotNull( $stmts );

		$traverser = new NodeTraverser();
		$traverser->addVisitor( new HookDocsVisitor() );
		$traverser->traverse( $stmts );

		$result = array();
		foreach ( ( new NodeFinder() )->findInstanceOf( $stmts, FuncCall::class ) as $call ) {
			$args = $call->getArgs();
			if ( ! isset( $args[0] ) || ! $args[0]->value instanceof String_ || ( $call->name instanceof Name && 'wp_tests_fn' === $call->name->toString() ) ) {
				continue;
			}

			$doc = $call->getAttribute( 'latestDocComment' );
			self::assertTrue( null === $doc || $doc instanceof Doc );

			$result[ $args[0]->value->value ] = null === $doc ? null : self::summary( $doc );
		}

		return $result;
	}

	/**
	 * Returns the first line of prose in a docblock.
	 *
	 * @param Doc $doc Docblock.
	 * @return string
	 */
	private static function summary( Doc $doc ): string {
		$text = preg_replace( '#^/\*\*\s*|\s*\*/$#', '', $doc->getText() );
		$text = preg_replace( '#^\s*\*\s?#m', '', (string) $text );

		return trim( (string) $text );
	}
}
