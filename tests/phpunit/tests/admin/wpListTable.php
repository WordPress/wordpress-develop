<?php

/**
 * @group admin
 *
 * @covers WP_List_Table
 */
class Tests_Admin_WpListTable extends WP_UnitTestCase {

	/**
	 * Tests that `WP_List_Table::get_column_info()` only adds the primary
	 * column header when necessary.
	 *
	 * @ticket 34564
	 *
	 * @dataProvider data_should_only_add_primary_column_when_needed
	 *
	 * @covers WP_List_Table::get_column_info
	 *
	 * @param string $list_class The name of the WP_List_Table child class.
	 * @param array  $headers    A list of column headers.
	 * @param array  $expected   The expected column headers.
	 */
	public function test_should_only_add_primary_column_when_needed( $list_class, $headers, $expected ) {
		// This is reset between tests.
		$GLOBALS['hook_suffix'] = 'my-hook';

		$list_table = _get_list_table( $list_class );

		$column_headers = new ReflectionProperty( $list_table, '_column_headers' );
		$column_headers->setAccessible( true );
		$column_headers->setValue( $list_table, $headers );

		$column_info = new ReflectionMethod( $list_table, 'get_column_info' );
		$column_info->setAccessible( true );

		$this->assertSame( $expected, $column_info->invoke( $list_table ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_only_add_primary_column_when_needed() {
		$list_primary_columns = array(
			'WP_Application_Passwords_List_Table'         => 'name',
			'WP_Comments_List_Table'                      => 'author',
			'WP_Links_List_Table'                         => 'name',
			'WP_Media_List_Table'                         => 'title',
			'WP_MS_Sites_List_Table'                      => 'blogname',
			'WP_MS_Themes_List_Table'                     => 'name',
			'WP_MS_Users_List_Table'                      => 'username',
			'WP_Plugin_Install_List_Table'                => '',
			'WP_Plugins_List_Table'                       => 'name',
			'WP_Post_Comments_List_Table'                 => 'author',
			'WP_Posts_List_Table'                         => 'title',
			'WP_Privacy_Data_Export_Requests_List_Table'  => 'email',
			'WP_Privacy_Data_Removal_Requests_List_Table' => 'email',
			'WP_Terms_List_Table'                         => 'name',
			'WP_Theme_Install_List_Table'                 => '',
			'WP_Themes_List_Table'                        => '',
			'WP_Users_List_Table'                         => 'username',
		);

		$datasets = array();

		foreach ( $list_primary_columns as $list_class => $primary_column ) {
			$datasets[ $list_class . ' - three columns' ] = array(
				'list_class' => $list_class,
				'headers'    => array( 'First', 'Second', 'Third' ),
				'expected'   => array( 'First', 'Second', 'Third', $primary_column ),
			);

			$datasets[ $list_class . ' - four columns' ] = array(
				'list_class' => $list_class,
				'headers'    => array( 'First', 'Second', 'Third', 'Fourth' ),
				'expected'   => array( 'First', 'Second', 'Third', 'Fourth' ),
			);
		}

		return $datasets;
	}
}
