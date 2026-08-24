<?php
/**
 * Test cases for wp_cache_flush_runtime().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.0.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_flush_runtime
 */
class Tests_wp_cache_flush_runtime extends WP_UnitTestCase {

	/**
	 * Tests flush runtime behavior.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_flush_runtime
	 *
	 * @param array  $items Cache items to preload before flushing.
	 * @param string $group Cache group.
	 */
	public function test_wp_cache_flush_runtime( array $items, string $group ): void {
		foreach ( $items as $key => $value ) {
			wp_cache_set( $key, $value, $group );
			$this->assertSame( $value, wp_cache_get( $key, $group ) );
		}

		if ( ! wp_cache_supports( 'flush_runtime' ) ) {
			$this->setExpectedIncorrectUsage( 'wp_cache_flush_runtime' );
			$result = wp_cache_flush_runtime();
			$this->assertFalse( $result );
		} else {
			$result = wp_cache_flush_runtime();
			$this->assertTrue( $result );

			foreach ( $items as $key => $value ) {
				$this->assertFalse( wp_cache_get( $key, $group ) );
			}
		}
	}

	/**
	 * Data provider for test_wp_cache_flush_runtime.
	 *
	 * @return array<string, array{
	 *     items: array<string, mixed>,
	 *     group: string,
	 * }>
	 */
	public function data_wp_cache_flush_runtime(): array {
		return array(
			'flushing single item'    => array(
				'items' => array(
					'key1' => 'value1',
				),
				'group' => 'test_group',
			),
			'flushing multiple items' => array(
				'items' => array(
					'key1' => 'value1',
					'key2' => 'value2',
				),
				'group' => 'test_group',
			),
		);
	}
}
