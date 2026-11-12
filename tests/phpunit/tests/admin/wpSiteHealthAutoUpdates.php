<?php

/**
 * Tests for WP_Site_Health_Auto_Updates.
 *
 * @group admin
 * @group site-health
 * @group upgrade
 *
 * @covers WP_Site_Health_Auto_Updates
 */
class Tests_Admin_wpSiteHealthAutoUpdates extends WP_UnitTestCase {

	/**
	 * Loads the classes under test.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health-auto-updates.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php';
	}

	/**
	 * Tests that the VCS test reports a `.git` file in ABSPATH, which is what
	 * a Git worktree or submodule has instead of a directory.
	 *
	 * The folder is asserted because a checkout of this repository also has a `.git`
	 * directory above ABSPATH, which the test would otherwise find and report.
	 *
	 * @ticket 66460
	 *
	 * @covers WP_Site_Health_Auto_Updates::test_vcs_abspath
	 */
	public function test_vcs_abspath_should_report_git_file() {
		$git_file = ABSPATH . '.git';

		// The test looks for `.svn` first, so a Subversion checkout never gets as far as `.git`.
		if ( file_exists( $git_file ) || file_exists( dirname( ABSPATH ) . '/.svn' ) ) {
			$this->markTestSkipped( 'This test needs ABSPATH to be the first place a VCS entry is found.' );
		}

		$health_check = new WP_Site_Health_Auto_Updates();

		file_put_contents( $git_file, "gitdir: /nonexistent/.git/worktrees/wordpress\n" );

		try {
			$result = $health_check->test_vcs_abspath();
		} finally {
			unlink( $git_file );
		}

		$this->assertSame( 'warning', $result['severity'] );
		$this->assertStringContainsString( '<code>' . ABSPATH . '</code>', $result['description'] );
		$this->assertStringContainsString( '<code>.git</code>', $result['description'] );
	}

	/**
	 * Tests that the test reports a plugin removing wp_version_check() from
	 * its hook on a single site install.
	 *
	 * This is the baseline for
	 * test_wp_version_check_attached_should_report_removed_check_on_main_site().
	 *
	 * @group ms-excluded
	 *
	 * @covers WP_Site_Health_Auto_Updates::test_wp_version_check_attached
	 */
	public function test_wp_version_check_attached_should_report_removed_check() {
		remove_action( 'wp_version_check', 'wp_version_check' );

		$health_check = new WP_Site_Health_Auto_Updates();
		$result       = $health_check->test_wp_version_check_attached();

		$this->assertIsArray( $result, 'The test should report the removed version check.' );
		$this->assertSame( 'fail', $result['severity'] );
	}

	/**
	 * Tests that the test reports a plugin removing wp_version_check() from
	 * its hook on the main site of a network.
	 *
	 * wp-includes/update.php only hooks wp_version_check() on the main site,
	 * so the test is limited to the main site on multisite (see #52135). It
	 * also requires the network admin, where Site Health has no screen, and
	 * the background updates test runs through the REST API, where
	 * is_network_admin() is false. The test never reports a problem on
	 * multisite as a result.
	 *
	 * @ticket 52135
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Site_Health_Auto_Updates::test_wp_version_check_attached
	 */
	public function test_wp_version_check_attached_should_report_removed_check_on_main_site() {
		$this->assertTrue( is_main_site(), 'The test should run on the main site.' );
		$this->assertFalse( is_network_admin(), 'Site Health does not run in the network admin.' );

		remove_action( 'wp_version_check', 'wp_version_check' );

		$health_check = new WP_Site_Health_Auto_Updates();
		$result       = $health_check->test_wp_version_check_attached();

		$this->assertIsArray( $result, 'The test should report the removed version check on the main site.' );
		$this->assertSame( 'fail', $result['severity'] );
	}

	/**
	 * Tests that the test does not report a missing wp_version_check() hook
	 * on a site other than the main site of a network.
	 *
	 * wp-includes/update.php only hooks wp_version_check() on the main site,
	 * so the hook is always missing on other sites.
	 *
	 * @ticket 52135
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_Site_Health_Auto_Updates::test_wp_version_check_attached
	 */
	public function test_wp_version_check_attached_should_not_report_on_other_sites() {
		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );

		remove_action( 'wp_version_check', 'wp_version_check' );

		$health_check = new WP_Site_Health_Auto_Updates();
		$result       = $health_check->test_wp_version_check_attached();

		restore_current_blog();

		$this->assertNull( $result );
	}
}
