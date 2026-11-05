<?php
/**
 * Test cases for wp_cache_supports().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.1.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_supports
 */
class Tests_wp_cache_supports extends WP_UnitTestCase {

	/**
	 * Tests wp_cache_supports with various features.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_supports
	 *
	 * @param string $feature Feature name to test.
	 */
	public function test_wp_cache_supports( string $feature ): void {
		$this->assertIsBool( wp_cache_supports( $feature ) );
	}

	/**
	 * Data provider for test_wp_cache_supports.
	 *
	 * @return array<string, array{
	 *     feature: string,
	 * }>
	 */
	public function data_wp_cache_supports(): array {
		return array(
			'add_multiple feature'    => array(
				'feature' => 'add_multiple',
			),
			'set_multiple feature'    => array(
				'feature' => 'set_multiple',
			),
			'get_multiple feature'    => array(
				'feature' => 'get_multiple',
			),
			'delete_multiple feature' => array(
				'feature' => 'delete_multiple',
			),
			'flush_runtime feature'   => array(
				'feature' => 'flush_runtime',
			),
			'flush_group feature'     => array(
				'feature' => 'flush_group',
			),
			'unknown feature'         => array(
				'feature' => 'unknown_feature_xyz',
			),
		);
	}
}
