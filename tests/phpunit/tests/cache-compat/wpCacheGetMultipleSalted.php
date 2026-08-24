<?php
/**
 * Test cases for wp_cache_get_multiple_salted().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.9.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_get_multiple_salted
 */
class Tests_wp_cache_get_multiple_salted extends WP_UnitTestCase {

	/**
	 * Tests retrieving multiple salted items from cache.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_get_multiple_salted
	 *
	 * @param array           $cached_raw_values Associative array of raw cache items to set.
	 * @param array           $keys_to_get       Array of keys to retrieve.
	 * @param string          $group             Cache group.
	 * @param string|string[] $query_salt        Salt to query with.
	 * @param array           $expected          Expected associative array of return values.
	 */
	public function test_wp_cache_get_multiple_salted( array $cached_raw_values, array $keys_to_get, string $group, $query_salt, array $expected ): void {
		foreach ( $cached_raw_values as $key => $raw_value ) {
			wp_cache_set( $key, $raw_value, $group );
		}

		$result = wp_cache_get_multiple_salted( $keys_to_get, $group, $query_salt );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for test_wp_cache_get_multiple_salted.
	 *
	 * @return array<string, array{
	 *     cached_raw_values: array<string, mixed>,
	 *     keys_to_get: string[],
	 *     group: string,
	 *     query_salt: string|string[],
	 *     expected: array<string, mixed>,
	 * }>
	 */
	public function data_wp_cache_get_multiple_salted(): array {
		return array(
			'mixed matches and invalid items' => array(
				'cached_raw_values' => array(
					'valid_key'       => array(
						'data' => 'valid_data',
						'salt' => 'salt_123',
					),
					'mismatched_salt' => array(
						'data' => 'mismatched_data',
						'salt' => 'other_salt',
					),
					'not_array_cache' => 'string_value',
					'missing_salt'    => array( 'data' => 'data_only' ),
				),
				'keys_to_get'       => array( 'valid_key', 'mismatched_salt', 'not_array_cache', 'missing_salt', 'non_existent' ),
				'group'             => 'salted_group',
				'query_salt'        => 'salt_123',
				'expected'          => array(
					'valid_key'       => 'valid_data',
					'mismatched_salt' => false,
					'not_array_cache' => false,
					'missing_salt'    => false,
					'non_existent'    => false,
				),
			),
			'array salt match'                => array(
				'cached_raw_values' => array(
					'key1' => array(
						'data' => 'data1',
						'salt' => 'p1:p2',
					),
				),
				'keys_to_get'       => array( 'key1' ),
				'group'             => 'salted_group',
				'query_salt'        => array( 'p1', 'p2' ),
				'expected'          => array(
					'key1' => 'data1',
				),
			),
		);
	}
}
