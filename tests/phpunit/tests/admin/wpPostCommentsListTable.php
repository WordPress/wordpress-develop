<?php

/**
 * @group admin
 *
 * @covers WP_Post_Comments_List_Table
 */
class Tests_Admin_wpPostCommentsListTable extends WP_UnitTestCase {

	/**
	 * @var WP_Post_Comments_List_Table
	 */
	protected $table;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Post_Comments_List_Table', array( 'screen' => 'edit-post-comments' ) );
	}

	/**
	 * @ticket 42066
	 *
	 * @covers WP_Post_Comments_List_Table::get_views
	 */
	public function test_get_views_should_return_views_by_default() {
		$this->table->prepare_items();

		$expected = array(
			'all'       => '<a href="http://example.org/wp-admin/edit-comments.php?comment_status=all" class="current" aria-current="page">All <span class="count">(<span class="all-count">0</span>)</span></a>',
			'mine'      => '<a href="http://example.org/wp-admin/edit-comments.php?comment_status=mine&#038;user_id=0">Mine <span class="count">(<span class="mine-count">0</span>)</span></a>',
			'moderated' => '<a href="http://example.org/wp-admin/edit-comments.php?comment_status=moderated">Pending <span class="count">(<span class="pending-count">0</span>)</span></a>',
			'approved'  => '<a href="http://example.org/wp-admin/edit-comments.php?comment_status=approved">Approved <span class="count">(<span class="approved-count">0</span>)</span></a>',
			'spam'      => '<a href="http://example.org/wp-admin/edit-comments.php?comment_status=spam">Spam <span class="count">(<span class="spam-count">0</span>)</span></a>',
			'trash'     => '<a href="http://example.org/wp-admin/edit-comments.php?comment_status=trash">Trash <span class="count">(<span class="trash-count">0</span>)</span></a>',
		);
		$this->assertSame( $expected, $this->table->get_views() );
	}

}
