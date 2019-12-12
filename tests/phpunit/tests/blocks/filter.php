<?php
/**
 * Block filtering tests.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.3.1
 */

/**
 * Tests for block filtering functions
 *
 * @since 5.3.1
 *
 * @group blocks
 */
class WP_Test_Block_Filter extends WP_UnitTestCase {

	/**
	 * An allowable tag name for all users, regardless of capability.
	 *
	 * @var string
	 */
	protected static $allowed_tag = 'b';

	/**
	 * @dataProvider data_filter_block_content
	 */
	function test_filter_block_content( $content, $expected_filtered ) {
		// Always filter on direct call to `filter_block_content`, `wp_kses`.
		$this->assertEquals( $expected_filtered, filter_block_content( $content ) );
		$this->assertEquals( $expected_filtered, wp_kses( $content, array( self::$allowed_tag => array() ) ) );

		// With `unfiltered_html` (no KSES filtering), original content of saved post should remain intact.
		kses_remove_filters();
		$post = self::factory()->post->create_and_get( wp_slash( array( 'post_content' => $content ) ) );
		$this->assertEquals( $content, $post->post_content );

		// Without `unfiltered_html` (KSES filtering), expect saved post content to match expected filtered.
		kses_init_filters();
		$post = self::factory()->post->create_and_get( wp_slash( array( 'post_content' => $content ) ) );
		$this->assertEquals( $expected_filtered, $post->post_content );
	}

	function data_filter_block_content() {
		return array(
			// Non-block content.
			array(
				'<b>Original content</b>',
				'<b>Original content</b>',
			),

			// Block content with no block attributes.
			array(
				'<!-- wp:example /-->',
				'<!-- wp:example /-->',
			),

			// Block with attributes including filterable HTML.
			array(
				'<!-- wp:example {"key":"\\u003cmarquee\\u003e\\u003c/marquee\\u003e\\u003c' . self::$allowed_tag . '\\u003e\\u003c/' . self::$allowed_tag . '\\u003e"} /-->',
				'<!-- wp:example {"key":"\\u003c' . self::$allowed_tag . '\\u003e\\u003c\\/' . self::$allowed_tag . '\\u003e"} /-->',
			),

			// Inner blocks using attributes including filterable HTML.
			array(
				'<!-- wp:outer --><!-- wp:inner {"key":"\\u003cmarquee\\u003e\\u003c/marquee\\u003e\\u003c' . self::$allowed_tag . '\\u003e\\u003c/' . self::$allowed_tag . '\\u003e"} /--><!-- /wp:outer -->',
				'<!-- wp:outer --><!-- wp:inner {"key":"\\u003c' . self::$allowed_tag . '\\u003e\\u003c\\/' . self::$allowed_tag . '\\u003e"} /--><!-- /wp:outer -->',
			),

			// Block with safe attributes.
			array(
				'<!-- wp:example {"object":{"object":{"ok":true},"array":[{"ok":true},[],"ok",10,true,false,null],"string":"ok","number":10,"true":true,"false":false,"null":null},"array":[{"ok":true},[],"ok",10,true,false,null],"string":"ok","number":10,"true":true,"false":false,"null":null} /-->',
				'<!-- wp:example {"object":{"object":{"ok":true},"array":[{"ok":true},[],"ok",10,true,false,null],"string":"ok","number":10,"true":true,"false":false,"null":null},"array":[{"ok":true},[],"ok",10,true,false,null],"string":"ok","number":10,"true":true,"false":false,"null":null} /-->',
			),

			// Block with unsafe nested object attributes.
			array(
				'<!-- wp:example {"object":{"one":{"unsafe":"\\u003cmarquee\\u003e\\u003c/marquee\\u003e\\u003c' . self::$allowed_tag . '\\u003e\\u003c/' . self::$allowed_tag . '\\u003e"},"two":"\\u003cmarquee\\u003e\\u003c/marquee\\u003e\\u003c' . self::$allowed_tag . '\\u003e\\u003c/' . self::$allowed_tag . '\\u003e"}} /-->',
				'<!-- wp:example {"object":{"one":{"unsafe":"\\u003c' . self::$allowed_tag . '\\u003e\\u003c\\/' . self::$allowed_tag . '\\u003e"},"two":"\\u003c' . self::$allowed_tag . '\\u003e\\u003c\\/' . self::$allowed_tag . '\\u003e"}} /-->',
			),

			// Block with unsafe nested array attributes.
			array(
				'<!-- wp:example {"array":[{"one":{"unsafe":"\\u003cmarquee\\u003e\\u003c/marquee\\u003e\\u003c' . self::$allowed_tag . '\\u003e\\u003c/' . self::$allowed_tag . '\\u003e"},"two":"\\u003cmarquee\\u003e\\u003c/marquee\\u003e\\u003c' . self::$allowed_tag . '\\u003e\\u003c/' . self::$allowed_tag . '\\u003e"},["\\u003cmarquee\\u003e\\u003c/marquee\\u003e\\u003c' . self::$allowed_tag . '\\u003e\\u003c/' . self::$allowed_tag . '\\u003e"],"\\u003cmarquee\\u003e\\u003c/marquee\\u003e\\u003c' . self::$allowed_tag . '\\u003e\\u003c/' . self::$allowed_tag . '\\u003e"]} /-->',
				'<!-- wp:example {"array":[{"one":{"unsafe":"\\u003c' . self::$allowed_tag . '\\u003e\\u003c\\/' . self::$allowed_tag . '\\u003e"},"two":"\\u003c' . self::$allowed_tag . '\\u003e\\u003c\\/' . self::$allowed_tag . '\\u003e"},["\\u003c' . self::$allowed_tag . '\\u003e\\u003c\\/' . self::$allowed_tag . '\\u003e"],"\\u003c' . self::$allowed_tag . '\\u003e\\u003c\\/' . self::$allowed_tag . '\\u003e"]} /-->',
			),

			// Block with unsafe object keys.
			array(
				'<!-- wp:example {"object":{"\\u003cmarquee\\u003e\\u003c/marquee\\u003e\\u003c' . self::$allowed_tag . '\\u003e\\u003c/' . self::$allowed_tag . '\\u003e":"value"}} /-->',
				'<!-- wp:example {"object":{"\\u003c' . self::$allowed_tag . '\\u003e\\u003c\\/' . self::$allowed_tag . '\\u003e":"value"}} /-->',
			),
		);
	}

}
