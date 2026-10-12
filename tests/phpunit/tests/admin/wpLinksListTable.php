<?php

/**
 * @group admin
 *
 * @covers WP_Links_List_Table
 */
class Tests_Admin_wpLinksListTable extends WP_UnitTestCase {

	/**
	 * @ticket 63971
	 *
	 * @covers WP_Links_List_Table::extra_tablenav
	 */
	public function test_link_category_filter_label_is_visible() {
		$table = _get_list_table( 'WP_Links_List_Table', array( 'screen' => 'link-manager' ) );

		ob_start();
		$table->extra_tablenav( 'top' );
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '#<div class="tablenav-filter"><label for="cat_id">Filter by category</label><select\s+name=\'cat_id\' id=\'cat_id\'#', $output );
	}
}
