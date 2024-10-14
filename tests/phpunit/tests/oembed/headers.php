<?php

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @group oembed
 * @group oembed-headers
 * @group xdebug
 *
 * @covers WP_REST_Request
 * @covers WP_REST_Server::dispatch
 */
class Tests_oEmbed_HTTP_Headers extends WP_UnitTestCase {

	/**
	 * @requires function xdebug_get_headers
	 */
	public function test_rest_pre_serve_request_headers() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Hello World',
			)
		);

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_param( 'url', get_permalink( $post->ID ) );
		$request->set_param( 'format', 'xml' );

		$server   = new WP_REST_Server();
		$response = $server->dispatch( $request );
		$output   = get_echo( '_oembed_rest_pre_serve_request', array( true, $response, $request, $server ) );

		$this->assertNotEmpty( $output );

		$headers = xdebug_get_headers();

		$this->assertContains( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), $headers );
	}
}
