<?php

/**
 * Tests for the behavior of `wp_cache_get_multiple_query_data()`
 *
 * @group functions
 * @group cache
 *
 * @covers ::wp_cache_set_salted
 */
class Tests_Functions_wpCacheSetMultipleQueryData extends WP_UnitTestCase {
	/**
	 * Test that wp_cache_set_multiple_query_data sets multiple query data correctly.
	 *
	 * @ticket 59592
	 */
	public function test_wp_cache_set_multiple_query_data() {
		$cache_group  = 'query_data';
		$last_changed = wp_cache_get_last_changed( 'query_data' );
		$data         = array(
			'key1' => 'value1',
			'key2' => 'value2',
		);

		wp_cache_set_multiple_salted( $data, $cache_group, $last_changed );
		$cache_values          = wp_cache_get_multiple( array( 'key1', 'key2' ), $cache_group );
		$expected_cache_values = array(
			'key1' => array(

				'data' => 'value1',
				'salt' => $last_changed,
			),
			'key2' => array(

				'data' => 'value2',
				'salt' => $last_changed,
			),
		);
		$this->assertSameSets( $expected_cache_values, $cache_values );
	}
}
