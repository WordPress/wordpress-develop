<?php

require_once __DIR__ . '/Admin_WpAutomaticUpdater_TestCase.php';

/**
 * @group admin
 * @group upgrade
 *
 * @covers WP_Automatic_Updater::is_vcs_checkout
 */
class Admin_WpAutomaticUpdater_IsVcsCheckout_Test extends Admin_WpAutomaticUpdater_TestCase {

	/**
	 * Tests that `WP_Automatic_Updater::is_vcs_checkout()` returns `false`
	 * when none of the checked directories are allowed.
	 *
	 * @ticket 58563
	 */
	public function test_is_vcs_checkout_should_return_false_when_no_directories_are_allowed() {
		$updater_mock = $this->getMockBuilder( 'WP_Automatic_Updater' )
			// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
			->setMethods( array( 'is_allowed_dir' ) )
			->getMock();

		/*
		 * As none of the directories should be allowed, simply mocking `WP_Automatic_Updater`
		 * and forcing `::is_allowed_dir()` to return `false` removes the need to run the test
		 * in a separate process due to setting the `open_basedir` PHP directive.
		 */
		$updater_mock->expects( $this->any() )->method( 'is_allowed_dir' )->willReturn( false );

		$this->assertFalse( $updater_mock->is_vcs_checkout( get_temp_dir() ) );
	}
}
