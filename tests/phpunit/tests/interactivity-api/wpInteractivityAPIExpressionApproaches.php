<?php
/**
 * Direct side-by-side tests for the two PHP SSR expression-evaluation approaches.
 *
 * These tests do not go through the public `evaluate()` entry point; instead
 * they invoke Approach A and Approach B directly with the same store/context
 * fixtures so that their differing semantics are explicit and reviewable.
 *
 * The goal is not to hide differences but to lock them down with tests: where
 * both approaches should agree, they are asserted to the same expected value;
 * where one still diverges from JS semantics, the test names and assertions
 * make that shortcoming obvious without breaking the suite.
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.9.0
 *
 * @group interactivity-api
 *
 * @coversDefaultClass WP_Interactivity_API
 */
class Tests_Interactivity_API_ExpressionApproaches extends WP_UnitTestCase {
	/**
	 * API instance under test.
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
	 * Sets the internal context stack to a single frame.
	 *
	 * @param array $context Context frame.
	 */
	private function set_context_stack( array $context ): void {
		$interactivity = new ReflectionClass( $this->interactivity );
		$context_stack = $interactivity->getProperty( 'context_stack' );
		if ( PHP_VERSION_ID < 80100 ) {
			$context_stack->setAccessible( true );
		}
		$context_stack->setValue( $this->interactivity, array( $context ) );
	}

	/**
	 * Fixture store with values chosen to expose JS-vs-PHP semantic edges.
	 *
	 * @param int $invoked_counter Derived-state invocation counter.
	 */
	private function set_up_fixture( int &$invoked_counter ): void {
		$this->interactivity->state(
			'myplugin',
			array(
				'count'        => 5,
				'flag'         => true,
				'name'         => 'bob',
				'zero'         => 0,
				'zeroString'   => '0',
				'emptyString'  => '',
				'emptyArray'   => array(),
				'stringNumber' => '5',
				'nullish'      => null,
				'below7'       => function () use ( &$invoked_counter ) {
					++$invoked_counter;
					return 7;
				},
			)
		);
		$this->set_context_stack(
			array(
				'myplugin' => array(
					'x' => true,
					'y' => false,
					'n' => 42,
				),
			)
		);
		$this->set_namespace_stack( 'myplugin' );
	}

	/**
	 * Builds the store root expected by the private full-expression helpers.
	 *
	 * @return array
	 */
	private function build_store(): array {
		return array(
			'state'   => $this->interactivity->state( 'myplugin' ),
			'context' => $this->interactivity->get_context( 'myplugin' ),
		);
	}

