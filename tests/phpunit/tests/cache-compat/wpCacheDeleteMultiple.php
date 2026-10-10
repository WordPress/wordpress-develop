<?php
/**
 * Test cases for wp_cache_delete_multiple().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.0.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_delete_multiple
 */
class Tests_wp_cache_delete_multiple extends WP_UnitTestCase {

	/**
	 * Tests deleting multiple values from the cache.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_delete_multiple
	 *
	 * @param array  $initial_cache Array of existing cache items to preload.
	 * @param array  $keys          Keys to delete.
	 * @param string $group         Cache group.
	 * @param array  $expected      Expected return array.
	 */
	public function test_wp_cache_delete_multiple( array $initial_cache, array $keys, string $group, array $expected ): void {
		foreach ( $initial_cache as $key => $value ) {
			wp_cache_set( $key, $value, $group );
		}

		$result = wp_cache_delete_multiple( $keys, $group );

		$this->assertSame( $expected, $result );

		foreach ( $keys as $key ) {
			$this->assertFalse( wp_cache_get( $key, $group ) );
		}
	}

	/**
	 * Data provider for test_wp_cache_delete_multiple.
	 *
	 * @return array<string, array{
	 *     initial_cache: array<string, mixed>,
	 *     keys: string[],
	 *     group: string,
	 *     expected: array<string, bool>,
	 * }>
	 */
	public function data_wp_cache_delete_multiple(): array {
		return array(
			'deleting existing and missing keys' => array(
				'initial_cache' => array(
					'key1' => 'val1',
					'key2' => 'val2',
				),
				'keys'          => array( 'key1', 'key2', 'missing_key' ),
				'group'         => 'test_group',
				'expected'      => array(
					'key1'        => true,
					'key2'        => true,
					'missing_key' => false,
				),
			),
			'empty keys array'                   => array(
				'initial_cache' => array(
					'key1' => 'val1',
				),
				'keys'          => array(),
				'group'         => 'test_group',
				'expected'      => array(),
			),
		);
	}
}
