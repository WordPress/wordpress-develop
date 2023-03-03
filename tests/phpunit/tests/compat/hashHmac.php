<?php

/**
 * @group compat
 *
 * @covers ::hash_hmac
 * @covers ::_hash_hmac
 */
class Tests_Compat_hashHmac extends WP_UnitTestCase {

	/**
	 * Test that hash_hmac() is always available (either from PHP or WP).
	 */
	public function test_hash_hmac_availability() {
		$this->assertTrue( function_exists( 'hash_hmac' ) );
	}

	public function test_hash_hmac_simple() {
		$data = 'simple';
		$key  = 'key';

		$this->assertSame(
			'140d1cb79fa12e2a31f32d35ad0a2723',
			_hash_hmac( 'md5', $data, $key ),
			'MD5 hash does not match'
		);
		$this->assertSame(
			'993003b95758e0ac2eba451a4c5877eb1bb7b92a',
			_hash_hmac( 'sha1', $data, $key ),
			'sha1 hash does not match'
		);
	}

	public function test_hash_hmac_padding() {
		$data = 'simple';
		$key  = '65 character key 65 character key 65 character key 65 character k';

		$this->assertSame(
			'3c1399103807cf12ec38228614416a8c',
			_hash_hmac( 'md5', $data, $key ),
			'MD5 hash does not match'
		);
		$this->assertSame(
			'4428826d20003e309d6c2a6515891370daf184ea',
			_hash_hmac( 'sha1', $data, $key ),
			'sha1 hash does not match'
		);
	}

	public function test_hash_hmac_output() {
		$data = 'simple';
		$key  = 'key';

		$this->assertSame(
			array( 1 => '140d1cb79fa12e2a31f32d35ad0a2723' ),
			unpack( 'H32', _hash_hmac( 'md5', $data, $key, true ) ),
			'unpacked MD5 hash does not match'
		);
		$this->assertSame(
			array( 1 => '993003b95758e0ac2eba451a4c5877eb1bb7b92a' ),
			unpack( 'H40', _hash_hmac( 'sha1', $data, $key, true ) ),
			'unpacked sha1 hash does not match'
		);
	}
}
