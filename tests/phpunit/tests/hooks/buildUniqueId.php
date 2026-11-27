<?php

/**
 * Tests for _wp_filter_build_unique_id().
 *
 * @group hooks
 * @covers ::_wp_filter_build_unique_id
 */
class Tests_Hooks_BuildUniqueId extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once __DIR__ . '/../../includes/mock-invokable.php';
	}

	/**
	 * @ticket 58291
	 * @ticket 65919
	 */
	public function test_string_callback_returns_string(): void {
		$result = _wp_filter_build_unique_id( '', '__return_null', 10 );
		$this->assertIsNonDecimalIntString( $result );
		$this->assertSame( '__return_null', $result );
	}

	/**
	 * @ticket 58291
	 * @ticket 65919
	 */
	public function test_closure_returns_string(): void {
		$cb     = function (): void {};
		$result = _wp_filter_build_unique_id( '', $cb, 10 );
		$this->assertIsNonDecimalIntString( $result );
	}

	/**
	 * @ticket 58291
	 * @ticket 65919
	 */
	public function test_invokable_object_returns_string(): void {
		$result = _wp_filter_build_unique_id( '', new Mock_Invokable(), 10 );
		$this->assertIsNonDecimalIntString( $result );
	}

	/**
	 * @ticket 58291
	 * @ticket 65919
	 */
	public function test_object_callback_returns_string(): void {
		$a      = new MockAction();
		$result = _wp_filter_build_unique_id( '', array( $a, 'action' ), 10 );
		$this->assertIsNonDecimalIntString( $result );
	}

	/**
	 * @ticket 58291
	 * @ticket 65919
	 */
	public function test_static_callback_returns_string(): void {
		$result = _wp_filter_build_unique_id( '', array( 'MockAction', 'action' ), 10 );
		$this->assertIsNonDecimalIntString( $result );
	}

	/**
	 * @ticket 58291
	 */
	public function test_two_different_objects_produce_different_ids(): void {
		$a = new MockAction();
		$b = new MockAction();
		$this->assertNotSame(
			_wp_filter_build_unique_id( '', array( $a, 'action' ), 10 ),
			_wp_filter_build_unique_id( '', array( $b, 'action' ), 10 )
		);
	}

	/**
	 * @ticket 58291
	 */
	public function test_same_object_produces_same_id(): void {
		$a = new MockAction();
		$this->assertSame(
			_wp_filter_build_unique_id( '', array( $a, 'action' ), 10 ),
			_wp_filter_build_unique_id( '', array( $a, 'action' ), 10 )
		);
	}

	/**
	 * @ticket 58291
	 */
	public function test_malformed_array_missing_method_returns_null(): void {
		$a      = new MockAction();
		$result = _wp_filter_build_unique_id( '', array( $a ), 10 );
		$this->assertNull( $result );
	}

	/**
	 * @ticket 58291
	 */
	public function test_malformed_array_non_string_method_returns_null(): void {
		$a      = new MockAction();
		$result = _wp_filter_build_unique_id( '', array( $a, 123 ), 10 );
		$this->assertNull( $result );
	}

	/**
	 * Static method used as a hook callback in the tests below.
	 */
	public static function static_callback(): void {}

	/**
	 * Closure::fromCallable() creates the same kind of closure as PHP 8.1's `$object->method(...)` syntax,
	 * which can't be used here while PHP 7.4 is supported.
	 *
	 * @ticket 59126
	 */
	public function test_closure_from_bound_method_gets_array_callback_id(): void {
		$a      = new MockAction();
		$result = _wp_filter_build_unique_id( '', Closure::fromCallable( array( $a, 'action' ) ), 10 );

		$this->assertIsNonDecimalIntString( $result );
		$this->assertSame( _wp_filter_build_unique_id( '', array( $a, 'action' ), 10 ), $result );
	}

	/**
	 * @ticket 59126
	 */
	public function test_closures_from_same_bound_method_get_same_id(): void {
		$a = new MockAction();
		// Both closures are kept alive, so PHP can't reuse the object ID of the first for the second.
		$first  = Closure::fromCallable( array( $a, 'action' ) );
		$second = Closure::fromCallable( array( $a, 'action' ) );

		$this->assertNotSame( $first, $second, 'Each Closure::fromCallable() call should create a new closure.' );
		$this->assertSame(
			_wp_filter_build_unique_id( '', $first, 10 ),
			_wp_filter_build_unique_id( '', $second, 10 )
		);
	}

	/**
	 * @ticket 59126
	 */
	public function test_closures_from_same_method_of_different_objects_get_different_ids(): void {
		$a          = new MockAction();
		$b          = new MockAction();
		$a_callback = Closure::fromCallable( array( $a, 'action' ) );
		$b_callback = Closure::fromCallable( array( $b, 'action' ) );

		$this->assertNotSame(
			_wp_filter_build_unique_id( '', $a_callback, 10 ),
			_wp_filter_build_unique_id( '', $b_callback, 10 )
		);
	}

	/**
	 * @ticket 59126
	 */
	public function test_closure_from_static_method_gets_static_callback_id(): void {
		$result = _wp_filter_build_unique_id( '', Closure::fromCallable( array( __CLASS__, 'static_callback' ) ), 10 );

		$this->assertIsNonDecimalIntString( $result );
		$this->assertSame( __CLASS__ . '::static_callback', $result );
	}

	/**
	 * @ticket 59126
	 */
	public function test_closure_from_function_gets_function_name(): void {
		$this->assertSame( '__return_true', _wp_filter_build_unique_id( '', Closure::fromCallable( '__return_true' ), 10 ) );
	}

	/**
	 * Anonymous functions are all named "{closure}" before PHP 8.4, so they must keep their object IDs.
	 *
	 * @ticket 59126
	 */
	public function test_anonymous_closures_in_same_scope_get_different_ids(): void {
		$first  = function (): void {};
		$second = function (): void {};

		$this->assertNotSame(
			_wp_filter_build_unique_id( '', $first, 10 ),
			_wp_filter_build_unique_id( '', $second, 10 )
		);
	}

	/**
	 * @ticket 59126
	 *
	 * @covers ::add_filter
	 * @covers ::has_filter
	 * @covers ::remove_filter
	 */
	public function test_closure_from_bound_method_can_be_removed_with_new_closure(): void {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_filter( $hook_name, Closure::fromCallable( array( $a, 'filter' ) ) );

		$this->assertSame( 10, has_filter( $hook_name, Closure::fromCallable( array( $a, 'filter' ) ) ), 'The callback should be found with a new closure.' );
		$this->assertSame( 10, has_filter( $hook_name, array( $a, 'filter' ) ), 'The callback should be found with the array form.' );
		$this->assertTrue( remove_filter( $hook_name, Closure::fromCallable( array( $a, 'filter' ) ) ), 'The callback should be removed with a new closure.' );
		$this->assertFalse( has_filter( $hook_name ) );
	}

	/**
	 * Asserts that a value is a string which PHP does not cast to an integer when used as an array key.
	 *
	 * The return value of _wp_filter_build_unique_id() is used as the callback key in WP_Hook::$callbacks.
	 * PHP silently casts an array key from string to int when the string is the canonical decimal
	 * representation of an integer, which changes the type of the keys that consumers of that public
	 * property read back. This is the runtime equivalent of PHPStan's `non-decimal-int-string` type.
	 *
	 * @param mixed $value Value to check.
	 */
	private function assertIsNonDecimalIntString( $value ): void {
		$this->assertIsString( $value, 'The unique ID is not a string.' );

		$array = array( $value => true );

		$this->assertIsString(
			array_key_first( $array ),
			sprintf( 'The unique ID "%s" was cast to an integer when used as an array key.', $value )
		);
	}
}
