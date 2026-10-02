<?php

/**
 * @group comment
 * @covers ::pingback
 */
class Tests_Comment_Pingback extends WP_UnitTestCase {

	protected static $post_id;
	protected $response = array();

	public function set_up() {
		parent::set_up();

		add_filter( 'pre_http_request', array( $this, 'request_response' ) );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'request_response' ) );
		parent::tear_down();
	}

	public function test_pingback() {
		$content = <<<HTML
<a href="http://example.org">test</a>
<a href="http://example1.org/test">test</a>
<a href="http://example3.org/">test</a>
HTML;

		$body = <<<BODY
			<a rel="pingback" href="https://example1.org/test/pingback">test</a>
BODY;

		$this->response = array(
			'body'     => $body,
			'response' => array( 'code' => 200 ),
		);

		self::$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$post = get_post( self::$post_id );
		$this->assertSame( array( 'http://example1.org/test' => false ), pingback( $post->post_content, self::$post_id ) );
	}

	public function test_pingback_no_ping_back() {
		$content = <<<HTML
<a href="http://example.org">test</a>
<a href="http://example1.org/test">test</a>
<a href="http://example3.org/">test</a>
HTML;

		$body = <<<BODY
			<a href="https://example1.org/test">test</a>
BODY;

		$this->response = array(
			'body'     => $body,
			'response' => array( 'code' => 200 ),
		);

		self::$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$post = get_post( self::$post_id );
		$this->assertSame( array(), pingback( $post->post_content, self::$post_id ) );
	}

	public function test_pingback_error_response() {
		$content = <<<HTML
<a href="http://example.org">test</a>
<a href="http://example1.org/test">test</a>
<a href="http://example3.org/">test</a>
HTML;

		$this->response = new WP_Error();

		self::$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$post = get_post( self::$post_id );
		$this->assertSame( array(), pingback( $post->post_content, self::$post_id ) );
	}

	/**
	 * Tests that a pingback the remote server reports as already registered is still recorded.
	 *
	 * @ticket 66160
	 */
	public function test_pingback_records_already_registered_ping(): void {
		$content = '<a href="http://example1.org/test">test</a>';

		// The discovery request finds the header; the XML-RPC request then receives this fault.
		$this->response = array(
			'headers'  => array( 'X-Pingback' => 'https://example1.org/xmlrpc.php' ),
			'body'     => '<?xml version="1.0"?><methodResponse><fault><value><struct><member><name>faultCode</name><value><int>48</int></value></member><member><name>faultString</name><value><string>The pingback has already been registered.</string></value></member></struct></value></fault></methodResponse>',
			'response' => array( 'code' => 200 ),
		);

		self::$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$post = get_post( self::$post_id );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( array( 'http://example1.org/test' => false ), pingback( $post->post_content, $post->ID ) );
		$this->assertContains( 'http://example1.org/test', (array) get_pung( $post->ID ), 'The already registered pingback should be recorded on the post.' );
	}

	public function request_response() {
		return $this->response;
	}
}
