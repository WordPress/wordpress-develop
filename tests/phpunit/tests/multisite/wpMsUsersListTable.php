<?php

/**
 * @group admin
 * @group ms-required
 * @group network-admin
 *
 * @covers WP_MS_Users_List_Table
 */
class Tests_Multisite_wpMsUsersListTable extends WP_UnitTestCase {
	protected static $site_ids;

	/**
	 * @var WP_MS_Users_List_Table
	 */
	public $table = false;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_MS_Users_List_Table', array( 'screen' => 'ms-users' ) );
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$site_ids = array(
			'wordpress.org/'          => array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			),
			'wordpress.org/foo/'      => array(
				'domain' => 'wordpress.org',
				'path'   => '/foo/',
			),
			'wordpress.org/foo/bar/'  => array(
				'domain' => 'wordpress.org',
				'path'   => '/foo/bar/',
			),
			'wordpress.org/afoo/'     => array(
				'domain' => 'wordpress.org',
				'path'   => '/afoo/',
			),
			'make.wordpress.org/'     => array(
				'domain' => 'make.wordpress.org',
				'path'   => '/',
			),
			'make.wordpress.org/foo/' => array(
				'domain' => 'make.wordpress.org',
				'path'   => '/foo/',
			),
			'www.w.org/'              => array(
				'domain' => 'www.w.org',
				'path'   => '/',
			),
			'www.w.org/foo/'          => array(
				'domain' => 'www.w.org',
				'path'   => '/foo/',
			),
			'www.w.org/foo/bar/'      => array(
				'domain' => 'www.w.org',
				'path'   => '/foo/bar/',
			),
			'test.example.org/'       => array(
				'domain' => 'test.example.org',
				'path'   => '/',
			),
			'test2.example.org/'      => array(
				'domain' => 'test2.example.org',
				'path'   => '/',
			),
			'test3.example.org/zig/'  => array(
				'domain' => 'test3.example.org',
				'path'   => '/zig/',
			),
			'atest.example.org/'      => array(
				'domain' => 'atest.example.org',
				'path'   => '/',
			),
		);

		foreach ( self::$site_ids as &$id ) {
			$id = $factory->blog->create( $id );
		}
		unset( $id );
	}

	public static function wpTearDownAfterClass() {
		foreach ( self::$site_ids as $site_id ) {
			wp_delete_site( $site_id );
		}
	}

	/**
	 * @ticket 42066
	 *
	 * @covers WP_MS_Users_List_Table::get_views
	 */
	public function test_get_views_should_return_views_by_default() {
		$all   = get_user_count();
		$super = count( get_super_admins() );

		$expected = array(
			'all'   => '<a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/network/users.php" class="current" aria-current="page">All <span class="count">(' . $all . ')</span></a>',
			'super' => '<a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/network/users.php?role=super">Super Admin <span class="count">(' . $super . ')</span></a>',
		);

		$this->assertSame( $expected, $this->table->get_views() );
	}

	/**
	 * @ticket 43899
	 */
	public function test_view_switcher_is_not_displayed() {
		$pagination = new ReflectionMethod( $this->table, 'pagination' );
		if ( PHP_VERSION_ID < 80100 ) {
			$pagination->setAccessible( true );
		}

		ob_start();
		$pagination->invoke( $this->table, 'top' );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'view-switch', $output );
	}

	/**
	 * @ticket 43899
	 */
	public function test_excerpt_mode_request_does_not_change_registered_date_format() {
		$_REQUEST['mode'] = 'excerpt';
		$this->table->prepare_items();
		unset( $_REQUEST['mode'] );

		$user                  = new WP_User();
		$user->user_registered = '2025-01-02 03:04:05';

		ob_start();
		$this->table->column_registered( $user );
		$output = ob_get_clean();

		$this->assertSame( mysql2date( __( 'Y/m/d g:i:s a' ), $user->user_registered ), $output );
	}
}
