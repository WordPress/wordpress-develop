<?php

require_once __DIR__ . '/Admin_WpMediaListTable_TestCase.php';

/**
 * @group admin
 */
class Tests_Admin_wpMediaListTable extends Admin_WpMediaListTable_TestCase {

	/**
	 * Tests that a call to WP_Media_List_Table::prepare_items() on a site without any scheduled events
	 * does not result in a PHP warning.
	 *
	 * The warning that we should not see:
	 * PHP <= 7.4: `Invalid argument supplied for foreach()`.
	 * PHP 8.0 and higher: `Warning: foreach() argument must be of type array|object, bool given`.
	 *
	 * Note: This does not test the actual functioning of the WP_Media_List_Table::prepare_items() method.
	 * It just and only tests for/against the PHP warning.
	 *
	 * @ticket 53949
	 * @covers WP_Media_List_Table::prepare_items
	 * @group cron
	 */
	public function test_prepare_items_without_cron_option_does_not_throw_warning() {
		global $wp_query;

		// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
		$mock = $this->getMockBuilder( WP_Media_List_Table::class )
			->disableOriginalConstructor()
			->disallowMockingUnknownTypes()
			->setMethods( array( 'set_pagination_args' ) )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'set_pagination_args' );

		$wp_query->query_vars['posts_per_page'] = 10;
		delete_option( 'cron' );

		// Verify that the cause of the error is in place.
		$this->assertIsArray( _get_cron_array(), '_get_cron_array() does not return an array.' );
		$this->assertEmpty( _get_cron_array(), '_get_cron_array() does not return an empty array.' );