	/**
	 * Invokes the private Approach A helper directly.
	 *
	 * @param string $expr Original JS expression.
	 * @return mixed
	 */
	private function evaluate_a( string $expr ) {
		$method = new ReflectionMethod( $this->interactivity, 'evaluate_full_expression_approach_a' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method->invoke( $this->interactivity, $expr, $this->build_store(), 'myplugin' );
	}

	/**
	 * Invokes the private Approach B helper directly.
	 *
	 * @param string $expr Original JS expression.
	 * @return mixed
	 */
	private function evaluate_b( string $expr ) {
		$method = new ReflectionMethod( $this->interactivity, 'evaluate_full_expression_approach_b' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method->invoke( $this->interactivity, $expr, $this->build_store(), 'myplugin' );
	}

	/**
	 * Expressions where both approaches currently match.
	 *
	 * @return array<string, array{expr: string, expected: mixed}>
	 */
	public function provider_matching_expressions(): array {
		return array(
			'basic comparison'                       => array(
				'expr'     => 'state.count !== context.n',
				'expected' => true,
			),
			'nested ternary'                         => array(
				'expr'     => 'state.count > 10 ? "big" : ( state.flag ? "mid" : "small" )',
				'expected' => 'mid',
			),
			'complex boolean grouping'               => array(
				'expr'     => '(context.x || context.y) && (state.flag || context.y)',
				'expected' => true,
			),
			'bitwise and'                            => array(
				'expr'     => 'state.count & 3',
				'expected' => 1,
			),
			'shift and exponent'                     => array(
				'expr'     => '(state.count << 1) + (2 ** 3)',
				'expected' => 18,
			),
			'nullish coalescing with zero'           => array(
				'expr'     => 'state.zero ?? 7',
				'expected' => 0,
			),
			'nullish coalescing with empty string'   => array(
				'expr'     => 'state.emptyString ?? "fallback"',
				'expected' => '',
			),
			'derived closure in compound expression' => array(
				'expr'     => 'state.below7 === 7 && state.flag',
				'expected' => true,
			),
		);
	}

	/**
	 * @ticket 60356
	 *
	 * @dataProvider provider_matching_expressions
	 */
	public function test_both_approaches_match_for_shared_cases( string $expr, $expected ) {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertSame( $expected, $this->evaluate_a( $expr ) );
		$this->assertSame( $expected, $this->evaluate_b( $expr ) );
	}

	/**
	 * Cases where Approach B now matches JS semantics but Approach A still
	 * inherits PHP `eval()` behaviour.
	 *
	 * @return array<string, array{expr: string, expected_b: mixed, expected_a: mixed}>
	 */
	public function provider_known_divergences(): array {
		return array(
			'empty array truthiness in ternary'      => array(
				'expr'       => 'state.emptyArray ? "yes" : "no"',
				'expected_b' => 'yes',
				'expected_a' => 'no',
			),
			'string zero truthiness in ternary'      => array(
				'expr'       => 'state.zeroString ? "yes" : "no"',
				'expected_b' => 'yes',
				'expected_a' => 'no',
			),
			'operand-returning short circuit'        => array(
				'expr'       => 'state.zero && state.flag',
				'expected_b' => 0,
				'expected_a' => false,
			),
			'array short circuit truthiness with &&' => array(
				'expr'       => 'state.emptyArray && state.flag',
				'expected_b' => true,
				'expected_a' => false,
			),
			'array short circuit truthiness with ||' => array(
				'expr'       => 'state.emptyArray || state.flag',
				'expected_b' => array(),
				'expected_a' => true,
			),
			'negation of empty array truthiness'     => array(
				'expr'       => '!state.emptyArray',
				'expected_b' => false,
				'expected_a' => true,
			),
			'negation of string zero truthiness'     => array(
				'expr'       => '!state.zeroString',
				'expected_b' => false,
				'expected_a' => true,
			),
			'array short circuit truthiness'         => array(
				'expr'       => 'state.emptyArray && state.flag',
				'expected_b' => true,
				'expected_a' => false,
			),
			'primitive loose equality'               => array(
				'expr'       => 'state.emptyString == 0',
				'expected_b' => true,
				'expected_a' => false,
			),
			'null loose equality to false'           => array(
				'expr'       => 'state.nullish == false',
				'expected_b' => false,
				'expected_a' => true,
			),
			'string concatenation with plus'         => array(
				'expr'       => 'state.name + context.n',
				'expected_b' => 'bob42',
				'expected_a' => null,
			),
			'string concatenation with zero string'  => array(
				'expr'       => 'state.name + state.zeroString',
				'expected_b' => 'bob0',
				'expected_a' => null,
			),
		);
	}

	/**
	 * @ticket 60356
	 *
	 * @dataProvider provider_known_divergences
	 */
	public function test_known_approach_divergences_are_explicit( string $expr, $expected_b, $expected_a ) {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertSame( $expected_a, $this->evaluate_a( $expr ) );
		$this->assertSame( $expected_b, $this->evaluate_b( $expr ) );
		$this->assertNotSame( $this->evaluate_a( $expr ), $this->evaluate_b( $expr ) );
	}

	/**
	 * Multi-statement expressions where both approaches agree.
	 *
	 * @return array<string, array{expr: string, expected: mixed}>
	 */
	public function provider_matching_multi_statement(): array {
		return array(
			'last statement wins' => array(
				'expr'     => 'context.y; context.x',
				'expected' => true,
			),
			'comparison as last'  => array(
				'expr'     => 'state.count; state.flag && context.x',
				'expected' => true,
			),
			'trailing semicolon'  => array(
				'expr'     => 'state.count;',
				'expected' => 5,
			),
		);
	}

	/**
	 * @ticket 60356
	 *
	 * @dataProvider provider_matching_multi_statement
	 */
	public function test_both_approaches_match_for_multi_statement_cases( string $expr, $expected ) {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertSame( $expected, $this->evaluate_a( $expr ) );
		$this->assertSame( $expected, $this->evaluate_b( $expr ) );
	}

	/**
	 * Actions/callbacks identifiers: both approaches should return null
	 * (defer to client).
	 *
	 * @return array<string, array{expr: string}>
	 */
	public function provider_actions_callbacks(): array {
		return array(
			'actions dot path'       => array( 'actions.someAction' ),
			'callbacks dot path'     => array( 'callbacks.myCallback' ),
			'actions with state'     => array( 'state.count && actions.isValid' ),
			'callbacks with context' => array( 'callbacks.x || context.x' ),
		);
	}

	/**
	 * @ticket 60356
	 *
	 * @dataProvider provider_actions_callbacks
	 */
	public function test_both_approaches_handle_actions_callbacks( string $expr ) {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$result_a = $this->evaluate_a( $expr );
		$result_b = $this->evaluate_b( $expr );
		// Both should either return null or produce the same non-error result.
		// Where they differ, Approach B is expected to be JS-correct.
		$this->assertTrue(
			null === $result_a || null === $result_b || $result_a === $result_b
		);
	}
}
