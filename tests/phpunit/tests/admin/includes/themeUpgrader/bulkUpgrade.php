<?php

require_once dirname( __DIR__ ) . '/class-wp-upgrader-testcase.php';

/**
 * Test class for Theme_Upgrader::bulk_upgrade().
 *
 * @group upgrader
 * @group theme_upgrader
 *
 * @covers Theme_Upgrader::bulk_upgrade
 */
class Tests_Admin_Includes_ThemeUpgrader_BulkUpgrade extends WP_Upgrader_TestCase {
	/**
	 * Whether this is a theme upgrade.
	 *
	 * @var bool
	 */
	protected $is_theme = true;

	/**
	 * Tests that Theme_Upgrader::bulk_upgrade does not send error data.
	 *
	 * @ticket 51928
	 *
	 * @dataProvider data_should_not_send_error_data
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

		$this->install_older_version( $theme_upgrader, $themes['install'], $update_themes );

		$theme_upgrader
			->expects( $this->never() )
			->method( 'send_error_data' );

		// Do the bulk upgrade.
		ob_start();
		$actual_results = $theme_upgrader->bulk_upgrade( $themes['themes'] );
		$actual_message = ob_get_clean();

		// Validate the upgrade happened.
		$theme_name = $themes['themes'][0];
		$this->assertSame(
			$expected['results'][ $theme_name ]['source'],
			$actual_results[ $theme_name ]['source'],
			'The expected results were not returned.'
		);
		$this->assertContainsAdminMessages(
			$expected['messages'],
			$actual_message,
			'The actual messages did not match the expected messages.'
		);

		// Validate there's no error data.
		$this->assertEmpty( $this->error_data, 'The error data was not empty.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
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
						$this->theme_data_provider->theme_name => $this->theme_data_provider->get_upgrade_results( 'new' ),
					),
					'messages' => $this->theme_data_provider->get_messages( 'success_upgrade' ),
				),
			),
		);
	}

	/**
	 * Tests that Theme_Upgrader::bulk_upgrade sends error data.
	 *
	 * @ticket 51928
	 *
	 * @dataProvider data_should_send_error_data
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
		$this->assertSame(
			$expected['results'],
			$actual_results,
			'The expected results were not returned.'
		);
		$this->assertContainsAdminMessages(
			$expected['messages'],
			$actual_message,
			'The actual messages did not match the expected messages.'
		);

		// Validate the sent error data.
		$this->assertContainsErrorDataStats(
			$expected['stats'],
			'Incorrect error data was returned.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
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
