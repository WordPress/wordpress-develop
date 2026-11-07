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
	 * @ticket 65819
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
	 * @ticket 65819
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
	 * @ticket 65819
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
}
