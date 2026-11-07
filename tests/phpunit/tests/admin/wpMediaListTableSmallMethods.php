<?php

/**
 * Tests for the columns, the actions and the messages of WP_Media_List_Table.
 *
 * @group admin
 * @group media
 */
class Tests_Admin_wpMediaListTableSmallMethods extends WP_UnitTestCase {

	/**
	 * ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original $_REQUEST.
	 *
	 * @var array
	 */
	private $original_request;

	/**
	 * The original global query.
	 *
	 * @var WP_Query|null
	 */
	private $original_wp_query;

	/**
	 * Creates the administrator.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );

		$this->original_request  = $_REQUEST;
		$this->original_wp_query = $GLOBALS['wp_query'] ?? null;

		$_REQUEST = array();
	}

	public function tear_down() {
		$_REQUEST            = $this->original_request;
		$GLOBALS['wp_query'] = $this->original_wp_query;

		unregister_taxonomy( 'media_group' );
		unregister_taxonomy_for_object_type( 'category', 'attachment' );
		unregister_taxonomy_for_object_type( 'post_tag', 'attachment' );

		parent::tear_down();
	}

	/**
	 * Creates the list table.
	 *
	 * @return WP_Media_List_Table The table.
	 */
	private function get_table() {
		return _get_list_table( 'WP_Media_List_Table', array( 'screen' => 'upload' ) );
	}

	/**
	 * Sets a private property of the table.
	 *
	 * @param WP_Media_List_Table $table The table.
	 * @param string              $name  The name of the property.
	 * @param mixed               $value The value.
	 */
	private function set_property( $table, $name, $value ) {
		$property = new ReflectionProperty( WP_Media_List_Table::class, $name );

		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$property->setValue( $table, $value );
	}

	/**
	 * Gets a private property of the table.
	 *
	 * @param WP_Media_List_Table $table The table.
	 * @param string              $name  The name of the property.
	 * @return mixed The value.
	 */
	private function get_property( $table, $name ) {
		$property = new ReflectionProperty( WP_Media_List_Table::class, $name );

		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		return $property->getValue( $table );
	}

	/**
	 * Calls a protected method of the table.
	 *
	 * @param WP_Media_List_Table $table The table.
	 * @param string              $name  The name of the method.
	 * @param array               $args  Optional. The arguments. Default none.
	 * @return mixed The result.
	 */
	private function call_method( $table, $name, $args = array() ) {
		$method = new ReflectionMethod( WP_Media_List_Table::class, $name );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invokeArgs( $table, $args );
	}

	/**
	 * Tests the filter of the unattached files in the constructor.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::__construct
	 */
	public function test_constructor_sets_the_unattached_filter() {
		$this->assertFalse( $this->get_property( $this->get_table(), 'detached' ) );

		$_REQUEST['attachment-filter'] = 'detached';

		$this->assertTrue( $this->get_property( $this->get_table(), 'detached' ) );

		$_REQUEST['attachment-filter'] = 'trash';

		$this->assertFalse( $this->get_property( $this->get_table(), 'detached' ) );
	}

	/**
	 * Tests the arguments of the table.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::__construct
	 */
	public function test_constructor_sets_the_arguments() {
		$table = $this->get_table();

		$this->assertSame( 'media', $table->_args['plural'] );
		$this->assertSame( 'upload', $table->screen->id );
	}

