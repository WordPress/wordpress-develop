<?php

/**
 * @group admin
 * @group ms-required
 * @group network-admin
 */
class Tests_Multisite_wpMsSitesListTable extends WP_UnitTestCase {

	protected static $site_ids;

	/**
	 * @var WP_MS_Sites_List_Table
	 */
	public $table = false;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_MS_Sites_List_Table', array( 'screen' => 'ms-sites' ) );
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

	public function test_ms_sites_list_table_default_items() {
		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		$this->assertSameSets( array( 1 ) + self::$site_ids, $items );
	}

	/**
	 * @ticket 33832
	 */
	public function test_ms_sites_list_table_admin_email_column_does_not_load_when_hidden() {
		$hide_admin_email = static function ( $hidden ) {
			$hidden[] = 'site_admin_email';
			return $hidden;
		};
		add_filter( 'hidden_columns', $hide_admin_email );

		$table                 = _get_list_table( 'WP_MS_Sites_List_Table', array( 'screen' => 'ms-sites' ) );
		$admin_email_requested = false;
		$track_option_request  = static function ( $value ) use ( &$admin_email_requested ) {
			$admin_email_requested = true;
			return $value;
		};
		add_filter( 'pre_option_admin_email', $track_option_request );

		ob_start();
		$table->column_site_admin_email( array( 'blog_id' => self::$site_ids['wordpress.org/foo/'] ) );
		$output = ob_get_clean();

		remove_filter( 'hidden_columns', $hide_admin_email );
		remove_filter( 'pre_option_admin_email', $track_option_request );

		$this->assertSame( '', $output );
		$this->assertFalse( $admin_email_requested );
	}

	/**
	 * @ticket 33832
	 */
	public function test_ms_sites_list_table_admin_email_column_is_opt_in_for_existing_preferences() {
		$user_id       = self::factory()->user->create();
		$screen_id     = 'ms-sites-admin-email-preferences';
		$hidden_option = 'manage' . $screen_id . 'columnshidden';

		wp_set_current_user( $user_id );
		update_user_meta( $user_id, $hidden_option, array( 'registered' ) );

		$table  = _get_list_table( 'WP_MS_Sites_List_Table', array( 'screen' => $screen_id ) );
		$hidden = get_hidden_columns( $table->screen );

		$this->assertSame( array( 'registered', 'site_admin_email' ), $hidden );
		$this->assertSame( $hidden, get_user_meta( $user_id, $hidden_option, true ) );

		// Enabling the column through Screen Options must persist.
		update_user_meta( $user_id, $hidden_option, array( 'registered' ) );
		$table = _get_list_table( 'WP_MS_Sites_List_Table', array( 'screen' => $screen_id ) );

		$this->assertSame( array( 'registered' ), get_hidden_columns( $table->screen ) );

		wp_set_current_user( 0 );
	}

	/**
	 * @ticket 33832
	 */
	public function test_ms_sites_list_table_admin_email_column_output() {
		$site_id     = self::$site_ids['wordpress.org/foo/'];
		$admin_email = 'site-admin@example.org';

		update_blog_option( $site_id, 'admin_email', $admin_email );

		$this->assertArrayHasKey( 'site_admin_email', $this->table->get_columns() );

		ob_start();
		$this->table->column_site_admin_email( array( 'blog_id' => $site_id ) );
		$output = ob_get_clean();

		$this->assertSame( $admin_email, $output );
	}

	/**
	 * @ticket 33832
	 */
	public function test_existing_admin_email_custom_column_uses_custom_column_hook() {
		$add_admin_email_column    = static function ( $columns ) {
			$columns['admin_email'] = 'Custom Admin Email';
			return $columns;
		};
		$custom_column_rendered    = false;
		$render_admin_email_column = static function ( $column_name ) use ( &$custom_column_rendered ) {
			if ( 'admin_email' === $column_name ) {
				$custom_column_rendered = true;
			}
		};

		add_filter( 'wpmu_blogs_columns', $add_admin_email_column );
		add_action( 'manage_sites_custom_column', $render_admin_email_column );

		$table        = _get_list_table( 'WP_MS_Sites_List_Table', array( 'screen' => 'ms-sites-admin-email-compat' ) );
		$table->items = array( get_site( self::$site_ids['wordpress.org/foo/'] ) );

		ob_start();
		$table->display_rows();
		ob_end_clean();

		remove_filter( 'wpmu_blogs_columns', $add_admin_email_column );
		remove_action( 'manage_sites_custom_column', $render_admin_email_column );

		$this->assertTrue( $custom_column_rendered );
	}

	public function test_ms_sites_list_table_subdirectory_path_search_items() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'Path search is not available for subdomain configurations.' );
		}

		$_REQUEST['s'] = 'foo';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['wordpress.org/foo/'],
			self::$site_ids['wordpress.org/foo/bar/'],
			self::$site_ids['wordpress.org/afoo/'],
			self::$site_ids['make.wordpress.org/foo/'],
			self::$site_ids['www.w.org/foo/'],
			self::$site_ids['www.w.org/foo/bar/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_subdirectory_multiple_path_search_items() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'Path search is not available for subdomain configurations.' );
		}

		$_REQUEST['s'] = 'foo/bar';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['wordpress.org/foo/bar/'],
			self::$site_ids['www.w.org/foo/bar/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_invalid_path_search_items() {
		$_REQUEST['s'] = 'foobar';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$this->assertEmpty( $items );
	}

	public function test_ms_sites_list_table_subdomain_domain_search_items() {
		if ( ! is_subdomain_install() ) {
			$this->markTestSkipped( 'Domain search is not available for subdirectory configurations.' );
		}

		$_REQUEST['s'] = 'test';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['test.example.org/'],
			self::$site_ids['test2.example.org/'],
			self::$site_ids['test3.example.org/zig/'],
			self::$site_ids['atest.example.org/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_subdomain_domain_search_items_with_trailing_wildcard() {
		if ( ! is_subdomain_install() ) {
			$this->markTestSkipped( 'Domain search is not available for subdirectory configurations.' );
		}

		$_REQUEST['s'] = 'test*';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['test.example.org/'],
			self::$site_ids['test2.example.org/'],
			self::$site_ids['test3.example.org/zig/'],
			self::$site_ids['atest.example.org/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_subdirectory_path_search_items_with_trailing_wildcard() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'Path search is not available for subdomain configurations.' );
		}

		$_REQUEST['s'] = 'fo*';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['wordpress.org/foo/'],
			self::$site_ids['wordpress.org/foo/bar/'],
			self::$site_ids['wordpress.org/afoo/'],
			self::$site_ids['make.wordpress.org/foo/'],
			self::$site_ids['www.w.org/foo/'],
			self::$site_ids['www.w.org/foo/bar/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	/**
	 * @ticket 42066
	 */
	public function test_get_views_should_return_views_by_default() {
		$expected = array(
			'all'    => '<a href="sites.php" class="current" aria-current="page">All <span class="count">(14)</span></a>',
			'public' => '<a href="sites.php?status=public">Public <span class="count">(14)</span></a>',
		);

		$this->assertSame( $expected, $this->table->get_views() );
	}
}
