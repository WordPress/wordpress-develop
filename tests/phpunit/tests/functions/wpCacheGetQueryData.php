<?php

/**
 * Tests for the behavior of `wp_cache_get_multiple_query_data()`
 *
 * @group functions
 * @group cache
 *
 * @covers ::wp_cache_get_query_data
 */
class Tests_Functions_wpCacheGetQueryData extends WP_UnitTestCase {

	/**
	 * Test that wp_cache_get_query_data returns the cached data.
	 *
	 * @ticket 59592
	 */
	public function test_wp_cache_get_query_data_return_data() {
		$last_changed = wp_cache_get_last_changed( 'query_data' );
		$cache_value  = array(
			'last_changed' => $last_changed,
			'data'         => array(
				'key1' => 'value1',
				'key2' => 'value2',
			),
		);
		wp_cache_set( 'cache_key', $cache_value, 'query_data' );

		$result = wp_cache_get_query_data( 'cache_key', 'query_data', $last_changed );

		$this->assertSameSets( $cache_value['data'], $result );
	}

	/**
	 * Test that wp_cache_get_query_data returns false when no data is cached.
	 *
	 * @dataProvider data_provider_for_wp_cache_get_query_data_return_false
	 *
	 * @ticket 59592
	 */
	public function test_wp_cache_get_query_data_return_false( $cache_value ) {
		wp_cache_set( 'cache_key', $cache_value, 'query_data' );
		$last_changed = wp_cache_get_last_changed( 'query_data' );
		$this->assertFalse( wp_cache_get_query_data( 'cache_key', 'query_data', $last_changed ) );
	}

	public function data_provider_for_wp_cache_get_query_data_return_false() {
		return array(
			array( false ),
			array( null ),
			array( '' ),
			array( 0 ),
			array( array() ),
			array( new StdClass() ),
			array( array( 'last_changed' => '123' ) ),
			array(
				array(
					'last_changed' => '123',
					'data'         => array(),
				),
			),
			array( array( 'data' => array() ) ),
		);
	}
}
