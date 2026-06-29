<?php
/**
 * Unit tests for WP_Interactivity_API::substitute_closures().
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.9.0
 *
 * @group interactivity-api
 *
 * @coversDefaultClass WP_Interactivity_API
 */
class Tests_Interactivity_API_SubstituteClosures extends WP_UnitTestCase {
	/**
	 * Instance under test.
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
	 * Invokes the private substitute_closures() helper.
	 *
	 * @param string $php_expr Post-regex-transform PHP expression.
	 * @param array  $store    Store root.
	 * @param string $ns       Store namespace.
	 * @return string|null Rewritten expression or null on failure.
	 */
	private function substitute_closures( string $php_expr, array $store, string $ns ) {
		$method = new ReflectionMethod( $this->interactivity, 'substitute_closures' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method->invoke( $this->interactivity, $php_expr, $store, $ns );
	}

	/**
	 * @ticket 60356
	 */
	public function test_substitutes_leaf_closure_with_json_literal() {
		global $wp_interactivity;
		$wp_interactivity = $this->interactivity;

		$invoked = 0;
		$this->interactivity->state(
			'myplugin',
			array(
				'other' => 7,
				'value' => function () use ( &$invoked ) {
					++$invoked;
					// Namespace stack must be pushed so state() without an
					// explicit namespace reads the current store.
					$state = wp_interactivity()->state();
					return $state['other'];
				},
			)
		);
		$this->set_namespace_stack( 'myplugin' );

		$store = array(
			'state'   => $this->interactivity->state( 'myplugin' ),
			'context' => array(),
		);
		$result = $this->substitute_closures( '$__st[\'value\'] === 7', $store, 'myplugin' );
		$this->assertSame( '7 === 7', $result );
		$this->assertSame( 1, $invoked );
		$this->assertContains( 'state.value', $this->get_derived_state_closures()['myplugin'] ?? array() );
	}

	/**
	 * @ticket 60356
	 */
	public function test_substitutes_mid_path_closure_and_records_prefix() {
		$invoked = 0;
		$this->interactivity->state(
			'myplugin',
			array(
				'nested' => function () use ( &$invoked ) {
					++$invoked;
					return array( 'flag' => true );
				},
			)
		);
		$this->set_namespace_stack( 'myplugin' );

		$store = array(
			'state'   => $this->interactivity->state( 'myplugin' ),
			'context' => array( 'x' => true ),
		);
		$result = $this->substitute_closures( '$__st[\'nested\'][\'flag\'] && $__ctx[\'x\']', $store, 'myplugin' );
		$this->assertSame( 'true && true', $result );
		$this->assertSame( 1, $invoked );
		$this->assertContains( 'state.nested', $this->get_derived_state_closures()['myplugin'] ?? array() );
	}

	/**
	 * @ticket 60356
	 */
	public function test_closure_returning_closure_is_fully_resolved() {
		$invoked = 0;
		$this->interactivity->state(
			'myplugin',
			array(
				'value' => function () use ( &$invoked ) {
					++$invoked;
					return function () use ( &$invoked ) {
						++$invoked;
						return 9;
					};
				},
			)
		);
		$this->set_namespace_stack( 'myplugin' );

		$store = array(
			'state'   => $this->interactivity->state( 'myplugin' ),
			'context' => array(),
		);
		$result = $this->substitute_closures( '$__st[\'value\'] === 9', $store, 'myplugin' );
		$this->assertSame( '9 === 9', $result );
		$this->assertSame( 2, $invoked );
		$this->assertContains( 'state.value', $this->get_derived_state_closures()['myplugin'] ?? array() );
	}

	/**
	 * @ticket 60356
	 * @expectedIncorrectUsage WP_Interactivity_API::substitute_closures
	 */
	public function test_closure_that_throws_aborts_the_whole_expression() {
		$this->interactivity->state(
			'myplugin',
			array(
				'bad' => function () {
					throw new Error( 'Boom' );
				},
			)
		);
		$this->set_namespace_stack( 'myplugin' );

		$store = array(
			'state'   => $this->interactivity->state( 'myplugin' ),
			'context' => array(),
		);
		$this->assertNull( $this->substitute_closures( '$__st[\'bad\'] === 1', $store, 'myplugin' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_non_json_encodable_value_aborts_the_whole_expression() {
		$resource = fopen( 'php://temp', 'r' );
		$this->interactivity->state(
			'myplugin',
			array(
				'bad' => function () use ( $resource ) {
					return $resource;
				},
			)
		);
		$this->set_namespace_stack( 'myplugin' );

		$store = array(
			'state'   => $this->interactivity->state( 'myplugin' ),
			'context' => array(),
		);
		$this->assertNull( $this->substitute_closures( '$__st[\'bad\'] === 1', $store, 'myplugin' ) );
		fclose( $resource );
	}
}