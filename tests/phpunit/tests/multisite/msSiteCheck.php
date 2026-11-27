<?php

/**
 * Tests for the `ms_site_check()` function.
 *
 * @group ms-required
 * @group multisite
 *
 * @covers ::ms_site_check
 */
class Tests_Multisite_MsSiteCheck extends WP_UnitTestCase {

	/**
	 * Tests that ms_site_check() returns true without warnings when get_site() returns null.
	 *
	 * @ticket 66760
	 */
	public function test_ms_site_check_returns_true_when_current_site_is_null() {
		$original_blog_id = $GLOBALS['blog_id'];

		try {
			$GLOBALS['blog_id'] = 0;
			$result             = ms_site_check();
		} finally {
			$GLOBALS['blog_id'] = $original_blog_id;
		}

		$this->assertTrue( $result );
	}
}
