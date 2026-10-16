<?php

/**
 * Tests for the behavior of `wp_cache_get_valid()`
 *
 * @group functions
 * @group cache
 *
 * @covers ::wp_cache_get_valid
 */
class Tests_Functions_wpCacheGetValid extends WP_UnitTestCase {

	/**
	 * Test that contents of an expected type are returned.
	 *
	 * @dataProvider data_valid_type_returns_contents
	 *
	 * @param mixed           $value The value to store in the cache.
	 * @param string|string[] $type  The expected type(s) to pass to wp_cache_get_valid().
	 *
	 * @ticket 66005
	 */
	public function test_valid_type_returns_contents( $value, $type ): void {
		wp_cache_set( 'cache_key', $value, 'cache_group' );

		$this->assertSame( $value, wp_cache_get_valid( 'cache_key', 'cache_group', $type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Data provider.
	 */
	public function data_valid_type_returns_contents(): array {
		return array(
			'array'                    => array( array( 1, 2, 3 ), 'array' ),
			'empty array'              => array( array(), 'array' ),
			'string'                   => array( 'a string', 'string' ),
			'empty string'             => array( '', 'string' ),
			'int'                      => array( 123, 'int' ),
			'integer'                  => array( 123, 'integer' ),
			'zero'                     => array( 0, 'int' ),
			'float'                    => array( 1.5, 'float' ),
			'double'                   => array( 1.5, 'double' ),
			'numeric string'           => array( '123', 'numeric' ),
			'bool true'                => array( true, 'bool' ),
			'boolean true'             => array( true, 'boolean' ),
			'array in type list'       => array( array( 1, 2, 3 ), array( 'object', 'array' ) ),
			'single type in list form' => array( 'a string', array( 'string' ) ),
		);
	}

	/**
	 * Test that objects of the expected type are returned.
	 *
	 * @ticket 66005
	 */
	public function test_valid_object_returns_contents(): void {
		$value      = new stdClass();
		$value->foo = 'bar';

		wp_cache_set( 'cache_key', $value, 'cache_group' );

		$result = wp_cache_get_valid( 'cache_key', 'cache_group', 'object' );

		$this->assertInstanceOf( stdClass::class, $result );
		$this->assertSame( 'bar', $result->foo );
	}

	/**
	 * Test that a cached boolean false is returned with $valid set to true.
	 *
	 * A false return is ambiguous for the 'bool' type, so callers rely on
	 * the $valid reference parameter instead.
	 *
	 * @ticket 66005
	 */
	public function test_cached_false_sets_valid_true(): void {
		wp_cache_set( 'cache_key', false, 'cache_group' );

		$valid  = null;
		$result = wp_cache_get_valid( 'cache_key', 'cache_group', 'bool', '', false, false, $valid );

		$this->assertFalse( $result );
		$this->assertTrue( $valid );
	}

	/**
	 * Test that $valid is set to true when the contents pass validation.
	 *
	 * @ticket 66005
	 */
	public function test_valid_is_true_on_success(): void {
		wp_cache_set( 'cache_key', array( 1, 2, 3 ), 'cache_group' );

		$valid = null;
		wp_cache_get_valid( 'cache_key', 'cache_group', 'array', '__return_true', false, false, $valid );

		$this->assertTrue( $valid );
	}

	/**
	 * Test that $valid is reset to false in every failure case.
	 *
	 * @dataProvider data_valid_is_false_on_failure
	 *
	 * @param callable $scenario Callback that performs the failing call, receiving $valid by reference.
	 *
	 * @ticket 66005
	 */
	public function test_valid_is_false_on_failure( callable $scenario ): void {
		// Pre-set to true to confirm the function resets the reference.
		$valid = true;

		$scenario( $valid );

		$this->assertFalse( $valid );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Data provider.
	 */
	public function data_valid_is_false_on_failure(): array {
		return array(
			'cache miss'         => array(
				static function ( &$valid ): void {
					wp_cache_get_valid( 'missing_key', 'cache_group', 'array', '', false, false, $valid );
				},
			),
			'type mismatch'      => array(
				static function ( &$valid ): void {
					wp_cache_set( 'cache_key', 'a string', 'cache_group' );
					wp_cache_get_valid( 'cache_key', 'cache_group', 'array', '', false, false, $valid );
				},
			),
			'callback rejection' => array(
				static function ( &$valid ): void {
					wp_cache_set( 'cache_key', array( 1, 2, 3 ), 'cache_group' );
					wp_cache_get_valid( 'cache_key', 'cache_group', 'array', '__return_false', false, false, $valid );
				},
			),
		);
	}

	/**
	 * Test that a cache miss returns false.
	 *
	 * @ticket 66005
	 */
	public function test_cache_miss_returns_false(): void {
		$this->assertFalse( wp_cache_get_valid( 'missing_key', 'cache_group', 'array' ) );
	}

	/**
	 * Test that contents of an unexpected type return false.
	 *
	 * @dataProvider data_type_mismatch_returns_false
	 *
	 * @param mixed           $value The value to store in the cache.
	 * @param string|string[] $type  The expected type(s) to pass to wp_cache_get_valid().
	 *
	 * @ticket 66005
	 */
	public function test_type_mismatch_returns_false( $value, $type ): void {
		wp_cache_set( 'cache_key', $value, 'cache_group' );

		$this->assertFalse( wp_cache_get_valid( 'cache_key', 'cache_group', $type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Data provider.
	 */
	public function data_type_mismatch_returns_false(): array {
		return array(
			'string, array expected'        => array( 'a string', 'array' ),
			'array, object expected'        => array( array( 1, 2, 3 ), 'object' ),
			'numeric string, int expected'  => array( '123', 'int' ),
			'float, int expected'           => array( 1.5, 'int' ),
			'non-numeric string'            => array( 'abc', 'numeric' ),
			'null, array expected'          => array( null, 'array' ),
			'true, array expected'          => array( true, 'array' ),
			'string, none in list expected' => array( 'a string', array( 'object', 'numeric' ) ),
		);
	}

	/**
	 * Test that the contents are returned when the callback returns true.
	 *
	 * @ticket 66005
	 */
	public function test_callback_returning_true_returns_contents(): void {
		$value = array( 'id' => 5 );

		wp_cache_set( 'cache_key', $value, 'cache_group' );

		$result = wp_cache_get_valid(
			'cache_key',
			'cache_group',
			'array',
			static function ( array $contents ): bool {
				return isset( $contents['id'] ) && is_int( $contents['id'] );
			}
		);

		$this->assertSame( $value, $result );
	}

	/**
	 * Test that false is returned when the callback returns false.
	 *
	 * @ticket 66005
	 */
	public function test_callback_returning_false_returns_false(): void {
		wp_cache_set( 'cache_key', array( 'id' => 'not an int' ), 'cache_group' );

		$result = wp_cache_get_valid(
			'cache_key',
			'cache_group',
			'array',
			static function ( array $contents ): bool {
				return isset( $contents['id'] ) && is_int( $contents['id'] );
			}
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test that the callback is not invoked when the type check fails.
	 *
	 * @ticket 66005
	 */
	public function test_callback_not_invoked_on_type_mismatch(): void {
		$invoked = false;

		wp_cache_set( 'cache_key', 'a string', 'cache_group' );

		$result = wp_cache_get_valid(
			'cache_key',
			'cache_group',
			'array',
			static function () use ( &$invoked ): bool {
				$invoked = true;
				return true;
			}
		);

		$this->assertFalse( $result );
		$this->assertFalse( $invoked );
	}

	/**
	 * Test that an unsupported type is incorrect usage and returns false.
	 *
	 * @dataProvider data_unsupported_type_is_doing_it_wrong
	 *
	 * @expectedIncorrectUsage wp_cache_get_valid
	 *
	 * @param string|array $type The unsupported type value.
	 *
	 * @ticket 66005
	 */
	public function test_unsupported_type_is_doing_it_wrong( $type ): void {
		wp_cache_set( 'cache_key', array( 1, 2, 3 ), 'cache_group' );

		$this->assertFalse( wp_cache_get_valid( 'cache_key', 'cache_group', $type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Data provider.
	 */
	public function data_unsupported_type_is_doing_it_wrong(): array {
		return array(
			'null'                     => array( 'null' ),
			'class name'               => array( 'WP_Post' ),
			'mixed case'               => array( 'Array' ),
			'empty type list'          => array( array() ),
			'unsupported type in list' => array( array( 'array', 'null' ) ),
			'non-string type in list'  => array( array( 'array', 123 ) ),
		);
	}

	/**
	 * Test that a non-callable callback is incorrect usage and returns false.
	 *
	 * @expectedIncorrectUsage wp_cache_get_valid
	 *
	 * @ticket 66005
	 */
	public function test_non_callable_callback_is_doing_it_wrong(): void {
		wp_cache_set( 'cache_key', array( 1, 2, 3 ), 'cache_group' );

		$this->assertFalse( wp_cache_get_valid( 'cache_key', 'cache_group', 'array', 'not_a_real_function_name' ) );
	}

	/**
	 * Test that a callback returning a non-boolean is incorrect usage and returns false.
	 *
	 * @dataProvider data_non_boolean_callback_return_is_doing_it_wrong
	 *
	 * @expectedIncorrectUsage wp_cache_get_valid
	 *
	 * @param mixed $callback_return The non-boolean value for the callback to return.
	 *
	 * @ticket 66005
	 */
	public function test_non_boolean_callback_return_is_doing_it_wrong( $callback_return ): void {
		wp_cache_set( 'cache_key', array( 1, 2, 3 ), 'cache_group' );

		$result = wp_cache_get_valid(
			'cache_key',
			'cache_group',
			'array',
			static function () use ( $callback_return ) {
				return $callback_return;
			}
		);

		$this->assertFalse( $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Data provider.
	 */
	public function data_non_boolean_callback_return_is_doing_it_wrong(): array {
		return array(
			'truthy int'     => array( 1 ),
			'the value back' => array( array( 1, 2, 3 ) ),
		);
	}

	/**
	 * Test that the failure action fires on a type mismatch.
	 *
	 * @ticket 66005
	 */
	public function test_failed_action_fires_on_type_mismatch(): void {
		$captured = array();

		add_action(
			'wp_cache_get_valid_failed',
			static function ( $key, $group, $value, $reason ) use ( &$captured ): void {
				$captured = array( $key, $group, $value, $reason );
			},
			10,
			4
		);

		wp_cache_set( 'cache_key', 'a string', 'cache_group' );
		wp_cache_get_valid( 'cache_key', 'cache_group', 'array' );

		$this->assertSame( array( 'cache_key', 'cache_group', 'a string', 'type' ), $captured );
	}

	/**
	 * Test that the failure action fires when the callback rejects the contents.
	 *
	 * @ticket 66005
	 */
	public function test_failed_action_fires_on_callback_rejection(): void {
		$captured = array();

		add_action(
			'wp_cache_get_valid_failed',
			static function ( $key, $group, $value, $reason ) use ( &$captured ): void {
				$captured = array( $key, $group, $value, $reason );
			},
			10,
			4
		);

		wp_cache_set( 'cache_key', array( 1, 2, 3 ), 'cache_group' );
		wp_cache_get_valid( 'cache_key', 'cache_group', 'array', '__return_false' );

		$this->assertSame( array( 'cache_key', 'cache_group', array( 1, 2, 3 ), 'callback' ), $captured );
	}

	/**
	 * Test that the failure action does not fire on a plain cache miss.
	 *
	 * @ticket 66005
	 */
	public function test_failed_action_does_not_fire_on_cache_miss(): void {
		$fired = false;

		add_action(
			'wp_cache_get_valid_failed',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		wp_cache_get_valid( 'missing_key', 'cache_group', 'array' );

		$this->assertFalse( $fired );
	}

	/**
	 * Test that invalid contents are left in the cache by default.
	 *
	 * @ticket 66005
	 */
	public function test_invalid_contents_are_not_deleted_by_default(): void {
		wp_cache_set( 'cache_key', 'a string', 'cache_group' );

		$this->assertFalse( wp_cache_get_valid( 'cache_key', 'cache_group', 'array' ) );

		$found = false;
		$this->assertSame( 'a string', wp_cache_get( 'cache_key', 'cache_group', false, $found ) );
		$this->assertTrue( $found );
	}

	/**
	 * Test that invalid contents are deleted when requested.
	 *
	 * @dataProvider data_invalid_contents_are_deleted_when_requested
	 *
	 * @param mixed           $value    The invalid value to store in the cache.
	 * @param callable|string $callback The validation callback to pass, if any.
	 *
	 * @ticket 66005
	 */
	public function test_invalid_contents_are_deleted_when_requested( $value, $callback ): void {
		wp_cache_set( 'cache_key', $value, 'cache_group' );

		$this->assertFalse( wp_cache_get_valid( 'cache_key', 'cache_group', 'array', $callback, false, true ) );

		$found = false;
		wp_cache_get( 'cache_key', 'cache_group', false, $found );
		$this->assertFalse( $found );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Data provider.
	 */
	public function data_invalid_contents_are_deleted_when_requested(): array {
		return array(
			'type mismatch'      => array( 'a string', '' ),
			'callback rejection' => array( array( 1, 2, 3 ), '__return_false' ),
		);
	}

	/**
	 * Test that valid contents are not deleted when deletion is requested.
	 *
	 * @ticket 66005
	 */
	public function test_valid_contents_are_not_deleted(): void {
		$value = array( 1, 2, 3 );

		wp_cache_set( 'cache_key', $value, 'cache_group' );

		$this->assertSame( $value, wp_cache_get_valid( 'cache_key', 'cache_group', 'array', '', false, true ) );
		$this->assertSame( $value, wp_cache_get( 'cache_key', 'cache_group' ) );
	}

	/**
	 * Test that contents are not deleted when the function was called incorrectly.
	 *
	 * Deletion only applies to contents that were found and failed validation,
	 * never to cases where validation itself could not run.
	 *
	 * @dataProvider data_contents_are_not_deleted_on_incorrect_usage
	 *
	 * @expectedIncorrectUsage wp_cache_get_valid
	 *
	 * @param string|array    $type     The type value to pass.
	 * @param callable|string $callback The callback to pass.
	 *
	 * @ticket 66005
	 */
	public function test_contents_are_not_deleted_on_incorrect_usage( $type, $callback ): void {
		$value = array( 1, 2, 3 );

		wp_cache_set( 'cache_key', $value, 'cache_group' );

		$this->assertFalse( wp_cache_get_valid( 'cache_key', 'cache_group', $type, $callback, false, true ) );
		$this->assertSame( $value, wp_cache_get( 'cache_key', 'cache_group' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Data provider.
	 */
	public function data_contents_are_not_deleted_on_incorrect_usage(): array {
		return array(
			'unsupported type'     => array( 'not_a_type', '' ),
			'non-callable'         => array( 'array', 'not_a_real_function_name' ),
			'non-boolean callback' => array( 'array', '__return_null' ),
		);
	}

	/**
	 * Test the sentinel-union pattern: an object or a numeric not-found marker.
	 *
	 * Mirrors the `sites` cache in WP_Site::get_instance(), where a cached `-1`
	 * records a previous lookup that found nothing.
	 *
	 * @ticket 66005
	 */
	public function test_type_list_supports_sentinel_union(): void {
		wp_cache_set( 'cache_key', -1, 'cache_group' );

		$this->assertSame( -1, wp_cache_get_valid( 'cache_key', 'cache_group', array( 'object', 'numeric' ) ) );

		$site      = new stdClass();
		$site->foo = 'bar';
		wp_cache_set( 'cache_key', $site, 'cache_group' );

		$result = wp_cache_get_valid( 'cache_key', 'cache_group', array( 'object', 'numeric' ) );
		$this->assertInstanceOf( stdClass::class, $result );

		wp_cache_set( 'cache_key', 'poisoned', 'cache_group' );

		$this->assertFalse( wp_cache_get_valid( 'cache_key', 'cache_group', array( 'object', 'numeric' ) ) );
	}
}
