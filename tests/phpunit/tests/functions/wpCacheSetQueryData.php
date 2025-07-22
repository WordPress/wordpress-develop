<?php

/**
 * @group functions
 *
 * @covers ::wp_cache_set_salted
 */
class Tests_Functions_wpCacheSetQueryData extends WP_UnitTestCase {

	/**
	 * Test that wp_cache_set_query_data sets the data correctly.
	 *
	 * @ticket 59592
	 */
	public function test_wp_cache_set_query_data_sets_data() {
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
		$this->assertSame( $last_changed, $cached_data['last_changed'], 'The last changed key should contain the last change time stamp' );
	}
}
