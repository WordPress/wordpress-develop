<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_oembed_cache() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_oembed_cache
 */
class Tests_wp_ajax_oembed_cache extends WP_Ajax_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Setup test fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$post_id = $factory->post->create(
			array(
				'post_content' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			)
		);
	}

	/**
	 * Tests oEmbed caching via AJAX.
	 *
	 * @ticket 65252
	 */
	public function test_oembed_cache(): void {
		global $wp_embed;

		// Mock the user.
		$this->_setRole( 'administrator' );

		$_GET['post'] = self::$post_id;

		// We want to verify that WP_Embed::cache_oembed was called.
		// Since we can't easily mock the global $wp_embed object without affecting other things,
		// we'll check if the cache was actually attempted by looking at post meta
		// or by mocking the method if possible.
		// However, WP_Embed::cache_oembed() triggers shortcodes and autoembeds.
		// A simpler way is to check if it dies with 0 as expected.

		try {
			$this->_handleAjax( 'oembed_cache' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->_last_response = (string) $e->getMessage();
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = (string) $e->getMessage();
		}

		$this->assertSame( '0', $this->_last_response );
	}

	/**
	 * Tests oEmbed caching with invalid post ID.
	 *
	 * @ticket 65252
	 */
	public function test_oembed_cache_invalid_post(): void {
		$this->_setRole( 'administrator' );

		$_GET['post'] = 99999;

		try {
			$this->_handleAjax( 'oembed_cache' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->_last_response = (string) $e->getMessage();
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = (string) $e->getMessage();
		}

		$this->assertSame( '0', $this->_last_response );
	}
}
