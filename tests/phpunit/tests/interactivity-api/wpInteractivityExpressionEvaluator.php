<?php
/**
 * Unit tests for WP_Interactivity_Expression_Evaluator (Approach B).
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.9.0
 *
 * @group interactivity-api
 *
 * @coversDefaultClass WP_Interactivity_Expression_Evaluator
 */
class Tests_Interactivity_ExpressionEvaluator extends WP_UnitTestCase {
	/**
	 * API instance used by the evaluator for closure resolution.
	 *
	 * @var WP_Interactivity_API
	 */
	protected $interactivity;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->interactivity = new WP_Interactivity_API();
	}

	/**
	 * Sets the internal namespace stack to a single namespace.
	 *
	 * @param string $ns Namespace.
	 */
	private function set_namespace_stack( string $ns ): void {
		$interactivity   = new ReflectionClass( $this->interactivity );
		$namespace_stack = $interactivity->getProperty( 'namespace_stack' );
		if ( PHP_VERSION_ID < 80100 ) {
			$namespace_stack->setAccessible( true );
		}
		$namespace_stack->setValue( $this->interactivity, array( $ns ) );
	}

	/**
	 * Reads the internal $derived_state_closures map.
	 *
	 * @return array
	 */
	private function get_derived_state_closures(): array {
		$interactivity = new ReflectionClass( $this->interactivity );
		$prop           = $interactivity->getProperty( 'derived_state_closures' );
		if ( PHP_VERSION_ID < 80100 ) {
			$prop->setAccessible( true );
		}
		return $prop->getValue( $this->interactivity );
	}

	/**
	 * Builds a configured evaluator for the fixture store.
	 *
	 * @param int $invoked_counter Closure invocation counter, passed by ref.
	 * @return WP_Interactivity_Expression_Evaluator
	 */
	private function evaluator( int &$invoked_counter ): WP_Interactivity_Expression_Evaluator {
		$this->interactivity->state(
			'myplugin',
			array(
				'count'  => 5,
				'flag'   => true,
				'zero'   => 0,
				'nested' => function () use ( &$invoked_counter ) {
					++$invoked_counter;
					return array( 'flag' => true );
				},
				'value'  => function () use ( &$invoked_counter ) {
					++$invoked_counter;
					return 7;
				},
				'chain'  => function () use ( &$invoked_counter ) {
					++$invoked_counter;
					return function () use ( &$invoked_counter ) {
						++$invoked_counter;
						return 9;
					};
				},
			)
		);
		$this->set_namespace_stack( 'myplugin' );

		$store = array(
			'state'   => $this->interactivity->state( 'myplugin' ),
			'context' => array(
				'x' => true,
				'y' => false,
				'n' => 42,
			),
		);
		$evaluator = new WP_Interactivity_Expression_Evaluator(
			function ( string $path ) use ( $store ) {
				return $this->resolve_path( $store, $path );
			}
		);
		return $evaluator;
	}

	/**
	 * Resolves a dotted path using the production shared resolver.
	 *
	 * @param array  $store Store root.
	 * @param string $path  Dotted path.
	 * @return mixed
	 */
	private function resolve_path( array $store, string $path ) {
		$method = new ReflectionMethod( $this->interactivity, 'resolve_path_with_closures' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method->invoke( $this->interactivity, $store, explode( '.', $path ), 'myplugin' );
	}

	/**
	 * @ticket 60356
	 */
	public function test_integer_vs_float_literals_with_strict_equality() {
		$invoked = 0;
		$evaluator = $this->evaluator( $invoked );
		$this->assertTrue( $evaluator->evaluate( 'state.count === 5' ) );
		$this->assertFalse( $evaluator->evaluate( 'state.count === 5.0' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_short_circuit_returns_js_semantics_operand() {
		$invoked = 0;
		$evaluator = $this->evaluator( $invoked );
		$this->assertSame( 0, $evaluator->evaluate( 'state.zero && state.flag' ) );
		$this->assertTrue( $evaluator->evaluate( 'state.flag || context.y' ) );
		$this->assertSame( 5, $evaluator->evaluate( 'state.count ?? state.flag' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_complex_boolean_logic_and_grouping() {
		$invoked = 0;
		$evaluator = $this->evaluator( $invoked );
		$this->interactivity->state(
			'myplugin',
			array(
				'a' => 1,
				'b' => 1,
				'c' => 2,
				'd' => 3,
				'e' => 5,
				'f' => 4,
			)
		);
		$store = array(
			'state'   => $this->interactivity->state( 'myplugin' ),
			'context' => array(
				'x' => true,
				'y' => false,
				'c' => true,
				'd' => false,
			),
		);
		$evaluator = new WP_Interactivity_Expression_Evaluator(
			function ( string $path ) use ( $store ) {
				return $this->resolve_path( $store, $path );
			}
		);

		$this->assertTrue( $evaluator->evaluate( 'state.a == state.b && state.c != state.d || state.e > state.f' ) );
		$this->assertTrue( $evaluator->evaluate( '(context.x || context.y) && (context.c || context.d)' ) );
		$this->assertFalse( $evaluator->evaluate( '(context.x || context.y) && (context.d && context.y)' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_bitwise_shift_exponent_and_unary_bitwise_not() {
		$invoked = 0;
		$this->interactivity->state(
			'myplugin',
			array(
				'a' => 6,
				'b' => 3,
				'shift' => 1,
				'count' => 5,
			)
		);
		$this->set_namespace_stack( 'myplugin' );
		$store = array(
			'state'   => $this->interactivity->state( 'myplugin' ),
			'context' => array(),
		);
		$evaluator = new WP_Interactivity_Expression_Evaluator(
			function ( string $path ) use ( $store ) {
				return $this->resolve_path( $store, $path );
			}
		);

		$this->assertSame( 2, $evaluator->evaluate( 'state.a & state.b' ) );
		$this->assertSame( 7, $evaluator->evaluate( 'state.a | state.b' ) );
		$this->assertSame( 5, $evaluator->evaluate( 'state.a ^ state.b' ) );
		$this->assertSame( -7, $evaluator->evaluate( '~state.a' ) );
		$this->assertSame( 12, $evaluator->evaluate( 'state.a << state.shift' ) );
		$this->assertSame( 3, $evaluator->evaluate( 'state.a >> state.shift' ) );
		$this->assertSame( 25, $evaluator->evaluate( 'state.count ** 2' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_bare_function_call_and_comma_operator_return_null() {
		$invoked = 0;
		$evaluator = $this->evaluator( $invoked );
		$this->assertNull( $evaluator->evaluate( 'doSomething()' ) );
		$this->assertNull( $evaluator->evaluate( 'Math.max(state.count, context.n)' ) );
		$this->assertNull( $evaluator->evaluate( 'state.count, context.n' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_derived_state_closure_leaf_is_invoked_and_recorded() {
		$invoked = 0;
		$evaluator = $this->evaluator( $invoked );
		$this->assertTrue( $evaluator->evaluate( 'state.value === 7' ) );
		$this->assertSame( 1, $invoked );
		$this->assertContains( 'state.value', $this->get_derived_state_closures()['myplugin'] ?? array() );
	}

	/**
	 * @ticket 60356
	 */
	public function test_mid_path_closure_is_invoked_and_prefix_recorded() {
		$invoked = 0;
		$evaluator = $this->evaluator( $invoked );
		$this->assertTrue( $evaluator->evaluate( 'state.nested.flag && context.x' ) );
		$this->assertSame( 1, $invoked );
		$this->assertContains( 'state.nested', $this->get_derived_state_closures()['myplugin'] ?? array() );
	}

	/**
	 * @ticket 60356
	 */
	public function test_closure_returning_closure_chain_is_fully_resolved() {
		$invoked = 0;
		$evaluator = $this->evaluator( $invoked );
		$this->assertTrue( $evaluator->evaluate( 'state.chain === 9' ) );
		$this->assertSame( 2, $invoked );
		$this->assertContains( 'state.chain', $this->get_derived_state_closures()['myplugin'] ?? array() );
	}

	/**
	 * @ticket 60356
	 */
	public function test_empty_input_returns_null() {
		$invoked = 0;
		$evaluator = $this->evaluator( $invoked );
		$this->assertNull( $evaluator->evaluate( '' ) );
	}
}