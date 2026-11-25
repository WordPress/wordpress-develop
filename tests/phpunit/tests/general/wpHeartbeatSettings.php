<?php

/**
 * Tests for the `wp_heartbeat_settings()` function.
 *
 * @group general
 *
 * @covers ::wp_heartbeat_settings
 */
class Tests_General_WpHeartbeatSettings extends WP_UnitTestCase {

	/**
	 * Tests frontend logged-out settings behavior.
	 *
	 * @ticket 66614
	 */
	public function test_wp_heartbeat_settings_frontend_logged_out() {
		wp_set_current_user( 0 );

		$initial  = array( 'interval' => 60 );
		$settings = wp_heartbeat_settings( $initial );

		$this->assertSame( 60, $settings['interval'] );
		$this->assertSame( admin_url( 'admin-ajax.php', 'relative' ), $settings['ajaxurl'] );
		$this->assertArrayNotHasKey( 'nonce', $settings );
	}

	/**
	 * Tests frontend logged-in settings behavior with valid nonce.
	 *
	 * @ticket 66614
	 */
	public function test_wp_heartbeat_settings_frontend_logged_in() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$settings = wp_heartbeat_settings( array() );

		$this->assertArrayHasKey( 'nonce', $settings );
		$this->assertNotFalse( wp_verify_nonce( $settings['nonce'], 'heartbeat-nonce' ) );
		$this->assertSame( admin_url( 'admin-ajax.php', 'relative' ), $settings['ajaxurl'] );
	}

	/**
	 * Tests that existing settings are preserved.
	 *
	 * @ticket 66614
	 */
	public function test_wp_heartbeat_settings_preserves_custom_settings() {
		wp_set_current_user( 0 );

		$custom   = array(
			'interval'  => 15,
			'customKey' => 'customValue',
		);
		$settings = wp_heartbeat_settings( $custom );

		$this->assertSame( 15, $settings['interval'] );
		$this->assertSame( 'customValue', $settings['customKey'] );
	}

	/**
	 * Tests that admin requests do not get an `ajaxurl`, and get a nonce only when logged in.
	 *
	 * Admin screens define `ajaxurl` themselves.
	 *
	 * @ticket 66757
	 *
	 * @dataProvider data_wp_heartbeat_settings_admin
	 *
	 * @param bool $logged_in Whether a user is logged in.
	 */
	public function test_wp_heartbeat_settings_admin( $logged_in ) {
		wp_set_current_user( $logged_in ? self::factory()->user->create() : 0 );
		set_current_screen( 'dashboard' );

		$settings = wp_heartbeat_settings( array() );

		$this->assertArrayNotHasKey( 'ajaxurl', $settings, 'Admin requests should not get an ajaxurl.' );

		if ( $logged_in ) {
			$this->assertNotFalse( wp_verify_nonce( $settings['nonce'] ?? '', 'heartbeat-nonce' ), 'Logged-in admin requests should get a valid nonce.' );
		} else {
			$this->assertArrayNotHasKey( 'nonce', $settings, 'Logged-out requests should not get a nonce.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ bool }>
	 */
	public static function data_wp_heartbeat_settings_admin() {
		return array(
			'logged in'  => array( true ),
			'logged out' => array( false ),
		);
	}
}
