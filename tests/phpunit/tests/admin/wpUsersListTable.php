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

	/**
	 * The reset password action preserves the current users list URL.
	 *
	 * @ticket 57508
	 *
	 * @covers WP_Users_List_Table::single_row
	 */
	public function test_single_row_reset_password_action_preserves_list_table_query() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$user = get_userdata( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$request_uri            = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		$_SERVER['REQUEST_URI'] = '/wp-admin/users.php?s=example&paged=50';
		$row                    = $this->table->single_row( $user );
		if ( null === $request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $request_uri;
		}

		$this->assertStringContainsString( 'wp_http_referer=%2Fwp-admin%2Fusers.php%3Fs%3Dexample%26paged%3D50', $row );
	}
}
