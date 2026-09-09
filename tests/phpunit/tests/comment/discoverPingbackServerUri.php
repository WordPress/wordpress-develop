<?php

/**
 * @group comment
 * @covers ::discover_pingback_server_uri
 *
 * @ticket 31384
 */
class Tests_Comment_DiscoverPingbackServerUri extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ) );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http_request' ) );
		parent::tear_down();
	}

	public function mock_http_request() {
		return array(
			'headers'  => array(),
			'body'     => '<link rel="pingback" href="https://example.com/pingback" />',
			'response' => array( 'code' => 200 ),
			'cookies'  => array(),
		);
	}

	/**
	 * @ticket 31384
	 */
	public function test_discovers_pingback_uri_for_schemeless_url() {
		$result = discover_pingback_server_uri( '//example.com/post' );

		$this->assertSame( 'https://example.com/pingback', $result );
	}
}
