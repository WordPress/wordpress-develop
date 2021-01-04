<?php

require_once dirname( __DIR__ ) . '/class-wp-upgrader-testcase.php';

/**
 * @covers Theme_Upgrader::bulk_upgrade
 *
 * @group  upgrader
 * @group  theme_upgrader
 */
class Tests_Admin_Includes_ThemeUpgrader_BulkUpgrade extends WP_Upgrader_TestCase {
	protected $is_theme = true;

	/**
	 * @dataProvider data_should_not_send_error_data
	 *
	 * @group        51928
	 *
	 * @param array $themes        Array of themes information.
	 * @param array $update_themes Value for the "update_themes" transient.
	 * @param array $expected      Array of expected results and messages.
	 */
	public function test_should_not_send_error_data( $themes, $update_themes, $expected ) {
		$this->theme = $themes;

		$theme_upgrader = $this
			->getMockBuilder( Theme_Upgrader::class )
			->setConstructorArgs( array( $this->mock_skin_feedback() ) )
			->setMethods( array( 'send_error_data' ) )
			->getMock();

		$theme_upgrader
			->expects( $this->never() )
			->method( 'send_error_data' );

		$this->install_older_version( $theme_upgrader, $themes['install'], $update_themes );

		// Do the bulk upgrade.
		ob_start();
		$actual_results = $theme_upgrader->bulk_upgrade( $themes['themes'] );
		$actual_message = ob_get_clean();

		// Validate the upgrade happened.
		$this->assertSame( $expected['results'], $actual_results );
		$this->assertContainsAdminMessages( $expected['messages'], $actual_message );

		// Validate there's no error data.
		$this->assertEmpty( $this->error_data );
	}

	public function data_should_not_send_error_data() {
		$this->init_theme_data_provider();

		return array(
			'when local zip file exists' => array(
				'themes'        => array(
					'install' => $this->theme_data_provider->packages['old'],
					'themes'  => array( $this->theme_data_provider->theme_name ),
				),
				'update_themes' => $this->theme_data_provider->get_update_themes( 'new' ),
				'expected'      => array(
					'results'  => array(
						'upgrader-test-theme' => $this->theme_data_provider->get_upgrade_results( 'new' ),
					),
					'messages' => $this->theme_data_provider->get_messages( 'success_upgrade' ),
				),
			),
		);
	}

	/**
	 * @dataProvider data_should_send_error_data
	 *
	 * @group        51928
	 *
	 * @param array $themes        Array of themes information.
	 * @param array $update_themes Value for the "update_themes" transient.
	 * @param array $expected      Array of expected results, messages, and stats.
	 */
	public function test_should_send_error_data( $themes, $update_themes, $expected ) {
		$this->theme = $themes;

		$this->shortcircuit_w_org_download();
		$this->capture_error_data();

		$theme_upgrader = new Theme_Upgrader( $this->mock_skin_feedback() );

		$this->install_older_version( $theme_upgrader, $themes['install'], $update_themes );

		// Do the bulk upgrade.
		ob_start();
		$actual_results = $theme_upgrader->bulk_upgrade( $themes['themes'] );
		$actual_message = ob_get_clean();

		// Validate the upgrade did not happen.
		$this->assertSame( $expected['results'], $actual_results );
		$this->assertContainsAdminMessages( $expected['messages'], $actual_message );

		// Validate the sent error data.
		$this->assertContainsErrorDataStats( $expected['stats'] );
	}

	public function data_should_send_error_data() {
		$this->init_theme_data_provider();

		return array(
			'when new version does not exist' => array(
				'themes'        => array(
					'install' => $this->theme_data_provider->packages['old'],
					'themes'  => array( $this->theme_data_provider->theme_name ),
				),
				'update_themes' => $this->theme_data_provider->get_update_themes( 'doesnotexist' ),
				'expected'      => array(
					'results'  => array( $this->theme_data_provider->theme_name => null ),
					'messages' => $this->theme_data_provider->get_messages( 'failed_update', 'doesnotexist' ),
					'stats'    => $this->theme_data_provider->error_data_stats,
				),
			),
		);
	}
}
