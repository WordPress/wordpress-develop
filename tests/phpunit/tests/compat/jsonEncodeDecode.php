<?php

/**
 * @group compat
 *
 * @covers Services_JSON
 */
class Tests_Compat_jsonEncodeDecode extends WP_UnitTestCase {

	public function test_json_encode_decode() {
		$this->expectDeprecation();

		require_once ABSPATH . WPINC . '/class-json.php';
		$json = new Services_JSON();

		// Super basic test to verify Services_JSON is intact and working.
		$this->assertSame(
			'["foo"]',
			$json->encodeUnsafe( array( 'foo' ) ),
			'encodeUnsafe() did not return expected output'
		);
		$this->assertSame(
			array( 'foo' ),
			$json->decode( '["foo"]' ),
			'decode() did not return expected output'
		);
	}
}
