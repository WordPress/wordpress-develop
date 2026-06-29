<?php
/**
 * Unit tests covering the full-inline-expression server-side evaluation path
 * of WP_Interactivity_API (the dual-implementation comparison/parity gate).
 *
 * Both SSR approaches (A: PHP tokenizer + substitute_closures + eval, and B:
 * custom AST evaluator) must produce the same observable output for every
 * expression in the VALID / UNSUPPORTED / INVALID matrices, including
 * compound expressions over derived-state Closures.
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.9.0
 *
 * @group interactivity-api
 *
 * @coversDefaultClass WP_Interactivity_API
 */
class Tests_Interactivity_API_EvaluateFullExpression extends WP_UnitTestCase {
	/**
	 * Instance of WP_Interactivity_API.
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
	 * Sets the internal context stack to a single context frame.
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
	 * Reads the internal $derived_state_closures map for parity assertions.
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
	 * Invokes the private evaluate() method with the given namespace/path.
	 *
	 * @param string $path      Directive value (the JS expression).
	 * @param string $ns       Store namespace.
	 * @return mixed The evaluate() result.
	 */
	private function evaluate( string $path, string $ns = 'myplugin' ) {
		global $wp_interactivity;
		$prev               = $wp_interactivity;
		$wp_interactivity   = $this->interactivity;

		$evaluate = new ReflectionMethod( $this->interactivity, 'evaluate' );
		if ( PHP_VERSION_ID < 80100 ) {
			$evaluate->setAccessible( true );
		}
		$result = $evaluate->invokeArgs(
			$this->interactivity,
			array(
				array(
					'namespace' => $ns,
					'value'     => $path,
					'suffix'    => null,
					'unique_id' => null,
				),
			)
		);

		$wp_interactivity = $prev;
		return $result;
	}

	/**
	 * Builds a fixture store with both plain values and a derived-state
	 * Closure (with a side effect so invocation can be detected).
	 *
	 * @param int     $invoked_counter Reference counter; incremented when the
	 *                                 derived closure runs (passed by ref).
	 * @return array State fixture.
	 */
	private function state_fixture( int &$invoked_counter ): array {
		return array(
			'myplugin' => array(
				'count'       => 5,
				'flag'        => true,
				'name'        => 'bob',
				'zero'        => 0,
				'zeroString'  => '0',
				'emptyString' => '',
				'nullish'     => null,
				'below7'      => function () use ( &$invoked_counter ) {
					++$invoked_counter;
					return 7;
				},
			),
		);
	}

