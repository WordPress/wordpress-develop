<?php

/**
 * @group general
 * @group template
 *
 * @covers ::wp_heartbeat_refresh_nonce_life
 */
class Tests_General_wpHeartbeatRefreshNonceLife extends WP_UnitTestCase {

	/**
	 * Tests that the refresh nonce lives longer than other nonces.
	 */
	public function test_refresh_nonce_life() {
		$this->assertSame( 2 * WEEK_IN_SECONDS, apply_filters( 'nonce_life', DAY_IN_SECONDS, 'heartbeat-refresh-nonce' ) );
	}

	/**
	 * Tests that other nonces keep their lifespan.
	 */
	public function test_other_nonce_life_is_unchanged() {
		$this->assertSame( DAY_IN_SECONDS, apply_filters( 'nonce_life', DAY_IN_SECONDS, 'heartbeat-nonce' ) );
		$this->assertSame( DAY_IN_SECONDS, apply_filters( 'nonce_life', DAY_IN_SECONDS, 'wp_rest' ) );
	}
}
