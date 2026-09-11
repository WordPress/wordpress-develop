<?php

/**
 * @group admin
 * @group user
 *
 * @covers WP_User_Search::__construct
 */
class Admin_WpUserSearch_Construct_Test extends WP_UnitTestCase {

	/**
	 * @expectedDeprecated WP_User_Search
	 */
	public function test_class_is_deprecated() {
		$wp_user_search = new WP_User_Search();
	}
}