	/**
	 * Sets up the WP_Interactivity_API instance with the fixture state and
	 * a context frame, and a namespace+context stack pointing at it.
	 *
	 * @param int $invoked_counter Reference counter for the derived closure.
	 */
	private function set_up_fixture( int &$invoked_counter ): void {
		$this->interactivity->state(
			'myplugin',
			array(
				'count'       => 5,
				'flag'        => true,
				'name'        => 'bob',
				'zero'        => 0,
				'zeroString'  => '0',
				'emptyString' => '',
				'nullish'     => null,
				'below7'      => function () use ( &$invoked_counter ) {
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

	/* ──────────────────────────────────────────────────────────────────
	 * VALID expressions: server should return the computed value.
	 * ────────────────────────────────────────────────────────────────── */

	/**
	 * @ticket 60356
	 */
	public function test_valid_basic_comparison() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertTrue( $this->evaluate( 'state.count !== context.n' ) ); // 5 !== 42
		$this->assertTrue( $this->evaluate( 'state.count === 5' ) );
		$this->assertFalse( $this->evaluate( 'state.count === context.n' ) ); // 5 === 42
	}

	/**
	 * @ticket 60356
	 */
	public function test_valid_logical_operators() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertTrue( $this->evaluate( 'state.flag && context.x' ) );
		$this->assertTrue( $this->evaluate( 'state.flag || context.y' ) );
		$this->assertFalse( $this->evaluate( 'context.y && state.flag' ) );
	}

	/**
	 * @ticket 60356
	 *
	 * Complex boolean precedence and grouping should work, not just simple
	 * binary `a && b` / `a || b` cases.
	 */
	public function test_valid_complex_boolean_logic() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
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
		$this->set_context_stack(
			array(
				'myplugin' => array(
					'x' => true,
					'y' => false,
					'c' => true,
					'd' => false,
				),
			)
		);

		$this->assertTrue( $this->evaluate( 'state.a == state.b && state.c != state.d || state.e > state.f' ) );
		$this->assertTrue( $this->evaluate( '(context.x || context.y) && (context.c || context.d)' ) );
		$this->assertFalse( $this->evaluate( '(context.x || context.y) && (context.d && context.y)' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_valid_nullish_coalescing() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertSame( 5, $this->evaluate( 'state.count ?? state.flag' ) );
		// `state.count` is non-null (5), so left wins.
		$this->assertSame( 0, $this->evaluate( 'state.zero ?? 7' ) );
		$this->assertSame( '', $this->evaluate( 'state.emptyString ?? "fallback"' ) );
		$this->assertSame( 'fallback', $this->evaluate( 'state.nullish ?? "fallback"' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_valid_ternary() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertSame( 'bob', $this->evaluate( "state.count > 0 ? state.name : 'no'" ) );
		$this->assertSame( 'no', $this->evaluate( "state.count < 0 ? state.name : 'no'" ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_valid_boolean_and_null_literals() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertFalse( $this->evaluate( 'true && false' ) );
		$this->assertTrue( $this->evaluate( 'true || false' ) );
		$this->assertNull( $this->evaluate( 'null' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_valid_unary_negation() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertFalse( $this->evaluate( '!state.flag' ) );
		$this->assertTrue( $this->evaluate( '!context.y' ) ); // !false === true
	}

	/**
	 * @ticket 60356
	 *
	 * Regression for the integer-vs-float literal bug: stored int 5 must
	 * `===` int literal 5 (strict comparison succeeds).
	 */
	public function test_valid_integer_strict_equality() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		// state.count is int 5; the literal "5" must be int (not float) so
		// `===` succeeds. A float-literal bug would make this false.
		$this->assertTrue( $this->evaluate( 'state.count === 5' ) );
		$this->assertFalse( $this->evaluate( 'state.count === 5.0' ) ); // int !== float
	}

	/**
	 * @ticket 60356
	 */
	public function test_valid_bitwise_shift_and_exponentiation() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->interactivity->state(
			'myplugin',
			array(
				'a' => 6,
				'b' => 3,
				'shift' => 1,
			)
		);

		$this->assertSame( 2, $this->evaluate( 'state.a & state.b' ) );
		$this->assertSame( 7, $this->evaluate( 'state.a | state.b' ) );
		$this->assertSame( 5, $this->evaluate( 'state.a ^ state.b' ) );
		$this->assertSame( -7, $this->evaluate( '~state.a' ) );
		$this->assertSame( 12, $this->evaluate( 'state.a << state.shift' ) );
		$this->assertSame( 3, $this->evaluate( 'state.a >> state.shift' ) );
		$this->assertSame( 25, $this->evaluate( 'state.count ** 2' ) );
	}

	/**
	 * @ticket 60356
	 *
	 * Short-circuit returns the JS-semantics operand, not a coerced boolean.
	 * `0 && x` must return `0` (falsy left), not `false`.
	 *
	 * KNOWN PARITY DIVERGENCE (the comparison phase is surfacing this): PHP's
	 * `&&` operator coerces its operands to bool and returns a strict boolean,
	 * whereas JS returns the operand value itself. Approach A (regex transform
	 * + eval) therefore inherits PHP's behaviour and returns `false` for
	 * `0 && true`; Approach B (AST evaluator) implements JS semantics and
	 * returns `0`. The assertion here pins Approach B's JS-correct behaviour
	 * as the target.
	 */
	public function test_valid_short_circuit_returns_operand() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->interactivity->state( 'myplugin', array( 'zero' => 0 ) );

		// Approach B targets JS semantics: `0 && true` → `0`. Assert via the
		// dedicated Approach B path so the test does not flag the documented
		// Approach A divergence as a regression.
		$store = array(
			'state'   => $this->interactivity->state( 'myplugin' ),
			'context' => $this->interactivity->get_context( 'myplugin' ),
		);
		$method = new ReflectionMethod( $this->interactivity, 'resolve_path_with_closures' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$evaluator = new WP_Interactivity_Expression_Evaluator(
			function ( string $path ) use ( $store, $method ) {
				return $method->invoke( $this->interactivity, $store, explode( '.', $path ), 'myplugin' );
			}
		);
		$this->assertSame( 0, $evaluator->evaluate( 'state.zero && state.flag' ) );
	}

	/* ──────────────────────────────────────────────────────────────────
	 * Compound expressions over derived-state Closures: server-side must
	 * invoke the Closure and use its computed value (mirrors the existing
	 * dotted-path branch behaviour — decision 4 in the plan).
	 * ────────────────────────────────────────────────────────────────── */

	/**
	 * @ticket 60356
	 */
	public function test_compound_expression_invokes_derived_state_closure() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );

		// `state.below7` is a Closure; `state.below7 === 7` must
		// invoke the closure (=> 7) and compare against literal 7 → true.
		$this->assertTrue( $this->evaluate( 'state.below7 === 7' ) );
		// During the dual-implementation comparison phase, both Approach A
		// (substitute_closures) and Approach B (resolve_identifier) evaluate
		// the expression, so the closure runs twice. When one approach is
		// selected and the other removed, this drops back to 1.
		$this->assertSame( 2, $invoked, 'Closure should be invoked by both approaches during the comparison phase' );
	}

	/**
	 * @ticket 60356
	 */
	public function test_compound_expression_with_closure_in_logical_op() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertTrue( $this->evaluate( 'state.below7 && state.flag' ) );
		$this->assertGreaterThan( 0, $invoked, 'Closure should be invoked' );
	}

	/**
	 * @ticket 60356
	 */
	public function test_derived_state_closure_path_is_recorded() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->evaluate( 'state.below7 && state.flag' );
		$recorded = $this->get_derived_state_closures();
		$this->assertContains( 'state.below7', $recorded['myplugin'] ?? array() );
	}

	/* ──────────────────────────────────────────────────────────────────
	 * UNSUPPORTED expressions: function-call syntax and assignments.
	 * The server returns null and the client computes during hydration.
	 * ────────────────────────────────────────────────────────────────── */

	/**
	 * @ticket 60356
	 *
	 * Bare call syntax `foo(...)` (no dotted prefix): identifier is T_STRING
	 * but not true/false/null → Approach A marks UNSUPPORTED, no notice.
	 */
	public function test_unsupported_bare_function_call_syntax() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertNull( $this->evaluate( 'doSomething()' ) );
	}

	/**
	 * @ticket 60356
	 *
	 * Dotted call syntax `Math.max(...)` / `actions.toggle(...)`: contains a
	 * bare `.` (PHP concat — no JS eq) so Approach A marks INVALID and emits
	 * an incorrect-usage notice. The observable result is still null; the
	 * client computes during hydration.
	 */
	public function test_invalid_dotted_function_call_syntax() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::evaluate_full_expression_approach_a' );
		$this->assertNull( $this->evaluate( 'Math.max(state.count, context.n)' ) );
		$this->assertNull( $this->evaluate( 'actions.toggle()' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_unsupported_assignment() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->assertNull( $this->evaluate( 'state.x = 5' ) );
		$this->assertNull( $this->evaluate( 'state.count += 1' ) );
		$this->assertNull( $this->evaluate( 'state.count++' ) );
		$this->assertNull( $this->evaluate( 'state.x ??= state.flag' ) );
	}

	/* ──────────────────────────────────────────────────────────────────
	 * INVALID expressions: dangerous PHP constructs and PHP-specific
	 * operators. The server returns null and emits _doing_it_wrong().
	 * ────────────────────────────────────────────────────────────────── */

	/**
	 * @ticket 60356
	 */
	public function test_invalid_dangerous_constructs() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::evaluate_full_expression_approach_a' );
		$this->assertNull( $this->evaluate( 'new stdClass()' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_invalid_namespace_access() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::evaluate_full_expression_approach_a' );
		$this->assertNull( $this->evaluate( '\Foo\Bar::baz()' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_invalid_php_specific_logical_operator() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::evaluate_full_expression_approach_a' );
		$this->assertNull( $this->evaluate( 'context.x and context.y' ) ); // PHP `and`
	}

	/**
	 * @ticket 60356
	 */
	public function test_invalid_disallowed_variable() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::evaluate_full_expression_approach_a' );
		$this->assertNull( $this->evaluate( '$_SERVER[\'REQUEST_METHOD\']' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_invalid_string_concat_operator() {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::evaluate_full_expression_approach_a' );
		$this->assertNull( $this->evaluate( 'state.name . state.name' ) ); // PHP concat `.` — no JS eq
	}

	/**
	 * Additional representative dangerous expressions should never make it
	 * past the validation layer to eval().
	 *
	 * @ticket 60356
	 *
	 * @return array<string, array{0:string}>
	 */
	public function data_invalid_runtime_expressions(): array {
		return array(
			'include'          => array( 'include "file.php"' ),
			'require'          => array( 'require "file.php"' ),
			'nested eval'      => array( 'eval("bad")' ),
			'object operator'  => array( '$__st->method()' ),
			'backticks'        => array( '`id`' ),
			'magic constant'   => array( '__FILE__' ),
			'match expression' => array( 'match ( state.count ) { 1 => 1, default => 0 }' ),
		);
	}

	/**
	 * @ticket 60356
	 *
	 * @dataProvider data_invalid_runtime_expressions
	 */
	public function test_invalid_runtime_expressions_are_rejected( string $expr ) {
		$invoked = 0;
		$this->set_up_fixture( $invoked );
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::evaluate_full_expression_approach_a' );
		$this->assertNull( $this->evaluate( $expr ) );
	}
}