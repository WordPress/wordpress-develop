<?php

/**
 * Tests for the network admin email change confirmation token.
 *
 * @group ms-required
 * @group multisite
 *
 * @covers ::update_network_option_new_admin_email
 */
class Tests_Multisite_UpdateNetworkOptionNewAdminEmail extends WP_UnitTestCase {

	/**
	 * The confirmation token that gates a network admin email change must be
	 * generated with a CSPRNG (wp_rand()/random_int()), not the predictable
	 * Mersenne Twister (mt_rand()).
	 *
	 * The stored token is `md5( $new_email . time() . <random> )` and is the only
	 * secret in the confirmation link (the consumer in wp-admin/network/settings.php
	 * compares it with hash_equals(), so there is no other gate). An attacker knows
	 * the proposed email address and the approximate time() of the request, so the
	 * entire unpredictability of the token rests on the random component. If that
	 * came from mt_rand(), recovering or forcing the Mersenne Twister state would
	 * make the token fully predictable and allow confirming the change without
	 * access to the mailbox.
	 *
	 * This is a regression guard: it reproduces the token the way a vulnerable
	 * mt_rand()-based implementation would and asserts the real, stored token does
	 * not match — proving the random component is sourced from wp_rand() instead.
	 * It fails on the old mt_rand() code and passes on the wp_rand() fix.
	 */
	public function test_confirmation_token_is_not_predictable_from_mersenne_twister() {
		$new_email = 'predictable-attack@example.com';

		// Warm the option cache so the function body does not advance the
		// mt_rand() stream between the reseed below and the token computation.
		get_site_option( 'admin_email' );

		// Pin the Mersenne Twister to a known state, as an attacker who has
		// recovered or forced the PRNG seed would.
		mt_srand( 1 );

		// The value a vulnerable md5( ... . mt_rand() ) would consume first.
		$predicted_rand = mt_rand();

		// Build the candidate tokens for a small window of timestamps, to absorb a
		// possible second boundary between this computation and the real call.
		$now       = time();
		$predicted = array();
		foreach ( array( $now - 1, $now, $now + 1 ) as $candidate_time ) {
			$predicted[] = md5( $new_email . $candidate_time . $predicted_rand );
		}

		// Restore the identical PRNG state immediately before the real call, so a
		// vulnerable implementation would consume exactly $predicted_rand again.
		mt_srand( 1 );
		update_network_option_new_admin_email( '', $new_email );

		$stored = get_site_option( 'network_admin_hash' );
		$this->assertIsArray( $stored, 'The pending admin email change should be stored.' );
		$this->assertArrayHasKey( 'hash', $stored, 'The stored data should contain a confirmation hash.' );

		$this->assertNotContains(
			$stored['hash'],
			$predicted,
			'The network admin email confirmation token is predictable from the Mersenne Twister state. '
				. 'It must be generated with wp_rand() (CSPRNG), matching update_option_new_admin_email().'
		);
	}

	/**
	 * Sanity check: the token still has the documented shape (a 32-character md5
	 * hash) and a fresh value is produced on each request.
	 */
	public function test_confirmation_token_shape() {
		update_network_option_new_admin_email( '', 'first-token@example.com' );
		$first = get_site_option( 'network_admin_hash' );

		update_network_option_new_admin_email( '', 'second-token@example.com' );
		$second = get_site_option( 'network_admin_hash' );

		$this->assertSame( 32, strlen( $first['hash'] ), 'The token should be a 32-character md5 hash.' );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $first['hash'] );
		$this->assertNotSame( $first['hash'], $second['hash'], 'A distinct token should be generated per request.' );
	}
}
