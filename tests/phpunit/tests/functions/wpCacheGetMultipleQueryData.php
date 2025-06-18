<?php

/**
 * Tests for the behavior of `wp_cache_get_multiple_query_data()`
 *
 * @group functions
 * @group cache
 *
 * @covers ::wp_cache_get_multiple_query_data
 */
class Tests_Functions_wpCacheGetMultipleQueryData extends WP_UnitTestCase {

	/**
	 * Test that wp_cache_get_multiple_query_data returns the cached data.
	 *
	 * @ticket 59592
	 */
	public function test_wp_cache_get_multiple_query_data_return_data() {
		$last_changed = wp_cache_get_last_changed( 'query_data' );
		$cache_value  = array(
			'last_changed' => $last_changed,
			'data'         => array(
				'key1' => 'value1',
				'key2' => 'value2',
			),
		);
		wp_cache_set( 'cache_key', $cache_value, 'query_data' );

		$result = wp_cache_get_multiple_query_data( array( 'cache_key' ), 'query_data', $last_changed );

		$this->assertSameSets( $cache_value['data'], $result['cache_key'] );
	}

	/**
	 * Test that wp_cache_get_multiple_query_data returns an empty array when no data is cached.
	 *
	 * @ticket 59592
	 */
	public function test_wp_cache_get_multiple_query_data_return_false() {
		wp_cache_set( 'cache_key', false, 'query_data' );
		wp_cache_set( 'another_key', null, 'query_data' );

		$last_changed = wp_cache_get_last_changed( 'query_data' );

		$result = wp_cache_get_multiple_query_data( array( 'cache_key', 'another_key' ), 'query_data', $last_changed );

		$this->assertSameSets(
			array(
				'cache_key'   => false,
				'another_key' => false,
			),
			$result
		);
	}

	/**
	 * Test that wp_cache_get_multiple_query_data returns the cached data for multiple keys.
	 *
	 * @ticket 59592
	 */
	public function test_wp_cache_get_multiple_query_data_with_some_false() {
		$last_changed = wp_cache_get_last_changed( 'query_data' );
		wp_cache_set(
			'cache_key',
			array(
				'last_changed' => $last_changed,
				'data'         => array( 123 ),
			),
			'query_data'
		);
		wp_cache_set(
			'another_key',
			array(
				'last_changed' => '123',
				'data'         => array(),
			),
			'query_data'
		);

		$last_changed = wp_cache_get_last_changed( 'query_data' );

		$result = wp_cache_get_multiple_query_data( array( 'cache_key', 'another_key' ), 'query_data', $last_changed );

		$this->assertSameSets(
			array(
				'cache_key'   => array( 123 ),
				'another_key' => false,
			),
			$result
		);
	}
}
