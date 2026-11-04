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

	/**
	 * Super admins see which network users share an email address, ignoring letter case.
	 *
	 * @ticket 66238
	 *
	 * @covers WP_MS_Users_List_Table::display_rows
	 * @covers WP_MS_Users_List_Table::column_email
	 */
	public function test_display_rows_highlights_duplicate_email() {
		global $wpdb;

		$super_admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin_id );
		wp_set_current_user( $super_admin_id );

		$table = _get_list_table( 'WP_MS_Users_List_Table', array( 'screen' => 'users-network' ) );

		$original = self::factory()->user->create( array( 'user_email' => 'abc@example.com' ) );
		$variant  = self::factory()->user->create( array( 'user_email' => 'placeholder@example.com' ) );
		$unique   = self::factory()->user->create( array( 'user_email' => 'unique@example.com' ) );

		// Duplicates can no longer be created through the API, so simulate legacy data.
		$wpdb->update( $wpdb->users, array( 'user_email' => 'ABc@example.com' ), array( 'ID' => $variant ) );
		clean_user_cache( $variant );

		$table->items = array(
			get_userdata( $original ),
			get_userdata( $unique ),
		);

		$output = get_echo( array( $table, 'display_rows' ) );

		$this->assertSame( 1, substr_count( $output, 'class="duplicate-email"' ), 'Exactly one row should be highlighted.' );
		$this->assertMatchesRegularExpression( "#<tr id=\"user-{$original}\".*?class=\"duplicate-email\".*?</tr>#s", $output, 'The duplicate user row should be highlighted.' );
	}
}
