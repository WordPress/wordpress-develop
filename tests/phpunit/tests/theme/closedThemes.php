<?php

/**
 * Test closed, suspended, and outdated themes handling.
 *
 * @group theme
 * @group update
 */
class Tests_Theme_ClosedThemes extends WP_UnitTestCase {

	/**
	 * Cleans up options and transients after each test.
	 */
	public function tear_down() {
		delete_site_transient( 'update_themes' );
		remove_all_filters( 'pre_http_request' );

		parent::tear_down();
	}

	/**
	 * Tests that wp_update_themes preserves closed, suspended, outdated, and security metadata.
	 *
	 * @covers ::wp_update_themes
	 */
	public function test_wp_update_themes_preserves_closed_suspended_and_outdated_metadata() {
		require_once ABSPATH . 'wp-includes/theme.php';
		require_once ABSPATH . 'wp-includes/update.php';

		$current_theme = get_stylesheet();

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) use ( $current_theme ) {
				if ( false === strpos( $url, 'api.wordpress.org/themes/update-check' ) ) {
					return $response;
				}

				$mock_body = array(
					'themes'       => array(
						$current_theme => array(
							'theme'        => $current_theme,
							'new_version'  => '99.0.0',
							'url'          => 'https://wordpress.org/themes/' . $current_theme . '/',
							'package'      => '',
							'closed'       => true,
							'is_suspended' => true,
							'is_security'  => true,
							'closed_date'  => '2025-01-15',
							'reason'       => 'security-issue',
							'reason_text'  => 'Security Issue',
						),
					),
					'translations' => array(),
					'no_update'    => array(
						'outdated-theme' => array(
							'theme'           => 'outdated-theme',
							'new_version'     => '1.0.0',
							'url'             => 'https://wordpress.org/themes/outdated-theme/',
							'is_outdated'     => true,
							'outdated_notice' => 'Theme has not been updated in over 2 years.',
						),
					),
					'closed'       => array(
						'suspended-theme' => array(
							'theme'        => 'suspended-theme',
							'status'       => 'suspend',
							'closed'       => true,
							'is_suspended' => true,
							'closed_date'  => '2024-03-01',
							'reason_text'  => 'Author request',
						),
					),
				);

				return array(
					'headers'  => array(),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => wp_json_encode( $mock_body ),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		delete_site_transient( 'update_themes' );
		wp_update_themes( array( 'core' => 'test' ) );

		$transient = get_site_transient( 'update_themes' );
		$this->assertIsObject( $transient, 'Transient should be an object.' );
		$this->assertArrayHasKey( $current_theme, $transient->response );

		$sec_item = $transient->response[ $current_theme ];
		$this->assertTrue( $sec_item['closed'] );
		$this->assertTrue( $sec_item['is_suspended'] );
		$this->assertTrue( $sec_item['is_security'] );
		$this->assertSame( '2025-01-15', $sec_item['closed_date'] );
		$this->assertSame( 'security-issue', $sec_item['reason'] );

		$this->assertArrayHasKey( 'outdated-theme', $transient->no_update );
		$outdated_item = $transient->no_update['outdated-theme'];
		$this->assertTrue( $outdated_item['is_outdated'] );
		$this->assertSame( 'Theme has not been updated in over 2 years.', $outdated_item['outdated_notice'] );

		$this->assertArrayHasKey( 'suspended-theme', $transient->no_update );
		$suspended_item = $transient->no_update['suspended-theme'];
		$this->assertTrue( $suspended_item['closed'] );
		$this->assertTrue( $suspended_item['is_suspended'] );
		$this->assertSame( '2024-03-01', $suspended_item['closed_date'] );
	}

	/**
	 * Tests that wp_prepare_themes_for_js includes closure, suspension, and outdated metadata.
	 *
	 * @covers ::wp_prepare_themes_for_js
	 */
	public function test_wp_prepare_themes_for_js_includes_closure_metadata() {
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$current_theme = get_stylesheet();

		$transient            = new stdClass();
		$transient->response  = array(
			$current_theme => array(
				'theme'           => $current_theme,
				'new_version'     => '99.0.0',
				'closed'          => true,
				'is_suspended'    => true,
				'is_security'     => true,
				'is_outdated'     => true,
				'closed_date'     => '2025-01-15',
				'reason_text'     => 'Security Issue',
				'outdated_notice' => 'Custom theme outdated message',
			),
		);
		$transient->no_update = array();
		set_site_transient( 'update_themes', $transient );

		$prepared = wp_prepare_themes_for_js();
		$this->assertIsArray( $prepared );

		$found_theme = null;
		foreach ( $prepared as $item ) {
			if ( $item['id'] === $current_theme ) {
				$found_theme = $item;
				break;
			}
		}

		$this->assertNotNull( $found_theme, 'Active theme must be in prepared themes.' );
		$this->assertTrue( $found_theme['closed'] );
		$this->assertTrue( $found_theme['is_suspended'] );
		$this->assertTrue( $found_theme['is_security'] );
		$this->assertTrue( $found_theme['is_outdated'] );
		$this->assertSame( '2025-01-15', $found_theme['closedDate'] );
		$this->assertSame( 'Security Issue', $found_theme['closedReason'] );
		$this->assertSame( 'Custom theme outdated message', $found_theme['outdatedNotice'] );
	}

	/**
	 * Tests that themes_api handles error => closed without converting to WP_Error.
	 *
	 * @covers ::themes_api
	 */
	public function test_themes_api_preserves_closed_theme_object() {
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) {
				if ( false === strpos( $url, 'api.wordpress.org/themes/info' ) ) {
					return $response;
				}

				return array(
					'headers'  => array(),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => wp_json_encode(
						array(
							'error'        => 'closed',
							'name'         => 'Test Closed Theme',
							'slug'         => 'test-closed-theme',
							'description'  => 'This theme has been closed.',
							'status'       => 'suspend',
							'closed'       => true,
							'is_closed'    => true,
							'is_suspended' => true,
							'is_security'  => true,
							'closed_date'  => '2025-01-15',
							'reason'       => 'security-issue',
							'reason_text'  => 'Security Issue',
							'is_outdated'  => false,
						)
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$result = themes_api( 'theme_information', array( 'slug' => 'test-closed-theme' ) );

		$this->assertNotWPError( $result );
		$this->assertIsObject( $result );
		$this->assertSame( 'closed', $result->error );
		$this->assertTrue( $result->closed );
		$this->assertTrue( $result->is_suspended );
		$this->assertTrue( $result->is_security );
		$this->assertSame( 'security-issue', $result->reason );
	}

	/**
	 * Tests Site Health check for closed and outdated themes.
	 *
	 * @covers WP_Site_Health::get_test_closed_plugins_and_themes
	 */
	public function test_site_health_closed_themes_check() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';

		$site_health   = new WP_Site_Health();
		$current_theme = get_stylesheet();

		// Case 1: Active theme is normal -> good status.
		delete_site_transient( 'update_themes' );
		delete_site_transient( 'update_plugins' );
		$res = $site_health->get_test_closed_plugins_and_themes();
		$this->assertSame( 'good', $res['status'] );

		// Case 2: Active theme closed for security -> critical status.
		$transient            = new stdClass();
		$transient->response  = array(
			$current_theme => array(
				'theme'       => $current_theme,
				'closed'      => true,
				'is_security' => true,
				'closed_date' => '2025-01-15',
			),
		);
		$transient->no_update = array();
		set_site_transient( 'update_themes', $transient );

		$res = $site_health->get_test_closed_plugins_and_themes();
		$this->assertSame( 'critical', $res['status'] );
		$this->assertStringContainsString( 'security issues', $res['label'] );

		// Case 3: Active theme suspended (non-security) -> recommended status.
		$transient->response = array(
			$current_theme => array(
				'theme'        => $current_theme,
				'status'       => 'suspend',
				'closed'       => true,
				'is_suspended' => true,
				'is_security'  => false,
			),
		);
		set_site_transient( 'update_themes', $transient );

		$res = $site_health->get_test_closed_plugins_and_themes();
		$this->assertSame( 'recommended', $res['status'] );
		$this->assertStringContainsString( 'no longer maintained or available', $res['label'] );

		// Case 4: Active theme outdated -> recommended status.
		$transient->response  = array();
		$transient->no_update = array(
			$current_theme => array(
				'theme'       => $current_theme,
				'is_outdated' => true,
			),
		);
		set_site_transient( 'update_themes', $transient );

		$res = $site_health->get_test_closed_plugins_and_themes();
		$this->assertSame( 'recommended', $res['status'] );
	}
}
