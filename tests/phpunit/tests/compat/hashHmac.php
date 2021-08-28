<?php

/**
 * @group compat
 */
class Tests_Compat_hashHmac extends WP_UnitTestCase {

	function test_hash_hmac_simple() {
		$this->assertSame( '140d1cb79fa12e2a31f32d35ad0a2723', _hash_hmac( 'md5', 'simple', 'key' ) );
		$this->assertSame( '993003b95758e0ac2eba451a4c5877eb1bb7b92a', _hash_hmac( 'sha1', 'simple', 'key' ) );
	}

	function test_hash_hmac_padding() {
		$this->assertSame( '3c1399103807cf12ec38228614416a8c', _hash_hmac( 'md5', 'simple', '65 character key 65 character key 65 character key 65 character k' ) );
		$this->assertSame( '4428826d20003e309d6c2a6515891370daf184ea', _hash_hmac( 'sha1', 'simple', '65 character key 65 character key 65 character key 65 character k' ) );
	}

	function test_hash_hmac_output() {
		$this->assertSame( array( 1 => '140d1cb79fa12e2a31f32d35ad0a2723' ), unpack( 'H32', _hash_hmac( 'md5', 'simple', 'key', true ) ) );
		$this->assertSame( array( 1 => '993003b95758e0ac2eba451a4c5877eb1bb7b92a' ), unpack( 'H40', _hash_hmac( 'sha1', 'simple', 'key', true ) ) );
	}
}
