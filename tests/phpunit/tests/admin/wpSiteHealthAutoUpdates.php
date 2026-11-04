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
}
