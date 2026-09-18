<?php

/**
 * @group post
 *
 * @covers ::_truncate_post_slug
 */
class Tests_Post_TruncatePostSlug extends WP_UnitTestCase {

	/**
	 * _truncate_post_slug() is retained as an alias of _truncate_slug().
	 *
	 * @ticket 46010
	 */
	public function test_truncate_post_slug_should_be_an_alias_of_truncate_slug() {
		$slug = 'myslug%C4%85';

		$this->assertSame( _truncate_slug( $slug, 9 ), _truncate_post_slug( $slug, 9 ) );
		$this->assertSame( _truncate_slug( $slug ), _truncate_post_slug( $slug ) );
	}
}
