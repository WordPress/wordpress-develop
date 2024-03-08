<?php
/**
 * Tests for the wp_post_preview_js function.
 *
 * @group functions.php
 *
 * @covers ::wp_post_preview_js
 */#
class Tests_functions_wpPostPreviewJs extends WP_UnitTestCase {

	/**
	 * Should return empty if not in prviewing of a post
	 *
	 * @ticket 59775
	 */
	public function test_wp_post_preview_js() {
		$this->assertEmpty( get_echo( 'wp_post_preview_js' ) );
	}

	/**
	 * @ticket 59775
	 */
	public function test_wp_post_preview_js_working() {
		global $post, $wp_query;
		$post                 = self::factory()->post->create_and_get();
		$wp_query->is_preview = true;

		$output = get_echo( 'wp_post_preview_js' );
		$this->assertStringContainsString( 'wp-preview-' . $post->ID, $output );
		$this->assertStringStartsWith( '<script', $output );
		$this->assertStringContainsString( '</script>', $output );
	}

	/**
	 * Check that both priveiw and post needed to return script block
	 *
	 * @ticket 59775
	 */
	public function test_wp_post_preview_js_not_preview() {
		global $post;
		$post = self::factory()->post->create_and_get();

		$this->assertEmpty( get_echo( 'wp_post_preview_js' ) );
	}
}
