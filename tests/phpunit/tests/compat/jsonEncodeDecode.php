<?php

/**
 * @group compat
 */
class Tests_Compat_jsonEncodeDecode extends WP_UnitTestCase {

	function test_json_encode_decode() {
		$this->expectDeprecation();

		require_once ABSPATH . WPINC . '/class-json.php';
		$json = new Services_JSON();
		// Super basic test to verify Services_JSON is intact and working.
		$this->assertSame( '["foo"]', $json->encodeUnsafe( array( 'foo' ) ) );
		$this->assertSame( array( 'foo' ), $json->decode( '["foo"]' ) );
	}
}
