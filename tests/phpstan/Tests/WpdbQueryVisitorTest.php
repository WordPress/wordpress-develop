<?php
/**
 * Tests for WpdbQueryVisitor.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use WordPress\PHPStan\WpdbQueryVisitor;

/**
 * Checks the query text the visitor attaches to the query method calls.
 *
 * These are tested on the visitor itself, rather than through the types inferred, for what cannot be put in a
 * fixture: a class named `wpdb`, which the tests already load from core, and a subclass of `wpdb`, for whose `$this`
 * PHPStan's test harness does not use the extension.
 */
final class WpdbQueryVisitorTest extends TestCase {

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: string, 1: string|null }>
	 */
	public function data_this(): array {
		return array(
			'wpdb'                        => array(
				'class wpdb { %s }',
				'DELETE FROM {wpdb:posts} WHERE ID = {value}',
			),
			'wpdb, in the global namespace' => array(
				'namespace { class wpdb { %s } }',
				'DELETE FROM {wpdb:posts} WHERE ID = {value}',
			),
			'wpdb, in another namespace'  => array(
				'namespace Plugin; class wpdb extends \wpdb { %s }',
				null,
			),
			'wpdb, in a braced namespace' => array(
				'namespace Plugin { class wpdb extends \wpdb { %s } }',
				null,
			),
			'subclass'                    => array(
				'class wpdb_subclass extends wpdb { %s }',
				null,
			),
			'class in a method of wpdb'   => array(
				'class wpdb { public function f() { return new class() { %s }; } }',
				null,
			),
		);
	}

	/**
	 * Tests that `$this` is taken as `$wpdb` only in the `wpdb` class itself.
	 *
	 * @dataProvider data_this
	 *
	 * @param string      $class_code Code of the class, with `%s` for its method.
	 * @param string|null $expected   Expected query text, or null if `$this->prepare()` is not followed, which leaves
	 *                                the whole query unknown.
	 */
	public function test_this( string $class_code, ?string $expected ): void {
		$method = 'public function delete_post( $id ) { return $this->query( $this->prepare( "DELETE FROM $this->posts WHERE ID = %d", $id ) ); }';

		$this->assertSame( array( $expected ), self::get_queries( '<?php ' . str_replace( '%s', $method, $class_code ) ) );
	}

	/**
	 * Gets the query text attached to each query method call in some code.
	 *
	 * @param string $code PHP code.
	 * @return list<string|null> Query text of each call, or null where none is attached.
	 */
	private static function get_queries( string $code ): array {
		$nodes = array_values( ( new ParserFactory() )->createForNewestSupportedVersion()->parse( $code ) ?? array() );
		( new WpdbQueryVisitor() )->beforeTraverse( $nodes );

		$queries = array();
		foreach ( ( new NodeFinder() )->findInstanceOf( $nodes, MethodCall::class ) as $call ) {
			if ( $call->name instanceof Identifier && 'query' === $call->name->toString() ) {
				$attribute = $call->getAttribute( WpdbQueryVisitor::ATTRIBUTE );
				$queries[] = is_string( $attribute ) ? $attribute : null;
			}
		}
		return $queries;
	}
}
