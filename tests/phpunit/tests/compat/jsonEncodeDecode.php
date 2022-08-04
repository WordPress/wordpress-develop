<?php

/**
 * @group compat
 *
 * @covers Services_JSON
 */
class Tests_Compat_jsonEncodeDecode extends WP_UnitTestCase {

	public function test_json_encode_decode() {
		$this->setExpectedDeprecated( 'class-json.php' );
		$this->setExpectedDeprecated( 'Services_JSON::__construct' );
		$this->setExpectedDeprecated( 'Services_JSON::encodeUnsafe' );
		$this->setExpectedDeprecated( 'Services_JSON::_encode' );
		$this->setExpectedDeprecated( 'Services_JSON::reduce_string' );
		$this->setExpectedDeprecated( 'Services_JSON::decode' );
		$this->setExpectedDeprecated( 'Services_JSON::isError' );
		$this->setExpectedDeprecated( 'Services_JSON::strlen8' );
		$this->setExpectedDeprecated( 'Services_JSON::substr8' );

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
