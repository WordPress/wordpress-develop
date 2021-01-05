<?php

require_once dirname( __DIR__ ) . '/class-wp-upgrader-testcase.php';

/**
 * @covers Theme_Upgrader::upgrade
 *
 * @group  upgrader
 * @group  theme_upgrader
 */
class Tests_Admin_Includes_ThemeUpgrader_Upgrade extends WP_Upgrader_TestCase {

	public function setUp() {
		parent::setUp();
		$this->is_theme = true;
	}

	/**
	 * @dataProvider data_should_not_send_error_data
	 *
	 * @group        51928
	 *
	 * @param array $theme         Array of theme information.
	 * @param array $update_themes Value for the "update_themes" transient.
	 * @param array $expected      Array of expected results and/or messages.
	 */
	public function test_should_not_send_error_data( $theme, $update_themes, $expected ) {
		$this->theme = $theme;

		$theme_upgrader = $this
			->getMockBuilder( Theme_Upgrader::class )
			->setConstructorArgs( array( $this->mock_skin_feedback() ) )
			->setMethods( array( 'send_error_data' ) )
			->getMock();

		$theme_upgrader
			->expects( $this->never() )
			->method( 'send_error_data' );

		$this->install_older_version( $theme_upgrader, $theme['install'], $update_themes );

		// Do the upgrade.
		ob_start();
		$actual_results = $theme_upgrader->upgrade( $theme['theme'] );
		$actual_message = ob_get_clean();

		// Validate the upgrade happened.
		$this->assertSame( $expected['results']['source'], $actual_results['source'] );
		$this->assertContainsAdminMessages( $expected['messages'], $actual_message );

		// Validate there's no error data.
		$this->assertEmpty( $this->error_data );
	}

	public function data_should_not_send_error_data() {
		$this->init_theme_data_provider();

		return array(
			'when local zip file exists' => array(
				'theme'         => array(
					'install' => $this->theme_data_provider->packages['old'],
					'theme'   => $this->theme_data_provider->theme_name,
				),
				'update_themes' => $this->theme_data_provider->get_update_themes( 'new' ),
				'expected'      => array(
					'results'  => $this->theme_data_provider->get_upgrade_results( 'new' ),
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
	 * @param array $theme         Array of theme information.
	 * @param array $update_themes Value for the "update_themes" transient.
	 * @param array $expected      Array of expected messages and stats.
	 */
	public function test_should_send_error_data( $theme, $update_themes, $expected ) {
		$this->theme = $theme;

		$this->shortcircuit_w_org_download();
		$this->capture_error_data();

		$theme_upgrader = new Theme_Upgrader( $this->mock_skin_feedback() );

		$this->install_older_version( $theme_upgrader, $theme['install'], $update_themes );

		// Do the upgrade.
		ob_start();
		$result         = $theme_upgrader->upgrade( $theme['theme'] );
		$actual_message = ob_get_clean();

		// Validate the upgrade did not happen.
		$this->assertWPError( $result );
		$this->assertSame( $expected['WP_Error']['errors'], $result->errors );
		$this->assertSame( $expected['WP_Error']['error_data'], $result->error_data );
		$this->assertContainsAdminMessages( $expected['messages'], $actual_message );

		// Validate the sent error data.
		$this->assertContainsErrorDataStats( $expected['stats'] );
	}

	public function data_should_send_error_data() {
		$this->init_theme_data_provider();

		return array(
			'when new version does not exist' => array(
				'theme'         => array(
					'install' => $this->theme_data_provider->packages['old'],
					'theme'   => $this->theme_data_provider->theme_name,
				),
				'update_themes' => $this->theme_data_provider->get_update_themes( 'doesnotexist' ),
				'expected'      => array(
					'WP_Error' => array(
						'errors'     => array(
							'incompatible_archive' => array( 'The package could not be installed.' ),
						),
						'error_data' => array(
							'incompatible_archive' => "PCLZIP_ERR_MISSING_FILE (-4) : Missing archive file '{$this->theme_data_provider->packages['doesnotexist']}'",
						),
					),
					'messages' => $this->theme_data_provider->get_messages( 'failed_update', 'doesnotexist' ),
					'stats'    => $this->theme_data_provider->error_data_stats,
				),
			),
		);
	}
}
