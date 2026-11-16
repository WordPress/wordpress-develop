<?php
/**
 * Test cases for wp_cache_set_salted().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.9.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_set_salted
 */
class Tests_wp_cache_set_salted extends WP_UnitTestCase {

	/**
	 * Tests setting salted data in cache.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_set_salted
	 *
	 * @param string          $key           Cache key.
	 * @param mixed           $data          Data to cache.
	 * @param string          $group         Cache group.
	 * @param string|string[] $salt          Salt (string or array).
	 * @param string          $expected_salt Expected salt stored in cache.
	 */
	public function test_wp_cache_set_salted( string $key, $data, string $group, $salt, string $expected_salt ): void {
		$result = wp_cache_set_salted( $key, $data, $group, $salt );

		$this->assertTrue( $result );

		$cached = wp_cache_get( $key, $group );

		$this->assertIsArray( $cached );
		$this->assertArrayHasKey( 'data', $cached );
		$this->assertArrayHasKey( 'salt', $cached );
		$this->assertSame( $data, $cached['data'] );
		$this->assertSame( $expected_salt, $cached['salt'] );
	}

	/**
	 * Data provider for test_wp_cache_set_salted.
	 *
	 * @return array<string, array{
	 *     key: string,
	 *     data: mixed,
	 *     group: string,
	 *     salt: string|string[],
	 *     expected_salt: string,
	 * }>
	 */
	public function data_wp_cache_set_salted(): array {
		return array(
			'string salt' => array(
				'key'           => 'key1',
				'data'          => array( 'foo' => 'bar' ),
				'group'         => 'salted_group',
				'salt'          => 'salt_123',
				'expected_salt' => 'salt_123',
			),
			'array salt'  => array(
				'key'           => 'key2',
				'data'          => 'scalar_data',
				'group'         => 'salted_group',
				'salt'          => array( 'part1', 'part2' ),
				'expected_salt' => 'part1:part2',
			),
		);
	}
}
