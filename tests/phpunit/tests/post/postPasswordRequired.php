<?php

/**
 * @group post
 * @covers ::post_password_required
 */
class Tests_Post_PostPasswordRequired extends WP_UnitTestCase {
	/**
	 * @var PasswordHash
	 */
	protected static $wp_hasher;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . WPINC . '/class-phpass.php';
		self::$wp_hasher = new PasswordHash( 8, true );
	}

	public function test_post_password_required() {
		$password = 'password';

		// Create a post with a password:
		$post_id = self::factory()->post->create(
			array(
				'post_password' => $password,
			)
		);

		// Password is required:
		$this->assertTrue( post_password_required( $post_id ) );
	}

	public function test_post_password_not_required_with_valid_cookie() {
		$password = 'password';

		// Create a post with a password:
		$post_id = self::factory()->post->create(
			array(
				'post_password' => $password,
			)
		);

		// Set the cookie:
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = wp_hash_password( $password );

		// Check if the password is required:
		$required = post_password_required( $post_id );

		// Clear the cookie:
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		// Password is not required:
		$this->assertFalse( $required );
	}

	/**
	 * @ticket 21022
	 * @ticket 50027
	 */
	public function test_post_password_hashed_with_phpass_remains_valid() {
		$password = 'password';

		// Create a post with a password:
		$post_id = self::factory()->post->create(
			array(
				'post_password' => $password,
			)
		);

		// Set the cookie with the phpass hash:
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = self::$wp_hasher->HashPassword( $password );

		// Check if the password is required:
		$required = post_password_required( $post_id );

		// Clear the cookie:
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		// Password is not required as it remains valid when hashed with phpass:
		$this->assertFalse( $required );
	}
}
