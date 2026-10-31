<?php
/**
 * Test cases for wp_cache_set_multiple_salted().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.9.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_set_multiple_salted
 */
class Tests_wp_cache_set_multiple_salted extends WP_UnitTestCase {

	/**
	 * Tests setting multiple salted data items in cache.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_set_multiple_salted
	 *
	 * @param array           $data          Associative array of keys and data to cache.
	 * @param string          $group         Cache group.
	 * @param string|string[] $salt          Salt string or array.
	 * @param string          $expected_salt Expected salt string in cached items.
	 * @param array           $expected      Expected return array.
	 */
	public function test_wp_cache_set_multiple_salted( array $data, string $group, $salt, string $expected_salt, array $expected ): void {
		$result = wp_cache_set_multiple_salted( $data, $group, $salt );

		$this->assertSame( $expected, $result );

		foreach ( $data as $key => $value ) {
			$cached = wp_cache_get( $key, $group );
			$this->assertIsArray( $cached );
			$this->assertSame( $value, $cached['data'] );
			$this->assertSame( $expected_salt, $cached['salt'] );
		}
	}

	/**
	 * Data provider for test_wp_cache_set_multiple_salted.
	 *
	 * @return array<string, array{
	 *     data: array<string, mixed>,
	 *     group: string,
	 *     salt: string|string[],
	 *     expected_salt: string,
	 *     expected: array<string, bool>,
	 * }>
	 */
	public function data_wp_cache_set_multiple_salted(): array {
		return array(
			'multiple items with string salt' => array(
				'data'          => array(
					'key1' => 'val1',
					'key2' => 'val2',
				),
				'group'         => 'salted_group',
				'salt'          => 'salt_123',
				'expected_salt' => 'salt_123',
				'expected'      => array(
					'key1' => true,
					'key2' => true,
				),
			),
			'multiple items with array salt'  => array(
				'data'          => array(
					'key3' => 'val3',
				),
				'group'         => 'salted_group',
				'salt'          => array( 'p1', 'p2' ),
				'expected_salt' => 'p1:p2',
				'expected'      => array(
					'key3' => true,
				),
			),
			'empty data array'                => array(
				'data'          => array(),
				'group'         => 'salted_group',
				'salt'          => 'salt_123',
				'expected_salt' => 'salt_123',
				'expected'      => array(),
			),
		);
	}
}
