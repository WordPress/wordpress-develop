<?php
/**
 * @group link
 * @covers ::get_page_link
 * @covers ::_get_page_link
 */
class Tests_Link_GetPageLink extends WP_UnitTestCase {

	public function test_get_page_link_should_return_string_on_success() {
		$post = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertIsString( get_page_link( $post ) );
	}

	public function test_get_page_link_should_return_false_for_non_existing_post() {
		$this->assertFalse( get_page_link( -1 ) );
	}

	public function test_underscore_get_page_link_should_return_false_for_non_existing_post() {
		$this->assertFalse( _get_page_link( -1 ) );
	}
}
