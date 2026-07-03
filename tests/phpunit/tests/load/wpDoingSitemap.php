<?php

/**
 * Tests for wp_doing_sitemap().
 *
 * @group load
 * @group sitemaps
 *
 * @covers ::wp_doing_sitemap
 */
class Tests_Load_WpDoingSitemap extends WP_UnitTestCase {

	/**
	 * The function should return false on a regular request.
	 *
	 * @ticket 56954
	 */
	public function test_should_return_false_by_default(): void {
		$this->assertFalse( wp_doing_sitemap() );
	}

	/**
	 * The DOING_SITEMAP constant should make the function return true.
	 *
	 * Runs in a separate process because the constant cannot be undefined
	 * once it has been set.
	 *
	 * @ticket 56954
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_constant_defined_returns_true(): void {
		$this->assertFalse( wp_doing_sitemap(), 'wp_doing_sitemap() should be false before the constant is defined.' );

		define( 'DOING_SITEMAP', true );

		$this->assertTrue( wp_doing_sitemap(), 'wp_doing_sitemap() should be true once DOING_SITEMAP is defined.' );
	}
}
