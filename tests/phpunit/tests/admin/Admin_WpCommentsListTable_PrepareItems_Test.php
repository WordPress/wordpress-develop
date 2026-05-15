<?php

/**
 * @group admin
 *
 * @covers WP_Comments_List_Table::prepare_items
 */
class Admin_WpCommentsListTable_PrepareItems_Test extends WP_UnitTestCase {

	/**
	 * @var WP_Comments_List_Table
	 */
	protected $table;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Comments_List_Table', array( 'screen' => 'edit-comments' ) );
	}

	/**
	 * Verify that the comments table never shows the note comment_type.
	 *
	 * @ticket 64198
	 * @ticket 64474
	 *
	 * @dataProvider data_comment_type
	 *
	 * @param string $comment_type The comment_type parameter value to test.
	 */
	public function test_comments_list_table_does_not_show_note_comment_type( string $comment_type ) {
		$post_id = self::factory()->post->create();
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'This is a note.',
				'comment_type'     => 'note',
				'comment_approved' => '1',
				'comment_date'     => '2024-01-01 10:00:00',
				'comment_date_gmt' => '2024-01-01 10:00:00',
			)
		);
		$regular_comment_id       = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'This is a regular comment.',
				'comment_type'     => '',
				'comment_approved' => '1',
				'comment_date'     => '2024-01-01 11:00:00',
				'comment_date_gmt' => '2024-01-01 11:00:00',
			)
		);
		$pingback_comment_id      = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'This is a pingback comment.',
				'comment_type'     => '',
				'comment_approved' => '1',
				'comment_date'     => '2024-01-01 12:00:00',
				'comment_date_gmt' => '2024-01-01 12:00:00',
			)
		);
		$_REQUEST['comment_type'] = $comment_type;
		$this->table->prepare_items();
		$items = $this->table->items;
		$this->assertCount( 2, $items );
		$this->assertEquals( $pingback_comment_id, $items[0]->comment_ID );
		$this->assertEquals( $regular_comment_id, $items[1]->comment_ID );
	}

	/**
	 * Data provider for test_comments_list_table_does_not_show_note_comment_type().
	 *
	 * @return array<string, string[]>
	 */
	public function data_comment_type(): array {
		return array(
			'note type explicitly requested' => array( 'note' ),
			'all type requested'             => array( 'all' ),
		);
	}
}
