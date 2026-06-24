<?php

/**
 * @group functions
 *
 * @covers ::wp_json_file_decode
 */
class Tests_Functions_WpJsonFileDecode extends WP_UnitTestCase {
	/**
	 * @ticket 53238
	 */
	public function test_wp_json_file_decode() {
		$result = wp_json_file_decode(
			DIR_TESTDATA . '/blocks/notice/block.json'
		);

		$this->assertIsObject( $result );
		$this->assertSame( 'tests/notice', $result->name );
	}

	/**
	 * @ticket 53238
	 */
	public function test_wp_json_file_decode_associative_array() {
		$result = wp_json_file_decode(
			DIR_TESTDATA . '/blocks/notice/block.json',
			array( 'associative' => true )
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'tests/notice', $result['name'] );
	}
}
