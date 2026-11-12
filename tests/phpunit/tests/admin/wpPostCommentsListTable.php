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
	public function test_get_views_should_always_include_every_status_key() {
		$this->table->prepare_items();

		$views = $this->table->get_views();

		foreach ( array( 'all', 'mine', 'moderated', 'approved', 'spam', 'trash' ) as $status ) {
			$this->assertArrayHasKey( $status, $views, "The \"$status\" view should always be present in get_views(), even at zero." );
		}
	}

	/**
	 * @covers WP_Post_Comments_List_Table::get_views
	 * @covers WP_Post_Comments_List_Table::views
	 */
	public function test_views_should_mark_empty_status_links_as_hidden() {
		$this->table->prepare_items();

		$output = get_echo( array( $this->table, 'views' ) );

		$this->assertStringNotContainsString( "class='all is-empty-view'", $output, 'The "All" view should never be marked hidden.' );
		$this->assertStringNotContainsString( "class='moderated is-empty-view'", $output, '"Pending" should never be marked hidden, even at zero.' );
		$this->assertStringNotContainsString( "class='approved is-empty-view'", $output, '"Approved" should never be marked hidden, even at zero.' );
		$this->assertStringContainsString( "class='mine is-empty-view'", $output, '"Mine" should be marked hidden at zero.' );
		$this->assertStringContainsString( "class='spam is-empty-view'", $output, '"Spam" should be marked hidden at zero.' );
		$this->assertStringContainsString( "class='trash is-empty-view'", $output, '"Trash" should be marked hidden at zero.' );
	}
}
