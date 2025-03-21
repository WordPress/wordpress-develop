<?php
/**
 * Tests for wp_fast_hash().
 *
 * @group functions
 *
 * @covers ::wp_fast_hash
 */
class Tests_Functions_WpFastHash extends WP_UnitTestCase {

	/**
	 * Test that the wp_fast_hash function correctly hashes the message.
	 *
	 * @ticket 63136
	 */
	public function test_wp_fast_hash_should_return_a_non_empty_string() {
		$message = 'test message';

		$result = wp_fast_hash( $message );

		$this->assertNotEmpty( $result );
	}

	/**
	 * Test that wp_fast_hash returns the expected format.
	 *
	 * @ticket 63136
	 */
	public function test_wp_fast_hash_should_return_correct_format() {
		$message = 'test message';

		$result = wp_fast_hash( $message );

		$this->assertStringStartsWith( '$generic$', $result );

		$base64_part = substr( $result, strlen( '$generic$' ) );

		$this->assertNotEmpty( $base64_part );
		$this->assertMatchesRegularExpression( '/^[a-zA-Z0-9\-_]+$/', $base64_part );
	}

	/**
	 * Test that wp_fast_hash handles special characters correctly.
	 *
	 * @ticket 63136
	 */
	public function test_wp_fast_hash_should_handle_special_characters() {
		$message = 'Test @#%$&*()';

		$result = wp_fast_hash( $message );

		$this->assertNotEmpty( $result );
	}
}