		// If this test does not error out due to the PHP warning, we're good.
		$mock->prepare_items();
	}

	/**
	 * Tests that `WP_Media_List_Table::_get_row_actions()` only includes an action
	 * in certain scenarios.
	 *
	 * @ticket 57893
	 *
	 * @covers WP_Media_List_Table::_get_row_actions
	 *
	 * @dataProvider data_get_row_actions_should_include_action
	 *
	 * @param string    $action   The action that should be included.
	 * @param string    $role     The role of the current user.
	 * @param bool|null $trash    Whether the attachment filter is currently 'trash',
	 *                            or `null` to leave as-is.
	 * @param bool|null $detached Whether the attachment filter is currently 'detached',
	 *                            or `null` to leave as-is.
	 */
	public function test_get_row_actions_should_include_action( $action, $role, $trash, $detached ) {
		if ( 'admin' === $role ) {
			wp_set_current_user( self::$admin );
		} elseif ( 'subscriber' === $role ) {
			wp_set_current_user( self::$subscriber );
		}

		if ( null !== $trash ) {
			self::set_is_trash( $trash );
		}

		if ( null !== $detached ) {
			self::set_detached( $detached );
		}

		$_get_row_actions = new ReflectionMethod( self::$list_table, '_get_row_actions' );
		$_get_row_actions->setAccessible( true );
		$actions = $_get_row_actions->invoke( self::$list_table, self::$post, 'att_title' );
		$_get_row_actions->setAccessible( false );

		$this->assertIsArray( $actions, 'An array was not returned.' );
		$this->assertArrayHasKey( $action, $actions, "'$action' was not included in the actions." );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_row_actions_should_include_action() {
		return array(
			'"edit" while not on "trash"'  => array(
				'action'   => 'edit',
				'role'     => 'admin',
				'trash'    => false,
				'detached' => null,
			),
			'"untrash" while on "trash"'   => array(
				'action'   => 'untrash',
				'role'     => 'admin',
				'trash'    => true,
				'detached' => null,
			),
			'"delete" while on "trash"'    => array(
				'action'   => 'delete',
				'role'     => 'admin',
				'trash'    => true,
				'detached' => null,
			),
			'"view" while not on "trash"'  => array(
				'action'   => 'view',
				'role'     => 'admin',
				'trash'    => false,
				'detached' => null,
			),
			'"attach" while on "detached"' => array(
				'action'   => 'attach',
				'role'     => 'admin',
				'trash'    => null,
				'detached' => true,
			),
		);
	}

	/**
	 * Tests that `WP_Media_List_Table::_get_row_actions()` does not include an action
	 * in certain scenarios.
	 *
	 * @ticket 57893
	 *
	 * @covers WP_Media_List_Table::_get_row_actions
	 *
	 * @dataProvider data_get_row_actions_should_not_include_action
	 *
	 * @param string    $action   The action that should not be included.
	 * @param string    $role     The role of the current user.
	 * @param bool|null $trash    Whether the attachment filter is currently 'trash',
	 *                            or `null` to leave as-is.
	 * @param bool|null $detached Whether the attachment filter is currently 'detached',
	 *                            or `null` to leave as-is.
	 */
	public function test_get_row_actions_should_not_include_action( $action, $role, $trash, $detached ) {
		if ( 'admin' === $role ) {
			wp_set_current_user( self::$admin );
		} elseif ( 'subscriber' === $role ) {
			wp_set_current_user( self::$subscriber );
		}

		if ( null !== $trash ) {
			self::set_is_trash( $trash );
		}

		if ( null !== $detached ) {
			self::set_detached( $detached );
		}

		$_get_row_actions = new ReflectionMethod( self::$list_table, '_get_row_actions' );
		$_get_row_actions->setAccessible( true );
		$actions = $_get_row_actions->invoke( self::$list_table, self::$post, 'att_title' );
		$_get_row_actions->setAccessible( false );

		$this->assertIsArray( $actions, 'An array was not returned.' );
		$this->assertArrayNotHasKey( $action, $actions, "'$action' was included in the actions." );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_row_actions_should_not_include_action() {
		return array(
			'"edit" while on "trash"'               => array(
				'action'   => 'edit',
				'role'     => 'admin',
				'trash'    => true,
				'detached' => null,
			),
			'"edit" with incorrect capabilities'    => array(
				'action'   => 'edit',
				'role'     => 'subscriber',
				'trash'    => false,
				'detached' => null,
			),
			'"untrash" while not on "trash"'        => array(
				'action'   => 'untrash',
				'role'     => 'administrator',
				'trash'    => false,
				'detached' => null,
			),
			'"untrash" with incorrect capabilities' => array(
				'action'   => 'untrash',
				'role'     => 'subscriber',
				'trash'    => true,
				'detached' => null,
			),
			'"trash" while not on "trash"'          => array(
				'action'   => 'trash',
				'role'     => 'administrator',
				'trash'    => false,
				'detached' => null,
			),
			'"trash" with incorrect capabilities'   => array(
				'action'   => 'trash',
				'role'     => 'subscriber',
				'trash'    => true,
				'detached' => null,
			),
			'"view" while on "trash"'               => array(
				'action'   => 'view',
				'role'     => 'administrator',
				'trash'    => true,
				'detached' => null,
			),
			'"attach" with incorrect capabilities'  => array(
				'action'   => 'attach',
				'role'     => 'subscriber',
				'trash'    => null,
				'detached' => true,
			),
			'"attach" when not on "detached"'       => array(
				'action'   => 'attach',
				'role'     => 'administrator',
				'trash'    => null,
				'detached' => false,
			),
			'"copy" when on "trash"'                => array(
				'action'   => 'copy',
				'role'     => 'administrator',
				'trash'    => true,
				'detached' => null,
			),
		);
	}

	/**
	 * Tests that `WP_Media_List_Table::_get_row_actions()` does not include the 'view' action
	 * when a permalink is not available.
	 *
	 * @ticket 57893
	 *
	 * @covers WP_Media_List_Table::_get_row_actions
	 */
	public function test_get_row_actions_should_not_include_view_without_a_permalink() {
		self::set_is_trash( false );

		// Ensure the permalink is `false`.
		add_filter( 'post_link', '__return_false', 10, 0 );

		$_get_row_actions = new ReflectionMethod( self::$list_table, '_get_row_actions' );
		$_get_row_actions->setAccessible( true );
		$actions = $_get_row_actions->invoke( self::$list_table, self::$post, 'att_title' );
		$_get_row_actions->setAccessible( false );

		$this->assertIsArray( $actions, 'An array was not returned.' );
		$this->assertArrayNotHasKey( 'view', $actions, '"view" was included in the actions.' );
	}

	/**
	 * Tests that `WP_Media_List_Table::_get_row_actions()` includes the 'copy' action.
	 *
	 * @ticket 57893
	 *
	 * @covers WP_Media_List_Table::_get_row_actions
	 */
	public function test_get_row_actions_should_include_copy() {
		self::set_is_trash( false );

		$_get_row_actions = new ReflectionMethod( self::$list_table, '_get_row_actions' );
		$_get_row_actions->setAccessible( true );
		$actions = $_get_row_actions->invoke( self::$list_table, self::$attachment, 'att_title' );
		$_get_row_actions->setAccessible( false );

		$this->assertIsArray( $actions, 'An array was not returned.' );
		$this->assertArrayHasKey( 'copy', $actions, '"copy" was not included in the actions.' );
	}

	/**
	 * Tests that `WP_Media_List_Table::_get_row_actions()` does not include the 'copy' action
	 * when an attachment URL is not available.
	 *
	 * @ticket 57893
	 *
	 * @covers WP_Media_List_Table::_get_row_actions
	 */
	public function test_get_row_actions_should_not_include_copy_without_an_attachment_url() {
		self::set_is_trash( false );

		// Ensure the attachment URL is `false`.
		add_filter( 'wp_get_attachment_url', '__return_false', 10, 0 );

		$_get_row_actions = new ReflectionMethod( self::$list_table, '_get_row_actions' );
		$_get_row_actions->setAccessible( true );
		$actions = $_get_row_actions->invoke( self::$list_table, self::$attachment, 'att_title' );
		$_get_row_actions->setAccessible( false );

		$this->assertIsArray( $actions, 'An array was not returned.' );
		$this->assertArrayNotHasKey( 'copy', $actions, '"copy" was included in the actions.' );
	}

	/**
	 * Tests that `WP_Media_List_Table::_get_row_actions()` includes the 'download' action.
	 *
	 * @ticket 57893
	 *
	 * @covers WP_Media_List_Table::_get_row_actions
	 */
	public function test_get_row_actions_should_include_download() {
		$_get_row_actions = new ReflectionMethod( self::$list_table, '_get_row_actions' );
		$_get_row_actions->setAccessible( true );
		$actions = $_get_row_actions->invoke( self::$list_table, self::$attachment, 'att_title' );
		$_get_row_actions->setAccessible( false );

		$this->assertIsArray( $actions, 'An array was not returned.' );
		$this->assertArrayHasKey( 'download', $actions, '"download" was not included in the actions.' );
	}

	/**
	 * Tests that `WP_Media_List_Table::_get_row_actions()` does not include the 'download' action
	 * when an attachment URL is not available.
	 *
	 * @ticket 57893
	 *
	 * @covers WP_Media_List_Table::_get_row_actions
	 */
	public function test_get_row_actions_should_not_include_download_without_an_attachment_url() {
		// Ensure the attachment URL is `false`.
		add_filter( 'wp_get_attachment_url', '__return_false', 10, 0 );

		$_get_row_actions = new ReflectionMethod( self::$list_table, '_get_row_actions' );
		$_get_row_actions->setAccessible( true );
		$actions = $_get_row_actions->invoke( self::$list_table, self::$attachment, 'att_title' );
		$_get_row_actions->setAccessible( false );

		$this->assertIsArray( $actions, 'An array was not returned.' );
		$this->assertArrayNotHasKey( 'download', $actions, '"download" was included in the actions.' );
	}
}
