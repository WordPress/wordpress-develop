<?php

/**
 * @group admin
 *
 * @covers WP_List_Table
 */
class Tests_Admin_WpListTable extends WP_UnitTestCase {

	/**
	 * List table.
	 *
	 * @var WP_List_Table $list_table
	 */
	private $list_table;

	/**
	 * Original value of $GLOBALS['hook_suffix'].
	 *
	 * @var string
	 */
	private static $original_hook_suffix;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		static::$original_hook_suffix = $GLOBALS['hook_suffix'];

		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}

	public function set_up() {
		parent::set_up();
		global $hook_suffix;
		$hook_suffix      = '_wp_tests';
		$this->list_table = new WP_List_Table();
	}

	public function clean_up_global_scope() {
		global $hook_suffix;
		$hook_suffix = static::$original_hook_suffix;
		parent::clean_up_global_scope();
	}

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
	 * @param string $list_class          The name of the WP_List_Table child class.
	 * @param array  $headers             A list of column headers.
	 * @param array  $expected            The expected column headers.
	 * @param int    $expected_hook_count The expected number of times the hook is called.
	 */
	public function test_should_only_add_primary_column_when_needed( $list_class, $headers, $expected, $expected_hook_count ) {
		$hook = new MockAction();
		add_filter( 'list_table_primary_column', array( $hook, 'filter' ) );

		/*
		 * Set a dummy value for the current screen in the admin to prevent
		 * `_get_list_table()` throwing.
		 */
		$GLOBALS['hook_suffix'] = 'my-hook';

		$list_table = _get_list_table( $list_class );

		$column_headers = new ReflectionProperty( $list_table, '_column_headers' );
		$column_headers->setAccessible( true );
		$column_headers->setValue( $list_table, $headers );

		$column_info = new ReflectionMethod( $list_table, 'get_column_info' );
		$column_info->setAccessible( true );

		$this->assertSame( $expected, $column_info->invoke( $list_table ), 'The actual columns did not match the expected columns' );
		$this->assertSame( $expected_hook_count, $hook->get_call_count(), 'The hook was not called the expected number of times' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_only_add_primary_column_when_needed() {
		/*
		 * `WP_Post_Comments_List_Table` overrides `get_column_info()` rather than
		 * use the default `WP_List_Table::get_column_info()`. Therefore it is
		 * untested.
		 */
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
				'list_class'          => $list_class,
				'headers'             => array( 'First', 'Second', 'Third' ),
				'expected'            => array( 'First', 'Second', 'Third', $primary_column ),
				'expected_hook_count' => 1,
			);

			$datasets[ $list_class . ' - four columns' ] = array(
				'list_class'          => $list_class,
				'headers'             => array( 'First', 'Second', 'Third', 'Fourth' ),
				'expected'            => array( 'First', 'Second', 'Third', 'Fourth' ),
				'expected_hook_count' => 0,
			);
		}

		/*
		 * `WP_MS_Themes_List_Table` and `WP_Plugins_List_Table` override the
		 * `get_primary_column_name()` method rather than use the default
		 * `WP_List_Table::get_primary_column_name()`. Neither include the
		 * `list_table_primary_column` hook.
		 */
		$datasets['WP_MS_Themes_List_Table - three columns']['expected_hook_count'] = 0;
		$datasets['WP_Plugins_List_Table - three columns']['expected_hook_count']   = 0;

		return $datasets;
	}

	/**
	 * Tests the `WP_List_Table::get_views_links()` method.
	 *
	 * @ticket 42066
	 *
	 * @covers WP_List_Table::get_views_links
	 *
	 * @dataProvider data_get_views_links
	 *
	 * @param array $link_data {
	 *     An array of link data.
	 *
	 *     @type string $url     The link URL.
	 *     @type string $label   The link label.
	 *     @type bool   $current Optional. Whether this is the currently selected view.
	 * }
	 * @param array $expected
	 */
	public function test_get_views_links( $link_data, $expected ) {
		$get_views_links = new ReflectionMethod( $this->list_table, 'get_views_links' );
		$get_views_links->setAccessible( true );

		$actual = $get_views_links->invokeArgs( $this->list_table, array( $link_data ) );

		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_views_links() {
		return array(
			'one "current" link'                           => array(
				'link_data' => array(
					'all'       => array(
						'url'     => 'https://example.org/',
						'label'   => 'All',
						'current' => true,
					),
					'activated' => array(
						'url'     => add_query_arg( 'status', 'activated', 'https://example.org/' ),
						'label'   => 'Activated',
						'current' => false,
					),
				),
				'expected'  => array(
					'all'       => '<a href="https://example.org/" class="current" aria-current="page">All</a>',
					'activated' => '<a href="https://example.org/?status=activated">Activated</a>',
				),
			),
			'two "current" links'                          => array(
				'link_data' => array(
					'all'       => array(
						'url'     => 'https://example.org/',
						'label'   => 'All',
						'current' => true,
					),
					'activated' => array(
						'url'     => add_query_arg( 'status', 'activated', 'https://example.org/' ),
						'label'   => 'Activated',
						'current' => true,
					),
				),
				'expected'  => array(
					'all'       => '<a href="https://example.org/" class="current" aria-current="page">All</a>',
					'activated' => '<a href="https://example.org/?status=activated" class="current" aria-current="page">Activated</a>',
				),
			),
			'one "current" link and one without "current" key' => array(
				'link_data' => array(
					'all'       => array(
						'url'     => 'https://example.org/',
						'label'   => 'All',
						'current' => true,
					),
					'activated' => array(
						'url'   => add_query_arg( 'status', 'activated', 'https://example.org/' ),
						'label' => 'Activated',
					),
				),
				'expected'  => array(
					'all'       => '<a href="https://example.org/" class="current" aria-current="page">All</a>',
					'activated' => '<a href="https://example.org/?status=activated">Activated</a>',
				),
			),
			'one "current" link with escapable characters' => array(
				'link_data' => array(
					'all'       => array(
						'url'     => 'https://example.org/',
						'label'   => 'All',
						'current' => true,
					),
					'activated' => array(
						'url'     => add_query_arg(
							array(
								'status' => 'activated',
								'sort'   => 'desc',
							),
							'https://example.org/'
						),
						'label'   => 'Activated',
						'current' => false,
					),
				),
				'expected'  => array(
					'all'       => '<a href="https://example.org/" class="current" aria-current="page">All</a>',
					'activated' => '<a href="https://example.org/?status=activated&#038;sort=desc">Activated</a>',
				),
			),
		);
	}

	/**
	 * Tests that `WP_List_Table::get_views_links()` throws a `_doing_it_wrong()`.
	 *
	 * @ticket 42066
	 *
	 * @covers WP_List_Table::get_views_links
	 *
	 * @expectedIncorrectUsage WP_List_Table::get_views_links
	 *
	 * @dataProvider data_get_views_links_doing_it_wrong
	 *
	 * @param array $link_data {
	 *     An array of link data.
	 *
	 *     @type string $url     The link URL.
	 *     @type string $label   The link label.
	 *     @type bool   $current Optional. Whether this is the currently selected view.
	 * }
	 */
	public function test_get_views_links_doing_it_wrong( $link_data ) {
		$get_views_links = new ReflectionMethod( $this->list_table, 'get_views_links' );
		$get_views_links->setAccessible( true );
		$get_views_links->invokeArgs( $this->list_table, array( $link_data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_views_links_doing_it_wrong() {
		return array(
			'non-array $link_data'               => array(
				'link_data' => 'https://example.org, All, class="current" aria-current="page"',
			),
			'a link with no URL'                 => array(
				'link_data' => array(
					'all' => array(
						'label'   => 'All',
						'current' => true,
					),
				),
			),
			'a link with an empty URL'           => array(
				'link_data' => array(
					'all' => array(
						'url'     => '',
						'label'   => 'All',
						'current' => true,
					),
				),
			),
			'a link with a URL of only spaces'   => array(
				'link_data' => array(
					'all' => array(
						'url'     => '  ',
						'label'   => 'All',
						'current' => true,
					),
				),
			),
			'a link with a non-string URL'       => array(
				'link_data' => array(
					'all' => array(
						'url'     => array(),
						'label'   => 'All',
						'current' => true,
					),
				),
			),
			'a link with no label'               => array(
				'link_data' => array(
					'all' => array(
						'url'     => 'https://example.org/',
						'current' => true,
					),
				),
			),
			'a link with an empty label'         => array(
				'link_data' => array(
					'all' => array(
						'url'     => 'https://example.org/',
						'label'   => '',
						'current' => true,
					),
				),
			),
			'a link with a label of only spaces' => array(
				'link_data' => array(
					'all' => array(
						'url'     => 'https://example.org/',
						'label'   => '  ',
						'current' => true,
					),
				),
			),
			'a link with a non-string label'     => array(
				'link_data' => array(
					'all' => array(
						'url'     => 'https://example.org/',
						'label'   => array(),
						'current' => true,
					),
				),
			),
		);
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58896
	 *
	 * @covers WP_List_Table::__get()
	 *
	 * @param string $property_name Property name to get.
	 * @param mixed $expected       Expected value.
	 */
	public function test_should_get_compat_fields( $property_name, $expected ) {
		$list_table = new WP_List_Table( array( 'plural' => '_wp_tests__get' ) );

		if ( 'screen' === $property_name ) {
			$this->assertInstanceOf( $expected, $list_table->$property_name );
		} else {
			$this->assertSame( $expected, $list_table->$property_name );
		}
	}

	/**
	 * @ticket 58896
	 *
	 * @covers WP_List_Table::__get()
	 */
	public function test_should_throw_deprecation_when_getting_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_List_Table::__get(): ' .
			'The property `undeclared_property` is not declared. Getting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->assertNull( $this->list_table->undeclared_property, 'Getting a dynamic property should return null from WP_List_Table::__get()' );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58896
	 *
	 * @covers WP_List_Table::__set()
	 *
	 * @param string $property_name Property name to set.
	 */
	public function test_should_set_compat_fields_defined_property( $property_name ) {
		$value                            = uniqid();
		$this->list_table->$property_name = $value;

		$this->assertSame( $value, $this->list_table->$property_name );
	}

	/**
	 * @ticket 58896
	 *
	 * @covers WP_List_Table::__set()
	 */
	public function test_should_throw_deprecation_when_setting_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_List_Table::__set(): ' .
			'The property `undeclared_property` is not declared. Setting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->list_table->undeclared_property = 'some value';
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58896
	 *
	 * @covers WP_List_Table::__isset()
	 *
	 * @param string $property_name Property name to check.
	 * @param mixed $expected       Expected value.
	 */
	public function test_should_isset_compat_fields( $property_name, $expected ) {
		$actual = isset( $this->list_table->$property_name );
		if ( is_null( $expected ) ) {
			$this->assertFalse( $actual );
		} else {
			$this->assertTrue( $actual );
		}
	}

	/**
	 * @ticket 58896
	 *
	 * @covers WP_List_Table::__isset()
	 */
	public function test_should_throw_deprecation_when_isset_of_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_List_Table::__isset(): ' .
			'The property `undeclared_property` is not declared. Checking `isset()` on a dynamic property ' .
			'is deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->assertFalse( isset( $this->list_table->undeclared_property ), 'Checking a dynamic property should return false from WP_List_Table::__isset()' );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58896
	 *
	 * @covers WP_List_Table::__unset()
	 *
	 * @param string $property_name Property name to unset.
	 */
	public function test_should_unset_compat_fields_defined_property( $property_name ) {
		unset( $this->list_table->$property_name );
		$this->assertFalse( isset( $this->list_table->$property_name ) );
	}

	/**
	 * @ticket 58896
	 *
	 * @covers WP_List_Table::__unset()
	 */
	public function test_should_throw_deprecation_when_unset_of_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_List_Table::__unset(): ' .
			'A property `undeclared_property` is not declared. Unsetting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		unset( $this->list_table->undeclared_property );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_compat_fields() {
		return array(
			'_args'            => array(
				'property_name' => '_args',
				'expected'      => array(
					'plural'   => '_wp_tests__get',
					'singular' => '',
					'ajax'     => false,
					'screen'   => null,
				),
			),
			'_pagination_args' => array(
				'property_name' => '_pagination_args',
				'expected'      => array(),
			),
			'screen'           => array(
				'property_name' => 'screen',
				'expected'      => WP_Screen::class,
			),
			'_actions'         => array(
				'property_name' => '_actions',
				'expected'      => null,
			),
			'_pagination'      => array(
				'property_name' => '_pagination',
				'expected'      => null,
			),
		);
	}

	/**
	 * Tests that `WP_List_Table::search_box()` works correctly with an `orderby` array with multiple values.
	 *
	 * @ticket 59494
	 *
	 * @covers WP_List_Table::search_box()
	 */
	public function test_search_box_working_with_array_of_orderby_multiple_values() {
		$_REQUEST['s']       = 'search term';
		$_REQUEST['orderby'] = array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		);

		$actual = get_echo( array( $this->list_table, 'search_box' ), array( 'Search Posts', 'post' ) );

		$expected_html1 = '<input type="hidden" name="orderby[menu_order]" value="ASC" />';
		$expected_html2 = '<input type="hidden" name="orderby[title]" value="ASC" />';

		$this->assertStringContainsString( $expected_html1, $actual );
		$this->assertStringContainsString( $expected_html2, $actual );
	}

	/**
	 * Tests that `WP_List_Table::search_box()` works correctly with an `orderby` array with a single value.
	 *
	 * @ticket 59494
	 *
	 * @covers WP_List_Table::search_box()
	 */
	public function test_search_box_working_with_array_of_orderby_single_value() {
		// Test with one 'orderby' element.
		$_REQUEST['s']       = 'search term';
		$_REQUEST['orderby'] = array(
			'title' => 'ASC',
		);

		$actual = get_echo( array( $this->list_table, 'search_box' ), array( 'Search Posts', 'post' ) );

		$expected_html = '<input type="hidden" name="orderby[title]" value="ASC" />';

		$this->assertStringContainsString( $expected_html, $actual );
	}

	/**
	 * Tests that `WP_List_Table::search_box()` works correctly with `orderby` set to a string.
	 *
	 * @ticket 59494
	 *
	 * @covers WP_List_Table::search_box()
	 */
	public function test_search_box_works_with_orderby_string() {
		// Test with one 'orderby' element.
		$_REQUEST['s']       = 'search term';
		$_REQUEST['orderby'] = 'title';

		$actual = get_echo( array( $this->list_table, 'search_box' ), array( 'Search Posts', 'post' ) );

		$expected_html = '<input type="hidden" name="orderby" value="title" />';

		$this->assertStringContainsString( $expected_html, $actual );
	}
}
