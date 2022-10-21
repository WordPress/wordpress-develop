<?php

/**
 * @group post
 *
 * @covers ::_truncate_post_slug
 */
class Tests_Post_TruncatePostSlug extends WP_UnitTestCase {

	public function test_truncate_post_slug_truncates_too_long_slugs() {
		$this->assertSame( 'truncated', _truncate_post_slug( 'truncated slug', 9 ) );
	}

	public function test_truncate_post_slug_truncates_too_long_slugs_without_dashes() {
		$this->assertSame( 'truncated', _truncate_post_slug( 'truncated-slug', 10 ) );
	}

	public function test_truncate_post_slug_strips_urlencoded_characters_as_a_unit_slash() {
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%2F', 7 ) );
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%2F', 8 ) );
		$this->assertSame( 'myslug%2F', _truncate_post_slug( 'myslug%2F', 9 ) );
	}

	public function test_truncate_post_slug_strips_urlencoded_characters_as_a_unit_accent() {
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%C4%85', 7 ) );
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%C4%85', 8 ) );
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%C4%85', 9 ) );
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%C4%85', 10 ) );
		$this->assertSame( 'myslug', _truncate_post_slug( 'myslug%C4%85', 11 ) );
		$this->assertSame( 'myslug%C4%85', _truncate_post_slug( 'myslug%C4%85', 12 ) );
	}
}
