<?php
/**
 * Tests for AT Protocol functions.
 *
 * @package WordPress
 *
 * @group atproto
 */
class Tests_ATProto extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'atproto_did' );
		remove_all_filters( 'atproto_did' );
		parent::tear_down();
	}

	/**
	 * @dataProvider data_wp_is_atproto_did
	 *
	 * @covers ::wp_is_atproto_did
	 */
	public function test_wp_is_atproto_did( $did, $expected ) {
		$this->assertSame( $expected, wp_is_atproto_did( $did ) );
	}

	public function data_wp_is_atproto_did() {
		return array(
			'did:plc'            => array( 'did:plc:ewvi7nxzyoun6zhxrhs64oiz', true ),
			'did:web domain'     => array( 'did:web:example.com', true ),
			'did:web localhost'  => array( 'did:web:localhost%3A3000', true ),
			'empty'              => array( '', false ),
			'invalid method'     => array( 'did:key:z6MkiTBzTbWb4TLEt', false ),
			'invalid plc length' => array( 'did:plc:abc', false ),
			'did:web path'       => array( 'did:web:example.com%3Ausers%3Aalice', false ),
			'did:web uppercase'  => array( 'did:web:Example.com', false ),
			'newline'            => array( "did:web:example.com\ninvalid", false ),
			'non-string'         => array( 123, false ),
		);
	}

	/**
	 * @covers ::get_atproto_did
	 */
	public function test_get_atproto_did_returns_option_value() {
		update_option( 'atproto_did', 'did:web:example.com' );

		$this->assertSame( 'did:web:example.com', get_atproto_did() );
	}

	/**
	 * @covers ::get_atproto_did
	 */
	public function test_get_atproto_did_applies_filter() {
		add_filter(
			'atproto_did',
			static function () {
				return 'did:plc:ewvi7nxzyoun6zhxrhs64oiz';
			}
		);

		$this->assertSame( 'did:plc:ewvi7nxzyoun6zhxrhs64oiz', get_atproto_did() );
	}

	/**
	 * @covers ::get_atproto_did
	 */
	public function test_get_atproto_did_returns_empty_string_for_invalid_did() {
		add_option( 'atproto_did', 'invalid' );

		$this->assertSame( '', get_atproto_did() );
	}
}
