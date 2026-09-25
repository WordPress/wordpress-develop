<?php
/**
 * Test cases for wp_cache_get_salted().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.9.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_get_salted
 */
class Tests_wp_cache_get_salted extends WP_UnitTestCase {

	/**
	 * Tests retrieving salted data from the cache.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_get_salted
	 *
	 * @param mixed           $cached_raw_value Raw value stored in cache.
	 * @param string          $group            Cache group.
	 * @param string|string[] $query_salt       Salt passed to wp_cache_get_salted.
	 * @param mixed           $expected         Expected return value.
	 */
	public function test_wp_cache_get_salted( $cached_raw_value, string $group, $query_salt, $expected ): void {
		wp_cache_set( 'test_key', $cached_raw_value, $group );

		$result = wp_cache_get_salted( 'test_key', $group, $query_salt );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for test_wp_cache_get_salted.
	 *
	 * @return array<string, array{
	 *     cached_raw_value: mixed,
	 *     group: string,
	 *     query_salt: string|string[],
	 *     expected: mixed,
	 * }>
	 */
	public function data_wp_cache_get_salted(): array {
		return array(
			'valid matching string salt' => array(
				'cached_raw_value' => array(
					'data' => 'sample_data',
					'salt' => 'salt_123',
				),
				'group'            => 'salted_group',
				'query_salt'       => 'salt_123',
				'expected'         => 'sample_data',
			),
			'valid matching array salt'  => array(
				'cached_raw_value' => array(
					'data' => array( 'foo' => 'bar' ),
					'salt' => 'part1:part2',
				),
				'group'            => 'salted_group',
				'query_salt'       => array( 'part1', 'part2' ),
				'expected'         => array( 'foo' => 'bar' ),
			),
			'salt mismatch'              => array(
				'cached_raw_value' => array(
					'data' => 'sample_data',
					'salt' => 'salt_old',
				),
				'group'            => 'salted_group',
				'query_salt'       => 'salt_new',
				'expected'         => false,
			),
			'cached value not an array'  => array(
				'cached_raw_value' => 'not_an_array',
				'group'            => 'salted_group',
				'query_salt'       => 'salt_123',
				'expected'         => false,
			),
			'missing salt key'           => array(
				'cached_raw_value' => array( 'data' => 'sample_data' ),
				'group'            => 'salted_group',
				'query_salt'       => 'salt_123',
				'expected'         => false,
			),
			'missing data key'           => array(
				'cached_raw_value' => array( 'salt' => 'salt_123' ),
				'group'            => 'salted_group',
				'query_salt'       => 'salt_123',
				'expected'         => false,
			),
		);
	}
}
