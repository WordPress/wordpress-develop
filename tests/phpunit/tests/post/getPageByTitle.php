<?php

/**
 * @group post
 *
 * @covers ::get_page_by_title
 */
class Tests_Post_GetPageByTitle extends WP_UnitTestCase {

	/**
	 * Tests that `get_page_by_title()` has been deprecated.
	 *
	 * @ticket 57041
	 *
	 * @expectedDeprecated get_page_by_title
	 */
	public function test_get_page_by_title_should_be_deprecated() {
		$this->assertNull( get_page_by_title( '#57041 Page' ) );
	}

}
