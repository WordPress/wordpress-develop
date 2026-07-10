<?php

/**
 * @group admin
 */
class Tests_Admin_wpLinksListTable extends WP_UnitTestCase {
	/**
	 * @var WP_Links_List_Table
	 */
	private $table;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Links_List_Table', array( 'screen' => 'link-manager' ) );
	}

	/**
	 * @covers WP_Links_List_Table::print_column_headers
	 */
	public function test_sortable_columns_set_rating_descending_by_default() {
		$output = get_echo( array( $this->table, 'print_column_headers' ) );

		$this->assertStringContainsString( '?orderby=rating&#038;order=desc', $output, 'Mismatch of the default link ordering for rating column. Should be desc.' );
		$this->assertStringContainsString( 'column-rating sortable asc', $output, 'Mismatch of CSS classes for the rating column.' );
	}
}
