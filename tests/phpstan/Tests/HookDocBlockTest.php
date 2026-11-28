<?php
/**
 * Tests for HookDocBlock.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPStan\Testing\PHPStanTestCase;
use WordPress\PHPStan\HookDocBlock;

/**
 * Checks the helpers the rules share.
 *
 * Resolving documentation for a call is covered end to end by the rule tests and
 * the return type extension test.
 */
final class HookDocBlockTest extends PHPStanTestCase {

	/**
	 * Returns the configuration registering the services under test.
	 *
	 * @return list<string>
	 */
	public static function getAdditionalConfigFiles(): array {
		return array( __DIR__ . '/tests.neon' );
	}

	/**
	 * @dataProvider data_hook_names
	 *
	 * @param string $call         Hook call expression.
	 * @param bool   $identifiable Whether the hook name carries literal text.
	 * @param string $display      How the name is rendered in messages.
	 */
	public function test_hook_names( string $call, bool $identifiable, string $display ): void {
		$node = self::call( $call );

		$this->assertSame( $identifiable, HookDocBlock::hasIdentifiableHookName( $node ) );
		$this->assertSame( $display, HookDocBlock::getHookNameDisplay( $node ) );
	}

	/**
	 * @return array<string, array{string, bool, string}>
	 */
	public function data_hook_names(): array {
		return array(
			'literal'                       => array( "apply_filters( 'the_content', \$c )", true, 'the_content' ),
			'interpolated with literal'     => array( 'apply_filters( "pre_option_{$option}", $v )', true, 'pre_option_{$option}' ),
			'interpolated without literal'  => array( 'apply_filters( "{$hook}", $v )', false, '{$hook}' ),
			'interpolated separators only'  => array( 'apply_filters( "{$taxonomy}_{$field}", $v )', true, '{$taxonomy}_{$field}' ),
			'interpolated property fetch'   => array( 'apply_filters( "manage_{$this->screen->id}_columns", $v )', true, 'manage_{...}_columns' ),
			'concatenated with literal'     => array( "apply_filters( 'prefix_' . \$name, \$v )", true, 'prefix_$name' ),
			'concatenated variables'        => array( 'apply_filters( $a . $b, $v )', false, '$a$b' ),
			'variable'                      => array( 'apply_filters( $hook_name, $v )', false, '$hook_name' ),
			'other expression'              => array( 'apply_filters( wp_tests_name(), $v )', false, '...' ),
			'no arguments'                  => array( 'apply_filters()', false, '' ),
		);
	}

	/**
	 * Tests that a filter resolving to a docblock without parameters is flagged,
	 * and an action is not.
	 */
	public function test_is_filter_missing_param_docs(): void {
		$without = array(
			'kind'       => 'inline',
			'resolved'   => null,
			'paramCount' => 0,
			'problem'    => null,
		);
		$with    = array_merge( $without, array( 'paramCount' => 1 ) );
		$unknown = array_merge( $without, array( 'paramCount' => null ) );

		$this->assertTrue( HookDocBlock::isFilterMissingParamDocs( self::call( "apply_filters( 'a', \$v )" ), $without ) );
		$this->assertTrue( HookDocBlock::isFilterMissingParamDocs( self::call( "apply_filters_ref_array( 'a', \$v )" ), $without ) );
		$this->assertTrue( HookDocBlock::isFilterMissingParamDocs( self::call( "apply_filters_deprecated( 'a', \$v, '1.0' )" ), $without ) );
		$this->assertFalse( HookDocBlock::isFilterMissingParamDocs( self::call( "do_action( 'a', \$v )" ), $without ) );
		$this->assertFalse( HookDocBlock::isFilterMissingParamDocs( self::call( "apply_filters( 'a', \$v )" ), $with ) );
		$this->assertFalse( HookDocBlock::isFilterMissingParamDocs( self::call( "apply_filters( 'a', \$v )" ), $unknown ) );
	}

	/**
	 * Parses a hook call expression.
	 *
	 * @param string $call PHP expression.
	 * @return FuncCall
	 */
	private static function call( string $call ): FuncCall {
		$stmts = ( new ParserFactory() )->createForHostVersion()->parse( '<?php ' . $call . ';' );
		self::assertNotNull( $stmts );

		$calls = ( new NodeFinder() )->findInstanceOf( $stmts, FuncCall::class );
		self::assertNotEmpty( $calls );

		return $calls[0];
	}
}
