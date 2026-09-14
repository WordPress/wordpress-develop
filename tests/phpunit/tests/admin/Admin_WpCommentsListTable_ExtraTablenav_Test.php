<?php

/**
 * @group admin
 *
 * @covers WP_Comments_List_Table::extra_tablenav
 */
class Admin_WpCommentsListTable_ExtraTablenav_Test extends WP_UnitTestCase {

	/**
	 * @var WP_Comments_List_Table
	 */
	protected $table;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Comments_List_Table', array( 'screen' => 'edit-comments' ) );
	}

	/**
	 * @ticket 40188
	 */
	public function test_filter_button_should_not_be_shown_if_there_are_no_comments() {
		ob_start();
		$this->table->extra_tablenav( 'top' );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'id="post-query-submit"', $output );
	}

	/**
	 * @ticket 40188
	 */
	public function test_filter_button_should_be_shown_if_there_are_comments() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);

		$this->table->prepare_items();

		ob_start();
		$this->table->extra_tablenav( 'top' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="post-query-submit"', $output );
	}

	/**
	 * @ticket 40188
	 */
	public function test_filter_comment_type_dropdown_should_be_shown_if_there_are_comments() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);

		$this->table->prepare_items();

		ob_start();
		$this->table->extra_tablenav( 'top' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="filter-by-comment-type"', $output );
		$this->assertStringContainsString( "<option value='comment'>", $output );
	}

	/**
	 * @ticket 38341
	 */
	public function test_empty_trash_button_should_not_be_shown_if_there_are_no_comments() {
		ob_start();
		$this->table->extra_tablenav( 'top' );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'id="delete_all"', $output );
	}
}
