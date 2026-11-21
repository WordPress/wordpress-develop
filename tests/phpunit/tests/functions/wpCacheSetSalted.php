<?php

/**
 * @group functions
 *
 * @covers ::wp_cache_set_salted
 */
class Tests_Functions_wpCacheSetSalted extends WP_UnitTestCase {

	/**
	 * Test that wp_cache_set_salted sets the data correctly.
	 *
	 * @ticket 59592
	 */
	public function test_wp_cache_set_salted() {
		$cache_key    = 'cache_key';
		$cache_group  = 'query_data';
		$last_changed = wp_cache_get_last_changed( 'query_data' );
		$data         = array(
			'key1' => 'value1',
			'key2' => 'value2',
		);

		wp_cache_set_salted( $cache_key, $data, $cache_group, $last_changed );

		$cached_data = wp_cache_get( $cache_key, 'query_data' );

		$this->assertSame( $data, $cached_data['data'], 'The data key should contain the cached data.' );
		$this->assertSame( $last_changed, $cached_data['salt'], 'The last changed key should contain the last change time stamp' );
	}

	/**
	 * Test that wp_cache_set_salted sets the data with a salt.
	 *
	 * @ticket 59592
	 */
	public function test_wp_cache_set_salted_array_salt() {
		$cache_key    = 'cache_key';
		$cache_group  = 'query_data';
		$last_changed = array(
			wp_cache_get_last_changed( 'query_data_1' ),
			wp_cache_get_last_changed( 'query_data_2' ),
		);
		$data         = array(
			'key1' => 'value1',
			'key2' => 'value2',
		);

		wp_cache_set_salted( $cache_key, $data, $cache_group, $last_changed );

		$cached_data = wp_cache_get( $cache_key, 'query_data' );

		$last_changed_string = implode( ':', $last_changed );
		$this->assertSame( $data, $cached_data['data'], 'The data key should contain the cached data.' );
		$this->assertSame( $last_changed_string, $cached_data['salt'], 'The last changed key should contain the last change time stamp' );
	}
}
