<?php

/**
 * @group admin
 * @group application-passwords
 *
 * @covers WP_Application_Passwords_List_Table
 */
class Tests_Admin_wpApplicationPasswordsListTable extends WP_UnitTestCase {

	/**
	 * The list table instance under test.
	 *
	 * @var WP_Application_Passwords_List_Table
	 */
	private $table;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table(
			'WP_Application_Passwords_List_Table',
			array( 'screen' => 'application-passwords-user' )
		);
	}

	/**
	 * Ensures the JavaScript row template used for a password created via AJAX
	 * renders the primary column as a `<th scope="row">` row header, matching
	 * the server-rendered markup from WP_List_Table::single_row_columns().
	 *
	 * @ticket 65707
	 *
	 * @covers WP_Application_Passwords_List_Table::print_js_template_row
	 */
	public function test_print_js_template_row_uses_th_row_header_for_primary_column() {
		ob_start();
		$this->table->print_js_template_row();
		$html = ob_get_clean();

		// Assert on the tag structure and `scope="row"` rather than the localized
		// column label, so the test does not depend on the current locale or on
		// filters that adjust the column headers.
		$this->assertMatchesRegularExpression(
			'/<th class="name column-name has-row-actions column-primary" data-colname="[^"]*" scope="row">/',
			$html,
			'The primary column should be rendered as a <th scope="row"> row header.'
		);

		$this->assertStringContainsString(
			'<td class="created column-created"',
			$html,
			'Non-primary columns should still be rendered as <td> cells.'
		);
	}
}
