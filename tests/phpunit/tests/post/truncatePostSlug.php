<?php

/**
 * @group post
 *
 * @covers ::_truncate_post_slug
 */
class Tests_Post_TruncatePostSlug extends WP_UnitTestCase {

	public function test_truncate_post_slug_truncates_too_long_slugs() {
		$this->assertSame( 'truncated', _truncate_post_slug( 'truncated slug', 9 ), '"truncated slug" should have been truncated to "truncated".' );
	}

	public function test_truncate_post_slug_truncates_too_long_slugs_without_dashes() {
		$this->assertSame( 'truncated', _truncate_post_slug( 'truncated-slug', 10 ), '"truncated-slug" should have been truncated to "truncated".' );
	}

	public function test_truncate_post_slug_strips_urlencoded_characters_as_a_unit_slash() {
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%2F', 7 ), '"myslug%2F" should have been truncated to "myslug".' );
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%2F', 8 ), '"myslug%2F" should have been truncated to "myslug".' );
		$this->assertSame( 'myslug%2F', _truncate_post_slug( 'myslug%2F', 9 ), '"myslug%2F" should have been truncated to "myslug%2F".' );
	}

	public function test_truncate_post_slug_strips_urlencoded_characters_as_a_unit_accent() {
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%C4%85', 7 ), '"myslug%C4%85" should have been truncated to "myslug".' );
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%C4%85', 8 ), '"myslug%C4%85" should have been truncated to "myslug".' );
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%C4%85', 9 ), '"myslug%C4%85" should have been truncated to "myslug".' );
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%C4%85', 10 ), '"myslug%C4%85" should have been truncated to "myslug".' );
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%C4%85', 11 ), '"myslug%C4%85" should have been truncated to "myslug".' );
		$this->assertSame( 'myslug%C4%85', _truncate_post_slug( 'myslug%C4%85', 12 ), '"myslug%C4%85" should have been truncated to "myslug%C4%85".' );
	}
}
