<?php

/**
 * @group ms-required
 * @group admin
 * @group network-admin
 *
 * @covers WP_MS_Themes_List_Table
 */
class Tests_Multisite_wpMsThemesListTable extends WP_UnitTestCase {
	protected static $site_ids;

	/**
	 * @var WP_MS_Themes_List_Table
	 */
	public $table = false;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_MS_Themes_List_Table', array( 'screen' => 'ms-themes' ) );
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
	 * @covers WP_MS_Themes_List_Table::get_views
	 */
	public function test_get_views_should_return_views_by_default() {
		global $totals;

		$totals_backup = $totals;
		$totals        = array(
			'all'                  => 21,
			'enabled'              => 1,
			'disabled'             => 2,
			'upgrade'              => 3,
			'broken'               => 4,
			'auto-update-enabled'  => 5,
			'auto-update-disabled' => 6,
		);

		$expected = array(
			'all'                  => '<a href="themes.php?theme_status=all" class="current" aria-current="page">All <span class="count">(21)</span></a>',
			'enabled'              => '<a href="themes.php?theme_status=enabled">Enabled <span class="count">(1)</span></a>',
			'disabled'             => '<a href="themes.php?theme_status=disabled">Disabled <span class="count">(2)</span></a>',
			'upgrade'              => '<a href="themes.php?theme_status=upgrade">Update Available <span class="count">(3)</span></a>',
			'broken'               => '<a href="themes.php?theme_status=broken">Broken <span class="count">(4)</span></a>',
			'auto-update-enabled'  => '<a href="themes.php?theme_status=auto-update-enabled">Auto-updates Enabled <span class="count">(5)</span></a>',
			'auto-update-disabled' => '<a href="themes.php?theme_status=auto-update-disabled">Auto-updates Disabled <span class="count">(6)</span></a>',
		);

		$actual = $this->table->get_views();
		$totals = $totals_backup;

		$this->assertSame( $expected, $actual );
	}
}
