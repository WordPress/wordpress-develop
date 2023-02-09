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
		global $wpdb;
		$before = $wpdb->num_queries;
		wp_load_alloptions();
		$after = $wpdb->num_queries;

		// Database has not been hit.
		$this->assertSame( $before, $after );
	}

	/**
	 * @depends test_if_cached_alloptions_is_deleted
	 *
	 * @covers ::wp_load_alloptions
	 */
	public function test_if_alloptions_are_retrieved_from_database() {
		global $wpdb;

		// Delete the existing cache first.
		wp_cache_delete( 'alloptions', 'options' );

		$before = $wpdb->num_queries;
		wp_load_alloptions();
		$after = $wpdb->num_queries;

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
}
