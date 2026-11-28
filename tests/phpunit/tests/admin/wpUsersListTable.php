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
	 * @ticket 61107
	 * @group ms-required
	 *
	 * @covers WP_Users_List_Table::extra_tablenav
	 */
	public function test_network_site_users_role_dropdown_lists_roles_for_the_target_site() {
		$current_site_id = get_current_blog_id();
		$target_site_id  = self::factory()->blog->create();
		$user_id         = self::factory()->user->create();
		$original_id     = $_REQUEST['id'] ?? null;

		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );
		add_role( 'request-site-role', 'Request site role' );

		switch_to_blog( $target_site_id );
		add_role( 'target-site-role', 'Target site role' );
		restore_current_blog();
		add_user_to_blog( $target_site_id, $user_id, 'target-site-role' );

		try {
			$_REQUEST['id'] = (string) $target_site_id;
			$table          = _get_list_table( 'WP_Users_List_Table', array( 'screen' => 'site-users-network' ) );
			$table->prepare_items();

			$tablenav_site_ids = array();
			add_action(
				'restrict_manage_users',
				static function () use ( &$tablenav_site_ids ) {
					$tablenav_site_ids[] = get_current_blog_id();
				}
			);

			foreach ( array( 'top', 'bottom' ) as $position ) {
				ob_start();
				$table->extra_tablenav( $position );
				$output = ob_get_clean();

				$this->assertStringContainsString( "<option value='target-site-role'>", $output, $position );
				$this->assertStringNotContainsString( "<option value='request-site-role'>", $output, $position );
				$this->assertStringContainsString( '<option value="none">', $output, $position );
				$this->assertSame( $current_site_id, get_current_blog_id(), $position );
			}

			$this->assertSame( array( $current_site_id, $current_site_id ), $tablenav_site_ids );
		} finally {
			if ( null === $original_id ) {
				unset( $_REQUEST['id'] );
			} else {
				$_REQUEST['id'] = $original_id;
			}
			remove_role( 'request-site-role' );
			revoke_super_admin( $user_id );
		}
	}

	/**
	 * The user login is escaped in the username column and the checkbox label.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Users_List_Table::single_row
	 */
	public function test_single_row_escapes_user_login() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$user             = get_userdata( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$user->user_login = '<svg onload=alert(1)>';

		$row = $this->table->single_row( $user );

		$this->assertStringNotContainsString( '<svg', $row, 'The user login should be escaped.' );
		// The login is an edit link when the current user can edit the user (not on multisite for site admins).
		$this->assertMatchesRegularExpression( '#<strong>(<a [^>]+>)?&lt;svg onload=alert\(1\)&gt;#', $row, 'The escaped login should be shown in the username column.' );
		$this->assertStringContainsString( 'Select &lt;svg onload=alert(1)&gt;', $row, 'The escaped login should be in the checkbox label.' );
	}
}
