<?php
/**
 * Test cases for wp_cache_flush_group().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.1.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_flush_group
 */
class Tests_wp_cache_flush_group extends WP_UnitTestCase {

	/**
	 * Tests flush group behavior.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_flush_group
	 *
	 * @param array  $group_to_flush_items Items belonging to the target group.
	 * @param string $group_to_flush       The group to flush.
	 * @param array  $other_group_items    Items belonging to a different group.
	 * @param string $other_group          The other group that should not be affected.
	 */
	public function test_wp_cache_flush_group( array $group_to_flush_items, string $group_to_flush, array $other_group_items, string $other_group ): void {
		foreach ( $group_to_flush_items as $key => $value ) {
			wp_cache_set( $key, $value, $group_to_flush );
			$this->assertSame( $value, wp_cache_get( $key, $group_to_flush ) );
		}

		foreach ( $other_group_items as $key => $value ) {
			wp_cache_set( $key, $value, $other_group );
			$this->assertSame( $value, wp_cache_get( $key, $other_group ) );
		}

		if ( ! wp_cache_supports( 'flush_group' ) ) {
			$this->setExpectedIncorrectUsage( 'wp_cache_flush_group' );
			$result = wp_cache_flush_group( $group_to_flush );
			$this->assertFalse( $result );
		} else {
			$result = wp_cache_flush_group( $group_to_flush );
			$this->assertTrue( $result );

			foreach ( $group_to_flush_items as $key => $value ) {
				$this->assertFalse( wp_cache_get( $key, $group_to_flush ) );
			}

			foreach ( $other_group_items as $key => $value ) {
				$this->assertSame( $value, wp_cache_get( $key, $other_group ) );
			}
		}
	}

	/**
	 * Data provider for test_wp_cache_flush_group.
	 *
	 * @return array<string, array{
	 *     group_to_flush_items: array<string, mixed>,
	 *     group_to_flush: string,
	 *     other_group_items: array<string, mixed>,
	 *     other_group: string,
	 * }>
	 */
	public function data_wp_cache_flush_group(): array {
		return array(
			'flushing group with multiple items' => array(
				'group_to_flush_items' => array(
					'key1' => 'value1',
					'key2' => 'value2',
				),
				'group_to_flush'       => 'group_a',
				'other_group_items'    => array(
					'key3' => 'value3',
				),
				'other_group'          => 'group_b',
			),
		);
	}
}
