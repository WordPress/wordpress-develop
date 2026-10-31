<?php
/**
 * Test cases for wp_cache_switch_to_blog().
 *
 * @since 7.0.0
 *
 * @group cache
 * @group compat
 *
 * @covers ::wp_cache_switch_to_blog
 * @subpackage UnitTests
 * @package WordPress
 */
class Tests_wp_cache_switch_to_blog extends WP_UnitTestCase {

	/**
	 * Tests wp_cache_switch_to_blog with drop-in method or fallback.
	 *
	 * @ticket 65955
	 *
	 * @dataProvider data_wp_cache_switch_to_blog
	 *
	 * @param int $blog_id Blog ID to switch to.
	 */
	public function test_wp_cache_switch_to_blog( int $blog_id ): void {
		global $wp_object_cache;

		$original_cache = $wp_object_cache;

		$mock_cache = new class() {
			/**
			 * Switched blog ID tracker.
			 *
			 * @var int|null
			 */
			public $switched_blog_id = null;

			/**
			 * Mock switch_to_blog method.
			 *
			 * @param int $id Blog ID.
			 */
			public function switch_to_blog( int $id ): void {
				$this->switched_blog_id = $id;
			}
		};

		$wp_object_cache = $mock_cache;

		try {
			wp_cache_switch_to_blog( $blog_id );
			$this->assertSame( $blog_id, $mock_cache->switched_blog_id );
		} finally {
			$wp_object_cache = $original_cache;
		}
	}

	/**
	 * Data provider for test_wp_cache_switch_to_blog.
	 *
	 * @return array<string, array{
	 *     blog_id: int,
	 * }>
	 */
	public function data_wp_cache_switch_to_blog(): array {
		return array(
			'switch to site 2'  => array(
				'blog_id' => 2,
			),
			'switch to site 99' => array(
				'blog_id' => 99,
			),
		);
	}
}
