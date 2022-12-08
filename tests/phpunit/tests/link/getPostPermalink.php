<?php
/**
 * @group link
 * @covers ::get_post_permalink
 */
class Tests_Link_GetPostPermalink extends WP_UnitTestCase {

	public function test_get_post_permalink_should_return_string_on_success() {
		$post = self::factory()->post->create();

		$this->assertIsString( get_post_permalink( $post ) );
	}

	public function test_get_post_permalink_should_return_false_for_non_existing_post() {
		$this->assertFalse( get_post_permalink( -1 ) );
	}

}
