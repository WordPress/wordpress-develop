<?php
/**
 * Test cases for wp_cache_set_multiple().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.0.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_set_multiple
 */
class Tests_wp_cache_set_multiple extends WP_UnitTestCase {

	/**
	 * Tests setting multiple values in the cache.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_set_multiple
	 *
	 * @param array  $initial_cache Array of existing cache items to preload.
	 * @param array  $data          Data to set.
	 * @param string $group         Cache group.
	 * @param array  $expected      Expected return array.
	 */
	public function test_wp_cache_set_multiple( array $initial_cache, array $data, string $group, array $expected ): void {
		foreach ( $initial_cache as $key => $value ) {
			wp_cache_set( $key, $value, $group );
		}

		$result = wp_cache_set_multiple( $data, $group );

		$this->assertSame( $expected, $result );

		foreach ( $data as $key => $value ) {
			$this->assertSame( $value, wp_cache_get( $key, $group ) );
		}
	}

	/**
	 * Data provider for test_wp_cache_set_multiple.
	 *
	 * @return array<string, array{
	 *     initial_cache: array<string, mixed>,
	 *     data: array<string, mixed>,
	 *     group: string,
	 *     expected: array<string, bool>,
	 * }>
	 */
	public function data_wp_cache_set_multiple(): array {
		return array(
			'setting multiple items in empty cache' => array(
				'initial_cache' => array(),
				'data'          => array(
					'key1' => 'val1',
					'key2' => 'val2',
					'key3' => 'val3',
				),
				'group'         => 'test_group',
				'expected'      => array(
					'key1' => true,
					'key2' => true,
					'key3' => true,
				),
			),
			'overwriting existing keys'             => array(
				'initial_cache' => array(
					'key1' => 'original_val',
				),
				'data'          => array(
					'key1' => 'updated_val',
					'key2' => 'val2',
				),
				'group'         => 'test_group',
				'expected'      => array(
					'key1' => true,
					'key2' => true,
				),
			),
			'empty data array'                      => array(
				'initial_cache' => array(),
				'data'          => array(),
				'group'         => 'test_group',
				'expected'      => array(),
			),
		);
	}
}
