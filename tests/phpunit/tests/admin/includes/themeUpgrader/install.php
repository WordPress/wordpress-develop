<?php

require_once dirname( __DIR__ ) . '/class-wp-upgrader-testcase.php';

/**
 * @covers Theme_Upgrader::install
 *
 * @group  upgrader
 * @group  theme_upgrader
 */
class Tests_Admin_Includes_ThemeUpgrader_Install extends WP_Upgrader_TestCase {
	protected $is_theme = true;

	/**
	 * @dataProvider data_should_not_send_error_data
	 *
	 * @group        51928
	 *
	 * @param array $theme    Array of theme information.
	 * @param array $expected Array of expected admin output messages.
	 */
	public function test_should_not_send_error_data( $theme, $expected ) {
		$this->theme = $theme;

		$theme_upgrader = $this
			->getMockBuilder( Theme_Upgrader::class )
			->setConstructorArgs( array( $this->mock_skin_feedback() ) )
			->setMethods( array( 'send_error_data' ) )
			->getMock();

		$theme_upgrader
			->expects( $this->never() )
			->method( 'send_error_data' );

		// Do the install.
		ob_start();
		$actual         = $theme_upgrader->install( $theme['package'] );
		$actual_message = ob_get_clean();

		// Validate the install happened.
		$this->assertTrue( $actual );
		$this->assertContainsAdminMessages( $expected['messages'], $actual_message );

		// Validate there's no error data.
		$this->assertEmpty( $this->error_data );
	}

	public function data_should_not_send_error_data() {
		$this->init_theme_data_provider();

		return array(
			'when local zip file exists' => array(
				'theme'    => array(
					'package' => $this->theme_data_provider->packages['new'],
				),
				'expected' => array(
					'messages' => $this->theme_data_provider->get_messages( 'success_install' ),
				),
			),
		);
	}

	/**
	 * @dataProvider data_should_send_error_data
	 *
	 * @group        51928
	 *
	 * @param array $theme    Array of theme information.
	 * @param array $expected Array of expected messages and stats.
	 */
	public function test_should_send_error_data( $theme, $expected ) {
		$this->theme = $theme;

		$this->shortcircuit_w_org_download();
		$this->capture_error_data();

		$theme_upgrader = new Theme_Upgrader( $this->mock_skin_feedback() );

		// Do the install.
		ob_start();
		$result         = $theme_upgrader->install( $theme['package'] );
		$actual_message = ob_get_clean();

		// Validate the upgrade did not happen.
		$this->assertNull( $result );
		$this->assertContainsAdminMessages( $expected['messages'], $actual_message );

		// Validate the sent error data.
		$this->assertContainsErrorDataStats( $expected['stats'] );
	}

	public function data_should_send_error_data() {
		$this->init_theme_data_provider();

		return array(
			'no_package when empty package given' => array(
				'theme'    => array(
					'package' => '',
				),
				'expected' => array(
					'messages' => $this->theme_data_provider->get_messages( 'not_available' ),
					'stats'    => $this->theme_data_provider->error_data_stats,
				),
			),
			'when package does not exist'         => array(
				'theme'    => array(
					'package' => $this->theme_data_provider->packages['doesnotexist'],
				),
				'expected' => array(
					'messages' => $this->theme_data_provider->get_messages( 'failed_install', 'doesnotexist' ),
					'stats'    => $this->theme_data_provider->error_data_stats,
				),
			),
		);
	}
}
