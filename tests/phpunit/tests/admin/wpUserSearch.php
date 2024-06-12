<?php
/**
 * @group admin
 * @group user
 *
 * @coversDefaultClass WP_User_Search
 */
class Tests_Admin_wpUserSearch extends WP_UnitTestCase {

	/**
	 * @covers ::__construct()
	 * @expectedDeprecated WP_User_Search
	 */
	public function test_class_is_deprecated() {
		$wp_user_search = new WP_User_Search();
	}
}
