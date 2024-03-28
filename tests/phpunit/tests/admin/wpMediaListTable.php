<?php

/**
 * @group admin
 */
class Tests_Admin_wpMediaListTable extends WP_UnitTestCase {
	/**
	 * A list table for testing.
	 *
	 * @var WP_Media_List_Table
	 */
	protected static $list_table;

	/**
	 * A reflection of the `$is_trash` property.
	 *
	 * @var ReflectionProperty
	 */
	protected static $is_trash;

	/**
	 * The original value of the `$is_trash` property.
	 *
	 * @var bool|null
	 */
	protected static $is_trash_original;

	/**
	 * A reflection of the `$detached` property.
	 *
	 * @var ReflectionProperty
	 */
	protected static $detached;

	/**
	 * The original value of the `$detached` property.
	 *
	 * @var bool|null
	 */
	protected static $detached_original;

	/**
	 * The ID of an 'administrator' user for testing.
	 *
	 * @var int
	 */
	protected static $admin;

	/**
	 * The ID of a 'subscriber' user for testing.
	 *
	 * @var int
	 */
	protected static $subscriber;

	/**
	 * A post for testing.
	 *
	 * @var WP_Post
	 */
	protected static $post;

	/**
	 * An attachment for testing.
	 *
	 * @var WP_Post
	 */
	protected static $attachment;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$list_table = new WP_Media_List_Table();
		self::$is_trash   = new ReflectionProperty( self::$list_table, 'is_trash' );
		self::$detached   = new ReflectionProperty( self::$list_table, 'detached' );

		self::$is_trash->setAccessible( true );
		self::$is_trash_original = self::$is_trash->getValue( self::$list_table );
		self::$is_trash->setAccessible( false );

		self::$detached->setAccessible( true );
		self::$detached_original = self::$detached->getValue( self::$list_table );
		self::$detached->setAccessible( false );

		// Create users.
		self::$admin      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Create posts.
		self::$post       = self::factory()->post->create_and_get();
		self::$attachment = self::factory()->attachment->create_and_get(
			array(
				'post_name'      => 'attachment-name',
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	/**
	 * Restores reflections to their original values.
	 */
	public function tear_down() {
		self::set_is_trash( self::$is_trash_original );
		self::set_detached( self::$detached_original );

		parent::tear_down();
	}

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

	/**
	 * Sets the `$is_trash` property.
	 *
	 * Helper method.
	 *
	 * @param bool $is_trash Whether the attachment filter is currently 'trash'.
	 */
	private static function set_is_trash( $is_trash ) {
		self::$is_trash->setAccessible( true );
		self::$is_trash->setValue( self::$list_table, $is_trash );
		self::$is_trash->setAccessible( false );
	}

	/**
	 * Sets the `$detached` property.
	 *
	 * Helper method.
	 *
	 * @param bool $detached Whether the attachment filter is currently 'detached'.
	 */
	private static function set_detached( $detached ) {
		self::$detached->setAccessible( true );
		self::$detached->setValue( self::$list_table, $detached );
		self::$detached->setAccessible( false );
	}
}