	/**
	 * Tests who can use the table in AJAX requests.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::ajax_user_can
	 */
	public function test_ajax_user_can() {
		$this->assertTrue( $this->get_table()->ajax_user_can() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse( $this->get_table()->ajax_user_can() );
	}

	/**
	 * Tests the primary column.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_default_primary_column_name
	 */
	public function test_default_primary_column_is_the_title() {
		$this->assertSame( 'title', $this->call_method( $this->get_table(), 'get_default_primary_column_name' ) );
	}

	/**
	 * Tests the columns of the table.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_columns
	 */
	public function test_get_columns() {
		$columns = $this->get_table()->get_columns();

		$this->assertSame( array( 'cb', 'title', 'author', 'parent', 'date' ), array_keys( $columns ) );
		$this->assertSame( '<input type="checkbox" />', $columns['cb'] );
		$this->assertSame( 'File', $columns['title'] );
		$this->assertSame( 'Author', $columns['author'] );
		$this->assertSame( 'Uploaded to', $columns['parent'] );
		$this->assertSame( 'Date', $columns['date'] );
	}

	/**
	 * Tests the columns of the unattached files.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_columns
	 */
	public function test_get_columns_of_unattached_files() {
		$_REQUEST['attachment-filter'] = 'detached';

		$this->assertSame( array( 'cb', 'title', 'author', 'date' ), array_keys( $this->get_table()->get_columns() ) );
	}

	/**
	 * Tests the column of the comments.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_columns
	 */
	public function test_get_columns_has_the_comments_when_the_attachment_pages_support_them() {
		add_post_type_support( 'attachment', 'comments' );
		update_option( 'wp_attachment_pages_enabled', 1 );

		$columns = $this->get_table()->get_columns();

		$this->assertSame( array( 'cb', 'title', 'author', 'parent', 'comments', 'date' ), array_keys( $columns ) );
		$this->assertStringContainsString( 'comment-grey-bubble', $columns['comments'] );
		$this->assertStringContainsString( '<span class="screen-reader-text">Comments</span>', $columns['comments'] );
		$this->assertStringContainsString( 'title="Comments"', $columns['comments'] );

		update_option( 'wp_attachment_pages_enabled', 0 );

		$this->assertArrayNotHasKey( 'comments', $this->get_table()->get_columns() );

		remove_post_type_support( 'attachment', 'comments' );
		update_option( 'wp_attachment_pages_enabled', 1 );

		$this->assertArrayNotHasKey( 'comments', $this->get_table()->get_columns() );
	}

	/**
	 * Tests that the unattached files have no comments column.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_columns
	 */
	public function test_get_columns_of_unattached_files_has_no_comments() {
		add_post_type_support( 'attachment', 'comments' );
		update_option( 'wp_attachment_pages_enabled', 1 );

		$_REQUEST['attachment-filter'] = 'detached';

		$this->assertArrayNotHasKey( 'comments', $this->get_table()->get_columns() );
	}

	/**
	 * Tests the columns of the taxonomies.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_columns
	 */
	public function test_get_columns_has_the_taxonomies_that_have_an_admin_column() {
		register_taxonomy_for_object_type( 'category', 'attachment' );
		register_taxonomy_for_object_type( 'post_tag', 'attachment' );
		register_taxonomy(
			'media_group',
			'attachment',
			array(
				'label'             => 'Groups',
				'show_admin_column' => true,
			)
		);

		$columns = $this->get_table()->get_columns();

		$this->assertSame( 'Categories', $columns['categories'] );
		$this->assertSame( 'Tags', $columns['tags'] );
		$this->assertSame( 'Groups', $columns['taxonomy-media_group'] );
		$this->assertSame( array( 'cb', 'title', 'author', 'categories', 'tags', 'taxonomy-media_group', 'parent', 'date' ), array_keys( $columns ) );
	}

	/**
	 * Tests that a taxonomy without an admin column is left out.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_columns
	 */
	public function test_get_columns_leaves_out_a_taxonomy_without_an_admin_column() {
		register_taxonomy( 'media_group', 'attachment', array( 'label' => 'Groups' ) );

		$this->assertArrayNotHasKey( 'taxonomy-media_group', $this->get_table()->get_columns() );
	}

	/**
	 * Tests the filter of the taxonomies of the columns.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_columns
	 */
	public function test_get_columns_filters_the_taxonomies() {
		$received = null;

		register_taxonomy( 'media_group', 'attachment', array( 'label' => 'Groups' ) );

		add_filter(
			'manage_taxonomies_for_attachment_columns',
			static function ( $taxonomies, $post_type ) use ( &$received ) {
				$received = array( $taxonomies, $post_type );

				return array( 'media_group', 'missing_taxonomy' );
			},
			10,
			2
		);

		$columns = $this->get_table()->get_columns();

		$this->assertSame( array( array(), 'attachment' ), $received );
		$this->assertArrayHasKey( 'taxonomy-media_group', $columns );
		$this->assertSame( array( 'cb', 'title', 'author', 'taxonomy-media_group', 'parent', 'date' ), array_keys( $columns ), 'A taxonomy that does not exist should be left out.' );
	}

	/**
	 * Tests the filter of the columns.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_columns
	 */
	public function test_get_columns_is_filtered_with_the_unattached_flag() {
		$received = array();

		add_filter(
			'manage_media_columns',
			static function ( $columns, $detached ) use ( &$received ) {
				$received[] = $detached;

				$columns['extra'] = 'Extra';

				return $columns;
			},
			10,
			2
		);

		$this->assertArrayHasKey( 'extra', $this->get_table()->get_columns() );

		$_REQUEST['attachment-filter'] = 'detached';

		$this->get_table()->get_columns();

		$this->assertSame( array( false, true ), $received );
	}

	/**
	 * Tests the sortable columns.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_sortable_columns
	 */
	public function test_get_sortable_columns() {
		$columns = $this->call_method( $this->get_table(), 'get_sortable_columns' );

		$this->assertSame( array( 'title', 'author', 'parent', 'comments', 'date' ), array_keys( $columns ) );
		$this->assertSame( 'title', $columns['title'][0] );
		$this->assertSame( 'author', $columns['author'][0] );
		$this->assertSame( 'parent', $columns['parent'][0] );
		$this->assertSame( 'comment_count', $columns['comments'][0] );
		$this->assertSame( 'date', $columns['date'][0] );
		$this->assertTrue( $columns['date'][1], 'The date is ordered descending first.' );
		$this->assertSame( 'desc', $columns['date'][4] );
		$this->assertFalse( $columns['title'][1] );
	}

	/**
	 * Tests the bulk actions of the media that are not in the trash.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_bulk_actions
	 */
	public function test_get_bulk_actions() {
		$actions = $this->call_method( $this->get_table(), 'get_bulk_actions' );

		if ( MEDIA_TRASH ) {
			$this->assertSame( array( 'trash' => 'Move to Trash' ), $actions );
		} else {
			$this->assertSame( array( 'delete' => 'Delete permanently' ), $actions );
		}
	}

	/**
	 * Tests the bulk actions of the media in the trash.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_bulk_actions
	 */
	public function test_get_bulk_actions_of_the_trash() {
		$table = $this->get_table();

		$this->set_property( $table, 'is_trash', true );

		$actions = $this->call_method( $table, 'get_bulk_actions' );

		if ( MEDIA_TRASH ) {
			$this->assertSame(
				array(
					'untrash' => 'Restore',
					'delete'  => 'Delete permanently',
				),
				$actions
			);
		} else {
			$this->assertSame( array( 'delete' => 'Delete permanently' ), $actions );
		}
	}

	/**
	 * Tests the bulk action to attach the unattached files.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::get_bulk_actions
	 */
	public function test_get_bulk_actions_of_unattached_files_has_attach() {
		$_REQUEST['attachment-filter'] = 'detached';

		$actions = $this->call_method( $this->get_table(), 'get_bulk_actions' );

		$this->assertSame( 'Attach', end( $actions ) );
		$this->assertSame( 'attach', array_key_last( $actions ) );
		$_REQUEST = array();

		$this->assertArrayNotHasKey( 'attach', $this->call_method( $this->get_table(), 'get_bulk_actions' ) );
	}

	/**
	 * Tests the current action.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::current_action
	 *
	 * @dataProvider data_current_actions
	 *
	 * @param array       $request  The request.
	 * @param string|bool $expected The expected action.
	 */
	public function test_current_action( $request, $expected ) {
		$_REQUEST = $request;

		$this->assertSame( $expected, $this->get_table()->current_action() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_current_actions() {
		return array(
			'attach'                      => array(
				array(
					'found_post_id' => 5,
					'media'         => array( 1 ),
				),
				'attach',
			),
			'attach needs the media'      => array( array( 'found_post_id' => 5 ), false ),
			'detach'                      => array(
				array(
					'parent_post_id' => 5,
					'media'          => array( 1 ),
				),
				'detach',
			),
			'detach needs the media'      => array( array( 'parent_post_id' => 5 ), false ),
			'attach wins over detach'     => array(
				array(
					'found_post_id'  => 5,
					'parent_post_id' => 6,
					'media'          => array( 1 ),
				),
				'attach',
			),
			'delete all'                  => array( array( 'delete_all' => '1' ), 'delete_all' ),
			'delete all at the bottom'    => array( array( 'delete_all2' => '1' ), 'delete_all' ),
			'attach wins over delete all' => array(
				array(
					'found_post_id' => 5,
					'media'         => array( 1 ),
					'delete_all'    => '1',
				),
				'attach',
			),
			'bulk action'                 => array( array( 'action' => 'delete' ), 'delete' ),
			'no action'                   => array( array(), false ),
		);
	}

	/**
	 * Tests the check for items.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::has_items
	 */
	public function test_has_items() {
		$table = $this->get_table();

		$GLOBALS['wp_query'] = new WP_Query(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post__in'    => array( 0 ),
			)
		);

		$this->assertFalse( $table->has_items() );

		self::factory()->attachment->create_object(
			array(
				'file'           => 'file.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$GLOBALS['wp_query'] = new WP_Query(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
			)
		);

		$this->assertTrue( $table->has_items() );
	}

	/**
	 * Tests the message when there are no files.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::no_items
	 */
	public function test_no_items() {
		$table = $this->get_table();

		ob_start();
		$table->no_items();

		$this->assertSame( 'No media files found.', ob_get_clean() );

		$this->set_property( $table, 'is_trash', true );

		ob_start();
		$table->no_items();

		$this->assertSame( 'No media files found in Trash.', ob_get_clean() );
	}

	/**
	 * Tests that the tablenav only has something at the bar.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::extra_tablenav
	 *
	 * @dataProvider data_other_tablenav_positions
	 *
	 * @param string $which The position.
	 */
	public function test_extra_tablenav_is_empty_except_at_the_bar( $which ) {
		ob_start();
		$this->call_method( $this->get_table(), 'extra_tablenav', array( $which ) );

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_other_tablenav_positions() {
		return array(
			'top'    => array( 'top' ),
			'bottom' => array( 'bottom' ),
		);
	}

	/**
	 * Tests the tablenav of the bar.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::extra_tablenav
	 */
	public function test_extra_tablenav_at_the_bar() {
		self::factory()->attachment->create_object(
			array(
				'file'           => 'file.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_date'      => '2020-03-15 10:00:00',
			)
		);

		$received = null;

		add_action(
			'restrict_manage_posts',
			static function ( $post_type, $which ) use ( &$received ) {
				$received = array( $post_type, $which );
			},
			10,
			2
		);

		$GLOBALS['wp_query'] = new WP_Query(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
			)
		);

		ob_start();
		$this->call_method( $this->get_table(), 'extra_tablenav', array( 'bar' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="actions">', $output );
		$this->assertStringContainsString( 'name="m"', $output, 'The months dropdown should be shown.' );
		$this->assertStringContainsString( 'id="post-query-submit"', $output );
		$this->assertStringContainsString( 'value="Filter"', $output );
		$this->assertStringNotContainsString( 'delete_all', $output );
		$this->assertSame( 'bar', $received[1] );
	}

	/**
	 * Tests the tablenav of the bar for the media in the trash.
	 *
	 * @ticket 66603
	 *
	 * @covers WP_Media_List_Table::extra_tablenav
	 */
	public function test_extra_tablenav_of_the_trash_has_the_empty_trash_button_when_there_are_items() {
		$table = $this->get_table();

		self::factory()->attachment->create_object(
			array(
				'file'           => 'kept.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_date'      => '2020-03-15 10:00:00',
			)
		);

		$this->set_property( $table, 'is_trash', true );

		$GLOBALS['wp_query'] = new WP_Query(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'trash',
				'post__in'    => array( 0 ),
			)
		);

		ob_start();
		$this->call_method( $table, 'extra_tablenav', array( 'bar' ) );
		$without_items = ob_get_clean();

		$this->assertStringNotContainsString( 'name="m"', $without_items, 'The months dropdown should be left out in the trash.' );
		$this->assertStringNotContainsString( 'delete_all', $without_items );

		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'file.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'trash',
			)
		);

		$GLOBALS['wp_query'] = new WP_Query(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'trash',
			)
		);

		ob_start();
		$this->call_method( $table, 'extra_tablenav', array( 'bar' ) );
		$with_items = ob_get_clean();

		$this->assertGreaterThan( 0, $attachment_id );
		$this->assertStringContainsString( 'name="delete_all"', $with_items );
		$this->assertStringContainsString( 'value="Empty Trash"', $with_items );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );

		ob_start();
		$this->call_method( $table, 'extra_tablenav', array( 'bar' ) );

		$this->assertStringNotContainsString( 'delete_all', ob_get_clean(), 'A user who cannot edit others posts should not see the button.' );
	}
}
