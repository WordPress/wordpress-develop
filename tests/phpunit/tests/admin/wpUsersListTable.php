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
	 * @ticket 66238
	 *
	 * @covers WP_Users_List_Table::get_duplicate_email_user_ids
	 *
	 * @dataProvider data_email_case_sensitivity
	 *
	 * @param bool $is_case_sensitive Whether the database compares user emails case-sensitively.
	 */
	public function test_get_duplicate_email_user_ids_flags_case_variants( $is_case_sensitive ) {
		global $wpdb;

		$original = self::factory()->user->create( array( 'user_email' => 'abc@example.com' ) );
		$variant  = self::factory()->user->create( array( 'user_email' => 'placeholder@example.com' ) );
		$unique   = self::factory()->user->create( array( 'user_email' => 'unique@example.com' ) );

		// Duplicates can no longer be created through the API, so simulate legacy data.
		$wpdb->update( $wpdb->users, array( 'user_email' => 'ABc@example.com' ), array( 'ID' => $variant ) );
		clean_user_cache( $variant );

		add_filter( 'wp_is_user_email_case_sensitive', $is_case_sensitive ? '__return_true' : '__return_false' );

		$method = new ReflectionMethod( $this->table, 'get_duplicate_email_user_ids' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$queries = array();
		$collect = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $collect );

		$flagged = $method->invoke( $this->table, array( $original, $unique ) );

		remove_filter( 'query', $collect );

		$this->assertSame( array( $original ), $flagged, 'Only the user sharing an address should be flagged, even when the other account is not on the page.' );

		$lower_queries = preg_grep( '/LOWER\(user_email\)/', $queries );
		if ( $is_case_sensitive ) {
			$this->assertNotEmpty( $lower_queries, 'A case-sensitive collation should use a LOWER() comparison.' );
		} else {
			$this->assertEmpty( $lower_queries, 'A case-insensitive collation should not use LOWER(), so that the index can be used.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_email_case_sensitivity() {
		return array(
			'case-insensitive collation' => array( false ),
			'case-sensitive collation'   => array( true ),
		);
	}

	/**
	 * @ticket 66238
	 *
	 * @covers WP_Users_List_Table::display_rows
	 */
	public function test_display_rows_highlights_duplicate_email() {
		global $wpdb;

		$original = self::factory()->user->create( array( 'user_email' => 'abc@example.com' ) );
		$variant  = self::factory()->user->create( array( 'user_email' => 'placeholder@example.com' ) );
		$unique   = self::factory()->user->create( array( 'user_email' => 'unique@example.com' ) );

		$wpdb->update( $wpdb->users, array( 'user_email' => 'ABc@example.com' ), array( 'ID' => $variant ) );
		clean_user_cache( $variant );

		$this->table->items = array(
			$original => get_userdata( $original ),
			$unique   => get_userdata( $unique ),
		);

		ob_start();
		$this->table->display_rows();
		$output = ob_get_clean();

		$this->assertSame( 1, substr_count( $output, 'class="duplicate-email"' ), 'Exactly one row should be highlighted.' );
		$this->assertMatchesRegularExpression( "#<tr id='user-{$original}'.*?class=\"duplicate-email\".*?</tr>#s", $output, 'The duplicate user row should be highlighted.' );
	}
}
