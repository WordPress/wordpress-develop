<?php
/**
 * Test cases for wp_cache_get_multiple().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.5.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_get_multiple
 */
class Tests_wp_cache_get_multiple extends WP_UnitTestCase {

	/**
	 * Tests retrieving multiple values from the cache.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_get_multiple
	 *
	 * @param array  $initial_cache Array of existing cache items to preload.
	 * @param string $initial_group Group where initial cache items are stored.
	 * @param array  $keys          Keys to retrieve.
	 * @param string $query_group   Cache group to query.
	 * @param array  $expected      Expected return array.
	 */
	public function test_wp_cache_get_multiple( array $initial_cache, string $initial_group, array $keys, string $query_group, array $expected ): void {
		foreach ( $initial_cache as $key => $value ) {
			wp_cache_set( $key, $value, $initial_group );
		}

		$result = wp_cache_get_multiple( $keys, $query_group );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for test_wp_cache_get_multiple.
	 *
	 * @return array<string, array{
	 *     initial_cache: array<string, mixed>,
	 *     initial_group: string,
	 *     keys: string[],
	 *     query_group: string,
	 *     expected: array<string, mixed>,
	 * }>
	 */
	public function data_wp_cache_get_multiple(): array {
		return array(
			'retrieving existing and missing keys' => array(
				'initial_cache' => array(
					'key1' => 'value1',
					'key2' => 'value2',
				),
				'initial_group' => 'test_group',
				'keys'          => array( 'key1', 'key2', 'missing_key' ),
				'query_group'   => 'test_group',
				'expected'      => array(
					'key1'        => 'value1',
					'key2'        => 'value2',
					'missing_key' => false,
				),
			),
			'empty keys array'                     => array(
				'initial_cache' => array(
					'key1' => 'value1',
				),
				'initial_group' => 'test_group',
				'keys'          => array(),
				'query_group'   => 'test_group',
				'expected'      => array(),
			),
			'cache group isolation'                => array(
				'initial_cache' => array(
					'key1' => 'group_a_value',
				),
				'initial_group' => 'group_a',
				'keys'          => array( 'key1' ),
				'query_group'   => 'group_b',
				'expected'      => array(
					'key1' => false,
				),
			),
		);
	}
}
