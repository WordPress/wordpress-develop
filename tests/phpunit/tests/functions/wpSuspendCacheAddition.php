<?php

/**
 * Tests for the wp_suspend_cache_addition() function.
 *
 * @group functions
 * @group cache
 *
 * @covers ::wp_suspend_cache_addition
 */
class Tests_Functions_wpSuspendCacheAddition extends WP_UnitTestCase {

	/**
	 * Original state of the cache addition suspension flag.
	 *
	 * @var bool
	 */
	protected $original_state;

	/**
	 * Sets up the test fixture.
	 */
	public function set_up() {
		parent::set_up();
		$this->original_state = wp_suspend_cache_addition();
	}

	/**
	 * Restores the original cache additions suspension flag after each test.
	 */
	public function tear_down() {
		wp_suspend_cache_addition( $this->original_state );
		parent::tear_down();
	}

	/**
	 * Tests setting and getting the cache addition suspension state.
	 *
	 * @ticket 57263
	 * @ticket 60017
	 */
	public function test_should_set_and_get_state() {
		// Set to true.
		$this->assertTrue( wp_suspend_cache_addition( true ), 'wp_suspend_cache_addition( true ) should return true.' );
		$this->assertTrue( wp_suspend_cache_addition(), 'wp_suspend_cache_addition() getter should return true after setting to true.' );

		// Set to false.
		$this->assertFalse( wp_suspend_cache_addition( false ), 'wp_suspend_cache_addition( false ) should return false.' );
		$this->assertFalse( wp_suspend_cache_addition(), 'wp_suspend_cache_addition() getter should return false after setting to false.' );
	}

	/**
	 * Tests that passing non-boolean values does not change the cache addition suspension state.
	 *
	 * @ticket 57263
	 * @ticket 60017
	 *
	 * @dataProvider data_non_boolean_values
	 *
	 * @param mixed $non_bool_value Non-boolean value to pass.
	 */
	public function test_should_not_change_state_with_non_boolean_values( $non_bool_value ) {
		// When currently false, passing a non-boolean should not change it.
		wp_suspend_cache_addition( false );
		$this->assertFalse( wp_suspend_cache_addition( $non_bool_value ), 'Passing non-boolean should not change state from false.' );
		$this->assertFalse( wp_suspend_cache_addition(), 'Getter should remain false.' );

		// When currently true, passing a non-boolean should not change it.
		wp_suspend_cache_addition( true );
		$this->assertTrue( wp_suspend_cache_addition( $non_bool_value ), 'Passing non-boolean should not change state from true.' );
		$this->assertTrue( wp_suspend_cache_addition(), 'Getter should remain true.' );
	}

	/**
	 * Data provider of non-boolean values.
	 *
	 * @return array[]
	 */
	public static function data_non_boolean_values() {
		return array(
			'string "true"'  => array( 'true' ),
			'string "false"' => array( 'false' ),
			'string ""'      => array( '' ),
			'integer 1'      => array( 1 ),
			'integer 0'      => array( 0 ),
			'array'          => array( array( true ) ),
			'null'           => array( null ),
		);
	}
}
