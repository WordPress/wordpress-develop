<?php

/**
 * @group admin
 * @group user
 *
 * @covers WP_Users_List_Table
 */
class Tests_Admin_wpUsersListTable extends WP_UnitTestCase {
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
	 *
	 * @covers WP_Users_List_Table::get_views
	 */
	public function test_get_views_should_return_views_by_default() {
		$expected = array(
			'all'           => '<a href="users.php" class="current" aria-current="page">All <span class="count">(1)</span></a>',
			'administrator' => '<a href="users.php?role=administrator">Administrator <span class="count">(1)</span></a>',
		);

		$this->assertSame( $expected, $this->table->get_views() );
	}

	/**
	 * @ticket 57233
	 * @group ms-required
	 *
	 * @covers WP_Users_List_Table::single_row
	 */
	public function test_reset_password_row_action_targets_site_users_screen() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$site_id  = get_current_blog_id();

		grant_super_admin( $admin_id );
		wp_set_current_user( $admin_id );

		$table          = _get_list_table( 'WP_Users_List_Table', array( 'screen' => 'site-users-network' ) );
		$table->site_id = $site_id;
		$row            = $table->single_row( get_userdata( $user_id ) );

		$reset_url = wp_nonce_url( "site-users.php?id={$site_id}&amp;action=resetpassword&amp;users={$user_id}", 'bulk-users' );
		$this->assertStringContainsString( $reset_url, $row );
	}
}
