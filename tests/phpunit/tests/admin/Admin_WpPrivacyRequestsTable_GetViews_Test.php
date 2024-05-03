<?php

require_once __DIR__ . '/Admin_WpPrivacyRequestsTable_TestCase.php';

/**
 * Test `WP_Privacy_Requests_Table::get_views`.
 *
 * @package WordPress\UnitTests
 *
 * @since 5.1.0
 *
 * @group admin
 * @group privacy
 * @covers WP_Privacy_Requests_Table::get_views
 */
class Admin_WpPrivacyRequestsTable_GetViews_Test extends Admin_WpPrivacyRequestsTable_TestCase {

	/**
	 * @ticket 42066
	 */
	public function test_get_views_should_return_views_by_default() {
		$expected = array(
			'all' => '<a href="http://example.org/wp-admin/export-personal-data.php" class="current" aria-current="page">All <span class="count">(0)</span></a>',
		);

		$this->assertSame( $expected, $this->get_mocked_class_instance()->get_views() );
	}
}
