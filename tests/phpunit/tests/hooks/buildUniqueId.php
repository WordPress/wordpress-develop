<?php

/**
 * Tests for _wp_filter_build_unique_id().
 *
 * @group hooks
 * @covers ::_wp_filter_build_unique_id
 */
class Tests_Hooks_BuildUniqueId extends WP_UnitTestCase {

	public function test_string_callback_returns_string(): void {
		$result = _wp_filter_build_unique_id( '', '__return_null', 10 );
		$this->assertIsString( $result );
		$this->assertSame( '__return_null', $result );
	}

	public function test_closure_returns_string(): void {
		$cb     = function (): void {};
		$result = _wp_filter_build_unique_id( '', $cb, 10 );
		$this->assertIsString( $result );
	}

	public function test_object_callback_returns_string(): void {
		$a      = new MockAction();
		$result = _wp_filter_build_unique_id( '', array( $a, 'action' ), 10 );
		$this->assertIsString( $result );
	}

	public function test_static_callback_returns_string(): void {
		$result = _wp_filter_build_unique_id( '', array( 'MockAction', 'action' ), 10 );
		$this->assertIsString( $result );
	}

	public function test_two_different_objects_produce_different_ids(): void {
		$a = new MockAction();
		$b = new MockAction();
		$this->assertNotSame(
			_wp_filter_build_unique_id( '', array( $a, 'action' ), 10 ),
			_wp_filter_build_unique_id( '', array( $b, 'action' ), 10 )
		);
	}

	public function test_same_object_produces_same_id(): void {
		$a = new MockAction();
		$this->assertSame(
			_wp_filter_build_unique_id( '', array( $a, 'action' ), 10 ),
			_wp_filter_build_unique_id( '', array( $a, 'action' ), 10 )
		);
	}

	public function test_malformed_array_missing_method_returns_null(): void {
		$a      = new MockAction();
		$result = _wp_filter_build_unique_id( '', array( $a ), 10 );
		$this->assertNull( $result );
	}

	public function test_malformed_array_non_string_method_returns_null(): void {
		$a      = new MockAction();
		$result = _wp_filter_build_unique_id( '', array( $a, 123 ), 10 );
		$this->assertNull( $result );
	}
}
