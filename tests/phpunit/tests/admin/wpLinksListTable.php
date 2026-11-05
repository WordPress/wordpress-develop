<?php

/**
 * @group admin
 */
class Tests_Admin_wpLinksListTable extends WP_UnitTestCase {
	/**
	 * A list table for testing.
	 *
	 * @var WP_Links_List_Table
	 */
	protected $table;

	public function set_up() {
		parent::set_up();

		$this->table = _get_list_table( 'WP_Links_List_Table', array( 'screen' => 'link-manager' ) );
	}

	/**
	 * Tests that `WP_Links_List_Table::column_name()` strips HTML from the link
	 * name used within the `aria-label` attribute.
	 *
	 * The name is escaped by `WP_Links_List_Table::display_rows()`, so any HTML
	 * it contains survives `esc_attr()` and is announced as literal text by
	 * screen readers.
	 *
	 * @ticket 65729
	 *
	 * @covers WP_Links_List_Table::column_name
	 */
	public function test_column_name_should_strip_html_from_the_aria_label() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// A link name is saved to the database with any HTML it contains already escaped.
		$link = self::factory()->bookmark->create_and_get( array( 'link_name' => 'my&lt;div&gt;link' ) );

		// Replicate the escaping applied by `WP_Links_List_Table::display_rows()`.
		$link->link_name = esc_attr( $link->link_name );

		ob_start();
		$this->table->column_name( $link );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'aria-label="Edit &#8220;mylink&#8221;"',
			$output,
			'The aria-label did not contain the stripped name.'
		);
		$this->assertStringContainsString(
			'>my&lt;div&gt;link</a>',
			$output,
			'The displayed link name should be left unchanged.'
		);
	}
}
