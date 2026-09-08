<?php

/**
 * @group admin
 *
 * @covers ::wp_refresh_heartbeat_nonces
 */
class Tests_Admin_Includes_Misc_WpRefreshHeartbeatNonces_Test extends WP_UnitTestCase {

	/**
	 * Tests that wp_refresh_heartbeat_nonces() correctly adds nonces to the response.
	 *
	 * @ticket 65199
	 */
	public function test_wp_refresh_heartbeat_nonces() {
		$response = array( 'some_data' => 'value' );

		$result = wp_refresh_heartbeat_nonces( $response );

		$this->assertArrayHasKey( 'rest_nonce', $result, 'The response should contain the rest_nonce.' );
		$this->assertArrayHasKey( 'heartbeat_nonce', $result, 'The response should contain the heartbeat_nonce.' );
		$this->assertSame( 'value', $result['some_data'], 'Existing data in the response should be preserved.' );

		$this->assertNotFalse( wp_verify_nonce( $result['rest_nonce'], 'wp_rest' ), 'The rest_nonce should be valid for "wp_rest".' );
		$this->assertNotFalse( wp_verify_nonce( $result['heartbeat_nonce'], 'heartbeat-nonce' ), 'The heartbeat_nonce should be valid for "heartbeat-nonce".' );
	}

	/**
	 * Tests that wp_refresh_heartbeat_nonces() overwrites existing nonces if they are already present.
	 *
	 * @ticket 65199
	 */
	public function test_wp_refresh_heartbeat_nonces_overwrites_existing() {
		$response = array(
			'rest_nonce'      => 'old_rest_nonce',
			'heartbeat_nonce' => 'old_heartbeat_nonce',
		);

		$result = wp_refresh_heartbeat_nonces( $response );

		$this->assertNotEquals( 'old_rest_nonce', $result['rest_nonce'], 'The rest_nonce should be updated.' );
		$this->assertNotEquals( 'old_heartbeat_nonce', $result['heartbeat_nonce'], 'The heartbeat_nonce should be updated.' );

		$this->assertNotFalse( wp_verify_nonce( $result['rest_nonce'], 'wp_rest' ), 'The rest_nonce should be valid for "wp_rest".' );
		$this->assertNotFalse( wp_verify_nonce( $result['heartbeat_nonce'], 'heartbeat-nonce' ), 'The heartbeat_nonce should be valid for "heartbeat-nonce".' );
	}
}
