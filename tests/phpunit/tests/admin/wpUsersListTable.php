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
	 * @ticket 29128
	 *
	 * @covers WP_Users_List_Table::get_columns
	 */
	public function test_get_columns_should_label_posts_column_as_content() {
		$columns = $this->table->get_columns();

		$this->assertSame( 'Content', $columns['posts'] );
	}

	/**
	 * @ticket 29128
	 *
	 * @covers WP_Users_List_Table::display_rows
	 */
	public function test_display_rows_should_count_authored_content_items() {
		register_post_type(
			'book',
			array(
				'public'   => true,
				'show_ui'  => true,
				'supports' => array( 'title', 'author' ),
			)
		);

		register_post_type(
			'secret_note',
			array(
				'public'   => true,
				'show_ui'  => false,
				'supports' => array( 'title', 'author' ),
			)
		);

		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_type'   => 'post',
			)
		);

		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_type'   => 'page',
			)
		);

		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_type'   => 'book',
			)
		);

		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_type'   => 'secret_note',
			)
		);

		try {
			$this->table->items = array(
				$user_id => get_userdata( $user_id ),
			);

			ob_start();
			$this->table->display_rows();
			$output = ob_get_clean();

			$this->assertStringNotContainsString( 'edit.php?author=' . $user_id, $output );
			$this->assertStringContainsString( '3 content items by this author', $output );
		} finally {
			unregister_post_type( 'book' );
			unregister_post_type( 'secret_note' );
		}
	}
}
