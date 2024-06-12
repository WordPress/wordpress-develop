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
	protected static $list_table;

	public static function set_up_before_class() {
		global $hook_suffix;

		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

		$hook_suffix      = '_wp_tests';
		self::$list_table = new WP_List_Table();
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
	 * Tests the "get_views_links()" method.
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
		$get_views_links = new ReflectionMethod( self::$list_table, 'get_views_links' );
		$get_views_links->setAccessible( true );

		$actual = $get_views_links->invokeArgs( self::$list_table, array( $link_data ) );

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
	 * Tests that "get_views_links()" throws a _doing_it_wrong().
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
		$get_views_links = new ReflectionMethod( self::$list_table, 'get_views_links' );
		$get_views_links->setAccessible( true );
		$get_views_links->invokeArgs( self::$list_table, array( $link_data ) );
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
}
