<?php

/**
 * Tests for the PasswordHash external library.
 *
 * @covers PasswordHash
 */
class Tests_User_PasswordHash extends WP_UnitTestCase {

	/**
	 * Tests that PasswordHash::gensalt_blowfish() does not throw a deprecation notice on PHP 8.1.
	 *
	 * The notice that we should not see:
	 * `Deprecated: Implicit conversion from float to int loses precision`.
	 *
	 * @ticket 56340
	 *
	 * @covers PasswordHash::gensalt_blowfish
	 *
	 * @requires PHP 8.1
	 * @doesNotPerformAssertions
	 */
	public function test_gensalt_blowfish_should_not_throw_deprecation_notice_on_php81() {
		$hasher = new PasswordHash( 8, true );
		$hasher->gensalt_blowfish( 'a password string' );
	}
}
