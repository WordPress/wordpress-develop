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
	 * @covers WP_Post_Comments_List_Table::get_views
	 */
	public function test_get_views_should_hide_empty_status_links() {
		$this->table->prepare_items();

		$views = $this->table->get_views();

		$this->assertArrayHasKey( 'all', $views, 'The "All" view should always be shown.' );
		$this->assertArrayHasKey( 'moderated', $views, '"Pending" should always be shown, even at zero.' );
		$this->assertArrayHasKey( 'approved', $views, '"Approved" should always be shown, even at zero.' );
		$this->assertArrayNotHasKey( 'mine', $views, 'Views with a zero count should be hidden.' );
		$this->assertArrayNotHasKey( 'spam', $views, 'Views with a zero count should be hidden.' );
		$this->assertArrayNotHasKey( 'trash', $views, 'Views with a zero count should be hidden.' );
	}
}
