<?php

/**
 * @group admin
 * @group user
 *
 * @covers WP_Users_List_Table::get_views
 */
class Admin_WpUsersListTable_GetViews_Test extends WP_UnitTestCase {
	/**
	 * @var WP_Users_List_Table
	 */
	public $table = false;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Users_List_Table', array( 'screen' => 'users' ) );
	}

	/**
	 * @ticket 42066
	 */
	public function test_get_views_should_return_views_by_default() {
		$expected = array(
			'all'           => '<a href="users.php" class="current" aria-current="page">All <span class="count">(1)</span></a>',
			'administrator' => '<a href="users.php?role=administrator">Administrator <span class="count">(1)</span></a>',
		);

		$this->assertSame( $expected, $this->table->get_views() );
	}
}
