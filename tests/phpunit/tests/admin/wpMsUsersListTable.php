<?php

/**
 * @group admin
 * @group user
 * @group ms-required
 *
 * @covers WP_MS_Users_List_Table
 */
class Tests_Admin_wpMsUsersListTable extends WP_UnitTestCase {

	/**
	 * The user login is escaped in the username column and the checkbox label.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_MS_Users_List_Table::column_username
	 * @covers WP_MS_Users_List_Table::column_cb
	 */
	public function test_columns_escape_user_login() {
		$super_admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin_id );
		wp_set_current_user( $super_admin_id );

		$table = _get_list_table( 'WP_MS_Users_List_Table', array( 'screen' => 'users-network' ) );

		$user             = get_userdata( self::factory()->user->create() );
		$user->user_login = '<svg onload=alert(1)>';

		foreach ( array( 'column_username', 'column_cb' ) as $column ) {
			$output = get_echo( array( $table, $column ), array( $user ) );

			$this->assertStringNotContainsString( '<svg', $output, "The user login should be escaped in {$column}()." );
			$this->assertStringContainsString( '&lt;svg onload=alert(1)&gt;', $output, "The escaped login should be shown in {$column}()." );
		}
	}
}
