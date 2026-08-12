<?php
/**
 * Test wp_load_alloptions().
 *
 * @group option
 */
class Tests_Option_wpLoadAlloptions extends WP_UnitTestCase {
	protected $alloptions = null;

	public function tear_down() {
		$this->alloptions = null;
		parent::tear_down();
	}

	/**
	 * @covers ::wp_cache_get
	 */
	public function test_if_alloptions_is_cached() {
		$this->assertNotEmpty( wp_cache_get( 'alloptions', 'options' ) );
	}

	/**
	 * @ticket 42441
	 *
	 * @covers ::wp_load_alloptions
	 */
	public function test_default_and_yes() {
		add_option( 'foo', 'bar' );
		add_option( 'bar', 'foo', '', true );
		$alloptions = wp_load_alloptions();
		$this->assertArrayHasKey( 'foo', $alloptions );
		$this->assertArrayHasKey( 'bar', $alloptions );
	}

	/**
	 * @ticket 42441
	 *
	 * @covers ::wp_load_alloptions
	 */
	public function test_default_and_no() {
		add_option( 'foo', 'bar' );
		add_option( 'bar', 'foo', '', false );
		$alloptions = wp_load_alloptions();
		$this->assertArrayHasKey( 'foo', $alloptions );
		$this->assertArrayNotHasKey( 'bar', $alloptions );
	}

	/**
	 * @depends test_if_alloptions_is_cached
	 *
	 * @covers ::wp_cache_delete
	 */
	public function test_if_cached_alloptions_is_deleted() {
		$this->assertTrue( wp_cache_delete( 'alloptions', 'options' ) );
	}

	/**
	 * @depends test_if_alloptions_is_cached
	 *
	 * @covers ::wp_load_alloptions
	 */
	public function test_if_alloptions_are_retrieved_from_cache() {
		$before = get_num_queries();
		wp_load_alloptions();
		$after = get_num_queries();

		// Database has not been hit.
		$this->assertSame( $before, $after );
	}

	/**
	 * @depends test_if_cached_alloptions_is_deleted
	 *
	 * @covers ::wp_load_alloptions
	 */
	public function test_if_alloptions_are_retrieved_from_database() {
		// Delete the existing cache first.
		wp_cache_delete( 'alloptions', 'options' );

		$before = get_num_queries();
		wp_load_alloptions();
		$after = get_num_queries();

		// Database has been hit.
		$this->assertSame( $before + 1, $after );
	}

	/**
	 * @depends test_if_cached_alloptions_is_deleted
	 *
	 * @covers ::wp_load_alloptions
	 */
	public function test_filter_pre_cache_alloptions_is_called() {
		$temp = wp_installing();

		/**
		 * Set wp_installing() to false.
		 *
		 * If wp_installing is false and the cache is empty, the filter is called regardless if it's multisite or not.
		 */
		wp_installing( false );

		// Delete the existing cache first.
		wp_cache_delete( 'alloptions', 'options' );

		add_filter( 'pre_cache_alloptions', array( $this, 'return_pre_cache_filter' ) );
		$all_options = wp_load_alloptions();

		// Value could leak to other tests if not reset.
		wp_installing( $temp );

		// Filter was called.
		$this->assertSame( $this->alloptions, $all_options );
	}

	/**
	 * @depends test_if_alloptions_is_cached
	 *
	 * @covers ::wp_load_alloptions
	 */
	public function test_filter_pre_cache_alloptions_is_not_called() {
		$temp = wp_installing();

		/**
		 * Set wp_installing() to true.
		 *
		 * If wp_installing is true and it's multisite, the cache and filter are not used.
		 * If wp_installing is true and it's not multisite, the cache is used (if not empty), and the filter not.
		 */
		wp_installing( true );

		add_filter( 'pre_cache_alloptions', array( $this, 'return_pre_cache_filter' ) );
		wp_load_alloptions();

		// Value could leak to other tests if not reset.
		wp_installing( $temp );

		// Filter was not called.
		$this->assertNull( $this->alloptions );
	}

	public function return_pre_cache_filter( $alloptions ) {
		$this->alloptions = $alloptions;
		return $this->alloptions;
	}

	/**
	 * Tests that `$alloptions` can be filtered with a custom value, short circuiting `wp_load_alloptions()`.
	 *
	 * @ticket 56045
	 *
	 * @covers ::wp_load_alloptions
	 */
	public function test_filter_pre_wp_load_alloptions_filter_is_called() {
		$filter = new MockAction();

		add_filter( 'pre_wp_load_alloptions', array( &$filter, 'filter' ) );

		wp_load_alloptions();

		$this->assertSame(
			1,
			$filter->get_call_count(),
			'The filter was not called 1 time.'
		);

		$this->assertSame(
			array( 'pre_wp_load_alloptions' ),
			$filter->get_hook_names(),
			'The hook name was incorrect.'
		);
	}

	/**
	 * Tests that wp_load_alloptions handles invalid cache values correctly.
	 *
	 * @ticket 28664
	 *
	 * @covers ::wp_load_alloptions
	 */
	public function test_invalid_cache_value_handling() {

		add_option( 'test_option_1', 'value1', '', 'yes' );
		add_option( 'test_option_2', 'value2', '', 'yes' );

		wp_cache_set( 'alloptions', 0, 'options' );

		$alloptions = wp_load_alloptions();

		$this->assertIsArray( $alloptions, 'alloptions should be an array' );
		$this->assertArrayHasKey( 'test_option_1', $alloptions, 'option1 should be loaded' );
		$this->assertArrayHasKey( 'test_option_2', $alloptions, 'option2 should be loaded' );
		$this->assertEquals( 'value1', $alloptions['test_option_1'], 'option1 should have correct value' );
		$this->assertEquals( 'value2', $alloptions['test_option_2'], 'option2 should have correct value' );

		$cached = wp_cache_get( 'alloptions', 'options' );
		$this->assertIsArray( $cached, 'cache should be reset to array' );
		$this->assertEquals( $alloptions, $cached, 'cached value should match loaded options' );
	}

	/**
	 * Tests that wp_load_alloptions properly handles cache fallback behavior.
	 *
	 * @ticket 28664
	 *
	 * @covers ::wp_load_alloptions
	 */
	public function test_cache_fallback_behavior() {

		add_option( 'fallback_option_1', 'value1', '', 'yes' );
		wp_cache_delete( 'alloptions', 'options' );

		$first_load = wp_load_alloptions();
		$this->assertArrayHasKey( 'fallback_option_1', $first_load );

		wp_cache_set( 'alloptions', 'invalid', 'options' );

		$second_load = wp_load_alloptions();
		$this->assertArrayHasKey( 'fallback_option_1', $second_load );
		$this->assertEquals( $first_load, $second_load );

		$cached = wp_cache_get( 'alloptions', 'options' );
		$this->assertIsArray( $cached );
		$this->assertEquals( $second_load, $cached );
	}
}
