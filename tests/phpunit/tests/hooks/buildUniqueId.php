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
