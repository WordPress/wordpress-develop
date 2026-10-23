<?php

/**
 * Tests for wp_get_auto_update_message().
 *
 * @group admin
 * @group upgrade
 *
 * @covers ::wp_get_auto_update_message
 */
class Tests_Admin_Includes_Update_WpGetAutoUpdateMessage extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/update.php';
	}

	public function set_up() {
		parent::set_up();

		wp_clear_scheduled_hook( 'wp_version_check' );
		wp_schedule_event( time() + 6 * HOUR_IN_SECONDS, 'twicedaily', 'wp_version_check' );

		// Start from a filesystem that the automatic updater can write to.
		add_filter( 'request_filesystem_credentials', '__return_true' );
	}

	/**
	 * Tests that the schedule is shown when automatic updates can run.
	 *
	 * @ticket 27814
	 */
	public function test_should_return_schedule_when_automatic_updates_can_run() {
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_false' );

		$this->assertSame( 'Automatic update scheduled in 6 hours.', wp_get_auto_update_message( 'plugin' ) );
	}

	/**
	 * Tests that the schedule is replaced when the site is under version control.
	 *
	 * @ticket 27814
	 *
	 * @dataProvider data_update_types
	 *
	 * @param string $type Type of update.
	 */
	public function test_should_return_version_control_message_when_under_version_control( $type ) {
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_true' );

		$message = wp_get_auto_update_message( $type );

		$this->assertStringStartsWith( 'Automatic update will not run because this site appears to be under version control.', $message );
		$this->assertStringNotContainsString( 'scheduled', $message );
	}

	/**
	 * Tests that the schedule is replaced when updating files requires FTP credentials.
	 *
	 * @ticket 27814
	 *
	 * @dataProvider data_update_types
	 *
	 * @param string $type Type of update.
	 */
	public function test_should_return_credentials_message_when_credentials_are_required( $type ) {
		remove_filter( 'request_filesystem_credentials', '__return_true' );
		add_filter( 'request_filesystem_credentials', '__return_false' );
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_false' );

		$message = wp_get_auto_update_message( $type );

		$this->assertStringStartsWith( 'Automatic update will not run because WordPress needs FTP credentials to update files.', $message );
		$this->assertStringNotContainsString( 'scheduled', $message );
	}

	/**
	 * Tests that the credentials message takes precedence, matching the order
	 * of the checks in WP_Automatic_Updater::should_update().
	 *
	 * @ticket 27814
	 */
	public function test_should_return_credentials_message_when_both_checks_fail() {
		remove_filter( 'request_filesystem_credentials', '__return_true' );
		add_filter( 'request_filesystem_credentials', '__return_false' );
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_true' );

		$this->assertStringStartsWith( 'Automatic update will not run because WordPress needs FTP credentials', wp_get_auto_update_message( 'plugin' ) );
	}

	/**
	 * Tests that the directory checked matches the one the automatic updater checks.
	 *
	 * @ticket 27814
	 *
	 * @dataProvider data_update_types
	 *
	 * @param string $type Type of update.
	 */
	public function test_should_check_the_directory_for_the_type( $type ) {
		$credentials_contexts = array();
		$vcs_contexts         = array();

		add_filter(
			'request_filesystem_credentials',
			static function ( $credentials, $form_post, $method, $error, $context ) use ( &$credentials_contexts ) {
				$credentials_contexts[] = $context;
				return true;
			},
			20,
			5
		);

		add_filter(
			'automatic_updates_is_vcs_checkout',
			static function ( $checkout, $context ) use ( &$vcs_contexts ) {
				$vcs_contexts[] = $context;
				return false;
			},
			10,
			2
		);

		wp_get_auto_update_message( $type );

		$expected = array(
			''       => ABSPATH,
			'plugin' => WP_PLUGIN_DIR,
			'theme'  => get_theme_root(),
		);

		$this->assertSame( array( $expected[ $type ] ), $credentials_contexts, 'The wrong directory was checked for credentials.' );
		$this->assertSame( array( $expected[ $type ] ), $vcs_contexts, 'The wrong directory was checked for version control.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_update_types() {
		return array(
			'no type' => array( '' ),
			'plugin'  => array( 'plugin' ),
			'theme'   => array( 'theme' ),
		);
	}

	/**
	 * Tests that users who can view Site Health are linked to it.
	 *
	 * @ticket 27814
	 */
	public function test_should_link_to_site_health_for_users_who_can_view_it() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}
		wp_set_current_user( $user_id );

		add_filter( 'automatic_updates_is_vcs_checkout', '__return_true' );

		$this->assertStringContainsString( 'href="' . admin_url( 'site-health.php' ) . '"', wp_get_auto_update_message( 'plugin' ) );
	}

	/**
	 * Tests that users who cannot view Site Health are not linked to it.
	 *
	 * @ticket 27814
	 */
	public function test_should_not_link_to_site_health_for_users_who_cannot_view_it() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		add_filter( 'automatic_updates_is_vcs_checkout', '__return_true' );

		$this->assertStringNotContainsString( 'site-health.php', wp_get_auto_update_message( 'plugin' ) );
	}
}
