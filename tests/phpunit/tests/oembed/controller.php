<?php

/**
 * @group oembed
 * @group restapi
 */
class Test_oEmbed_Controller extends WP_UnitTestCase {
	/**
	 * @var WP_REST_Server
	 */
	protected $server;
	protected static $editor;
	protected static $administrator;
	protected static $subscriber;
	const YOUTUBE_VIDEO_ID       = 'OQSNhk5ICTI';
	const INVALID_OEMBED_URL     = 'https://www.notreallyanoembedprovider.com/watch?v=awesome-cat-video';
	const UNTRUSTED_PROVIDER_URL = 'https://www.untrustedprovider.com';

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$subscriber    = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		self::$editor        = $factory->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'editor@example.com',
			)
		);
		self::$administrator = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'administrator@example.com',
			)
		);

		// `get_post_embed_html()` assumes `wp-includes/js/wp-embed.js` is present:
		self::touch( ABSPATH . WPINC . '/js/wp-embed.js' );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$subscriber );
		self::delete_user( self::$editor );
	}

	public function set_up() {
		parent::set_up();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = new Spy_REST_Server;
		do_action( 'rest_api_init', $wp_rest_server );

		add_filter( 'pre_http_request', array( $this, 'mock_embed_request' ), 10, 3 );
		add_filter( 'oembed_result', array( $this, 'filter_oembed_result' ), 10, 3 );
		$this->request_count = 0;

		$this->oembed_result_filter_count = 0;
	}

	public function tear_down() {
		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Count of the number of requests attempted.
	 *
	 * @var int
	 */
	public $request_count = 0;

	/**
	 * Count of the number of times the oembed_result filter was called.
	 *
	 * @var int
	 */
	public $oembed_result_filter_count = 0;

	/**
	 * Intercept oEmbed requests and mock responses.
	 *
	 * @param mixed  $preempt Whether to preempt an HTTP request's return value. Default false.
	 * @param mixed  $r       HTTP request arguments.
	 * @param string $url     The request URL.
	 * @return array Response data.
	 */
	public function mock_embed_request( $preempt, $r, $url ) {
		unset( $preempt, $r );

		$parsed_url = wp_parse_url( $url );
		$query      = isset( $parsed_url['query'] ) ? $parsed_url['query'] : '';
		parse_str( $query, $query_params );
		$this->request_count += 1;

		// Mock request to YouTube Embed.
		if ( ! empty( $query_params['url'] ) && false !== strpos( $query_params['url'], '?v=' . self::YOUTUBE_VIDEO_ID ) ) {
			return array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'version'          => '1.0',
						'type'             => 'video',
						'provider_name'    => 'YouTube',
						'provider_url'     => 'https://www.youtube.com',
						'thumbnail_width'  => $query_params['maxwidth'],
						'width'            => $query_params['maxwidth'],
						'thumbnail_height' => $query_params['maxheight'],
						'height'           => $query_params['maxheight'],
						'html'             => '<b>Unfiltered</b><iframe width="' . $query_params['maxwidth'] . '" height="' . $query_params['maxheight'] . '" src="https://www.youtube.com/embed/' . self::YOUTUBE_VIDEO_ID . '?feature=oembed" frameborder="0" allowfullscreen></iframe>',
						'author_name'      => 'Yosemitebear62',
						'thumbnail_url'    => 'https://i.ytimg.com/vi/' . self::YOUTUBE_VIDEO_ID . '/hqdefault.jpg',
						'title'            => 'Yosemitebear Mountain Double Rainbow 1-8-10',
					)
				),
			);
		}

		if ( self::UNTRUSTED_PROVIDER_URL === $url ) {
			return array(
				'response' => array(
					'code' => 200,
				),
				'body'     => '<html><head><link rel="alternate" type="application/json+oembed" href="' . self::UNTRUSTED_PROVIDER_URL . '" /></head><body></body></html>',
			);
		}

		if ( ! empty( $query_params['url'] ) && false !== strpos( $query_params['url'], self::UNTRUSTED_PROVIDER_URL ) ) {
			return array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'version'       => '1.0',
						'type'          => 'rich',
						'provider_name' => 'Untrusted',
						'provider_url'  => self::UNTRUSTED_PROVIDER_URL,
						'html'          => '<b>Filtered</b><a href="">Unfiltered</a>',
						'author_name'   => 'Untrusted Embed Author',
						'title'         => 'Untrusted Embed',
					)
				),
			);
		}

		return array(
			'response' => array(
				'code' => 404,
			),
		);
	}

	/**
	 * Filters 'oembed_result' to ensure correct type.
	 *
	 * @param string|false $data The returned oEmbed HTML.
	 * @param string       $url  URL of the content to be embedded.
	 * @param array        $args Optional arguments, usually passed from a shortcode.
	 * @return string
	 */
	public function filter_oembed_result( $data, $url, $args ) {
		if ( ! is_string( $data ) && false !== $data ) {
			$this->fail( 'Unexpected type for $data.' );
		}
		$this->assertIsString( $url );
		$this->assertIsArray( $args );
		$this->oembed_result_filter_count++;
		return $data;
	}

	function test_wp_oembed_ensure_format() {
		$this->assertSame( 'json', wp_oembed_ensure_format( 'json' ) );
		$this->assertSame( 'xml', wp_oembed_ensure_format( 'xml' ) );
		$this->assertSame( 'json', wp_oembed_ensure_format( 123 ) );
		$this->assertSame( 'json', wp_oembed_ensure_format( 'random' ) );
		$this->assertSame( 'json', wp_oembed_ensure_format( array() ) );
	}

	function test_oembed_create_xml() {
		$actual = _oembed_create_xml(
			array(
				'foo'  => 'bar',
				'bar'  => 'baz',
				'ping' => 'pong',
			)
		);

		$expected = '<oembed><foo>bar</foo><bar>baz</bar><ping>pong</ping></oembed>';

		$this->assertStringEndsWith( $expected, trim( $actual ) );

		$actual = _oembed_create_xml(
			array(
				'foo'  => array(
					'bar' => 'baz',
				),
				'ping' => 'pong',
			)
		);

		$expected = '<oembed><foo><bar>baz</bar></foo><ping>pong</ping></oembed>';

		$this->assertStringEndsWith( $expected, trim( $actual ) );

		$actual = _oembed_create_xml(
			array(
				'foo'   => array(
					'bar' => array(
						'ping' => 'pong',
					),
				),
				'hello' => 'world',
			)
		);

		$expected = '<oembed><foo><bar><ping>pong</ping></bar></foo><hello>world</hello></oembed>';

		$this->assertStringEndsWith( $expected, trim( $actual ) );

		$actual = _oembed_create_xml(
			array(
				array(
					'foo' => array(
						'bar',
					),
				),
				'helloworld',
			)
		);

		$expected = '<oembed><oembed><foo><oembed>bar</oembed></foo></oembed><oembed>helloworld</oembed></oembed>';

		$this->assertStringEndsWith( $expected, trim( $actual ) );
	}

	public function test_route_availability() {
		// Check the route was registered correctly.
		$filtered_routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/oembed/1.0/embed', $filtered_routes );
		$route = $filtered_routes['/oembed/1.0/embed'];
		$this->assertCount( 1, $route );
		$this->assertArrayHasKey( 'callback', $route[0] );
		$this->assertArrayHasKey( 'methods', $route[0] );
		$this->assertArrayHasKey( 'args', $route[0] );

		// Check proxy route registration.
		$this->assertArrayHasKey( '/oembed/1.0/proxy', $filtered_routes );
		$proxy_route = $filtered_routes['/oembed/1.0/proxy'];
		$this->assertCount( 1, $proxy_route );
		$this->assertArrayHasKey( 'callback', $proxy_route[0] );
		$this->assertArrayHasKey( 'permission_callback', $proxy_route[0] );
		$this->assertArrayHasKey( 'methods', $proxy_route[0] );
		$this->assertArrayHasKey( 'args', $proxy_route[0] );
	}

	function test_request_with_wrong_method() {
		$request = new WP_REST_Request( 'POST', '/oembed/1.0/embed' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 'rest_no_route', $data['code'] );
	}

	function test_request_without_url_param() {
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 'rest_missing_callback_param', $data['code'] );
		$this->assertSame( 'url', $data['data']['params'][0] );
	}

	function test_request_with_bad_url() {
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_param( 'url', 'http://google.com/' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 'oembed_invalid_url', $data['code'] );
	}

	function test_request_invalid_format() {
		$post_id = $this->factory()->post->create();

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_param( 'url', get_permalink( $post_id ) );
		$request->set_param( 'format', 'random' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );
	}

	function test_request_json() {
		$user = self::factory()->user->create_and_get(
			array(
				'display_name' => 'John Doe',
			)
		);
		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => $user->ID,
				'post_title'  => 'Hello World',
			)
		);

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_param( 'url', get_permalink( $post->ID ) );
		$request->set_param( 'maxwidth', 400 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		$this->assertArrayHasKey( 'version', $data );
		$this->assertArrayHasKey( 'provider_name', $data );
		$this->assertArrayHasKey( 'provider_url', $data );
		$this->assertArrayHasKey( 'author_name', $data );
		$this->assertArrayHasKey( 'author_url', $data );
		$this->assertArrayHasKey( 'title', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'width', $data );

		$this->assertSame( '1.0', $data['version'] );
		$this->assertSame( get_bloginfo( 'name' ), $data['provider_name'] );
		$this->assertSame( home_url(), $data['provider_url'] );
		$this->assertSame( $user->display_name, $data['author_name'] );
		$this->assertSame( get_author_posts_url( $user->ID, $user->user_nicename ), $data['author_url'] );
		$this->assertSame( $post->post_title, $data['title'] );
		$this->assertSame( 'rich', $data['type'] );
		$this->assertTrue( $data['width'] <= $request['maxwidth'] );
	}

	/**
	 * @ticket 34971
	 */
	function test_request_static_front_page() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Front page',
				'post_type'  => 'page',
			)
		);

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $post->ID );

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_param( 'url', home_url() );
		$request->set_param( 'maxwidth', 400 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		$this->assertArrayHasKey( 'version', $data );
		$this->assertArrayHasKey( 'provider_name', $data );
		$this->assertArrayHasKey( 'provider_url', $data );
		$this->assertArrayHasKey( 'author_name', $data );
		$this->assertArrayHasKey( 'author_url', $data );
		$this->assertArrayHasKey( 'title', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'width', $data );

		$this->assertSame( '1.0', $data['version'] );
		$this->assertSame( get_bloginfo( 'name' ), $data['provider_name'] );
		$this->assertSame( home_url(), $data['provider_url'] );
		$this->assertSame( get_bloginfo( 'name' ), $data['author_name'] );
		$this->assertSame( home_url(), $data['author_url'] );
		$this->assertSame( $post->post_title, $data['title'] );
		$this->assertSame( 'rich', $data['type'] );
		$this->assertTrue( $data['width'] <= $request['maxwidth'] );

		update_option( 'show_on_front', 'posts' );
	}

	function test_request_xml() {
		$user = self::factory()->user->create_and_get(
			array(
				'display_name' => 'John Doe',
			)
		);
		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => $user->ID,
				'post_title'  => 'Hello World',
			)
		);

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_param( 'url', get_permalink( $post->ID ) );
		$request->set_param( 'format', 'xml' );
		$request->set_param( 'maxwidth', 400 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		$this->assertArrayHasKey( 'version', $data );
		$this->assertArrayHasKey( 'provider_name', $data );
		$this->assertArrayHasKey( 'provider_url', $data );
		$this->assertArrayHasKey( 'author_name', $data );
		$this->assertArrayHasKey( 'author_url', $data );
		$this->assertArrayHasKey( 'title', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'width', $data );

		$this->assertSame( '1.0', $data['version'] );
		$this->assertSame( get_bloginfo( 'name' ), $data['provider_name'] );
		$this->assertSame( home_url(), $data['provider_url'] );
		$this->assertSame( $user->display_name, $data['author_name'] );
		$this->assertSame( get_author_posts_url( $user->ID, $user->user_nicename ), $data['author_url'] );
		$this->assertSame( $post->post_title, $data['title'] );
		$this->assertSame( 'rich', $data['type'] );
		$this->assertTrue( $data['width'] <= $request['maxwidth'] );
	}

	/**
	 * @group multisite
	 * @group ms-required
	 */
	function test_request_ms_child_in_root_blog() {
		$child = self::factory()->blog->create();
		switch_to_blog( $child );

		$post = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Hello Child Blog',
			)
		);

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_param( 'url', get_permalink( $post->ID ) );
		$request->set_param( 'maxwidth', 400 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		restore_current_blog();
	}

	function test_rest_pre_serve_request() {
		$user = $this->factory()->user->create_and_get(
			array(
				'display_name' => 'John Doe',
			)
		);
		$post = $this->factory()->post->create_and_get(
			array(
				'post_author' => $user->ID,
				'post_title'  => 'Hello World',
			)
		);

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_param( 'url', get_permalink( $post->ID ) );
		$request->set_param( 'format', 'xml' );

		$response = rest_get_server()->dispatch( $request );
		$output   = get_echo( '_oembed_rest_pre_serve_request', array( true, $response, $request, rest_get_server() ) );

		$xml = simplexml_load_string( $output );
		$this->assertInstanceOf( 'SimpleXMLElement', $xml );
	}

	function test_rest_pre_serve_request_wrong_format() {
		$post = $this->factory()->post->create_and_get();

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_param( 'url', get_permalink( $post->ID ) );
		$request->set_param( 'format', 'json' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( _oembed_rest_pre_serve_request( true, $response, $request, rest_get_server() ) );
	}

	function test_rest_pre_serve_request_wrong_method() {
		$post = $this->factory()->post->create_and_get();

		$request = new WP_REST_Request( 'HEAD', '/oembed/1.0/embed' );
		$request->set_param( 'url', get_permalink( $post->ID ) );
		$request->set_param( 'format', 'xml' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( _oembed_rest_pre_serve_request( true, $response, $request, rest_get_server() ) );
	}

	function test_get_oembed_endpoint_url() {
		$this->assertSame( home_url() . '/index.php?rest_route=/oembed/1.0/embed', get_oembed_endpoint_url() );
		$this->assertSame( home_url() . '/index.php?rest_route=/oembed/1.0/embed', get_oembed_endpoint_url( '', 'json' ) );
		$this->assertSame( home_url() . '/index.php?rest_route=/oembed/1.0/embed', get_oembed_endpoint_url( '', 'xml' ) );

		$post_id     = $this->factory()->post->create();
		$url         = get_permalink( $post_id );
		$url_encoded = urlencode( $url );

		$this->assertSame( home_url() . '/index.php?rest_route=%2Foembed%2F1.0%2Fembed&url=' . $url_encoded, get_oembed_endpoint_url( $url ) );
		$this->assertSame( home_url() . '/index.php?rest_route=%2Foembed%2F1.0%2Fembed&url=' . $url_encoded . '&format=xml', get_oembed_endpoint_url( $url, 'xml' ) );
	}

	function test_get_oembed_endpoint_url_pretty_permalinks() {
		update_option( 'permalink_structure', '/%postname%' );

		$this->assertSame( home_url() . '/wp-json/oembed/1.0/embed', get_oembed_endpoint_url() );
		$this->assertSame( home_url() . '/wp-json/oembed/1.0/embed', get_oembed_endpoint_url( '', 'xml' ) );

		$post_id     = $this->factory()->post->create();
		$url         = get_permalink( $post_id );
		$url_encoded = urlencode( $url );

		$this->assertSame( home_url() . '/wp-json/oembed/1.0/embed?url=' . $url_encoded, get_oembed_endpoint_url( $url ) );
		$this->assertSame( home_url() . '/wp-json/oembed/1.0/embed?url=' . $url_encoded . '&format=xml', get_oembed_endpoint_url( $url, 'xml' ) );

		update_option( 'permalink_structure', '' );
	}

	public function test_proxy_without_permission() {
		// Test without a login.
		$request  = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );

		// Test with a user that does not have edit_posts capability.
		wp_set_current_user( self::$subscriber );
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', self::INVALID_OEMBED_URL );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $data['code'], 'rest_forbidden' );
	}

	public function test_proxy_with_invalid_oembed_provider() {
		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', self::INVALID_OEMBED_URL );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'oembed_invalid_url', $data['code'] );
	}

	public function test_proxy_with_invalid_type() {
		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'type', 'xml' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_proxy_with_valid_oembed_provider() {
		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', 'https://www.youtube.com/watch?v=' . self::YOUTUBE_VIDEO_ID );
		$request->set_param( 'maxwidth', 456 );
		$request->set_param( 'maxheight', 789 );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $this->request_count );

		// Subsequent request is cached and so it should not cause a request.
		rest_get_server()->dispatch( $request );
		$this->assertSame( 1, $this->request_count );

		// Rest with another user should also be cached.
		wp_set_current_user( self::$administrator );
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', 'https://www.youtube.com/watch?v=' . self::YOUTUBE_VIDEO_ID );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'maxwidth', 456 );
		$request->set_param( 'maxheight', 789 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 1, $this->request_count );

		// Test data object.
		$data = $response->get_data();

		$this->assertNotEmpty( $data );
		$this->assertIsObject( $data );
		$this->assertSame( 'YouTube', $data->provider_name );
		$this->assertSame( 'https://i.ytimg.com/vi/' . self::YOUTUBE_VIDEO_ID . '/hqdefault.jpg', $data->thumbnail_url );
		$this->assertEquals( $data->width, $request['maxwidth'] );
		$this->assertEquals( $data->height, $request['maxheight'] );
	}

	/**
	 * @ticket 45447
	 *
	 * @see wp_maybe_load_embeds()
	 */
	public function test_proxy_with_classic_embed_provider() {
		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', 'https://www.youtube.com/embed/' . self::YOUTUBE_VIDEO_ID );
		$request->set_param( 'maxwidth', 456 );
		$request->set_param( 'maxheight', 789 );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $this->request_count );

		// Test data object.
		$data = $response->get_data();

		$this->assertNotEmpty( $data );
		$this->assertIsObject( $data );
		$this->assertIsString( $data->html );
		$this->assertIsArray( $data->scripts );
	}

	public function test_proxy_with_invalid_oembed_provider_no_discovery() {
		wp_set_current_user( self::$editor );

		// If discover is false for an unknown provider, no discovery request should take place.
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', self::INVALID_OEMBED_URL );
		$request->set_param( 'discover', false );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 0, $this->request_count );
	}

	public function test_proxy_with_invalid_oembed_provider_with_default_discover_param() {
		wp_set_current_user( self::$editor );

		// For an unknown provider, a discovery request should happen.
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', self::INVALID_OEMBED_URL );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 1, $this->request_count );
	}

	public function test_proxy_with_invalid_discover_param() {
		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', self::INVALID_OEMBED_URL );
		$request->set_param( 'discover', 'notaboolean' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $data['code'], 'rest_invalid_param' );
	}

	/**
	 * @ticket 45142
	 */
	function test_proxy_with_internal_url() {
		wp_set_current_user( self::$editor );

		$user = self::factory()->user->create_and_get(
			array(
				'display_name' => 'John Doe',
			)
		);
		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => $user->ID,
				'post_title'  => 'Hello World',
			)
		);

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', get_permalink( $post->ID ) );
		$request->set_param( 'maxwidth', 400 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$data = (array) $data;

		$this->assertNotEmpty( $data );

		$this->assertArrayHasKey( 'version', $data );
		$this->assertArrayHasKey( 'provider_name', $data );
		$this->assertArrayHasKey( 'provider_url', $data );
		$this->assertArrayHasKey( 'author_name', $data );
		$this->assertArrayHasKey( 'author_url', $data );
		$this->assertArrayHasKey( 'title', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'width', $data );

		$this->assertSame( '1.0', $data['version'] );
		$this->assertSame( get_bloginfo( 'name' ), $data['provider_name'] );
		$this->assertSame( home_url(), $data['provider_url'] );
		$this->assertSame( $user->display_name, $data['author_name'] );
		$this->assertSame( get_author_posts_url( $user->ID, $user->user_nicename ), $data['author_url'] );
		$this->assertSame( $post->post_title, $data['title'] );
		$this->assertSame( 'rich', $data['type'] );
		$this->assertTrue( $data['width'] <= $request['maxwidth'] );
	}

	/**
	 * @ticket 45142
	 */
	function test_proxy_with_static_front_page_url() {
		wp_set_current_user( self::$editor );

		$post = self::factory()->post->create_and_get(
			array(
				'post_title'  => 'Front page',
				'post_type'   => 'page',
				'post_author' => 0,
			)
		);

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $post->ID );

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', home_url() );
		$request->set_param( 'maxwidth', 400 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertIsObject( $data );

		$data = (array) $data;

		$this->assertNotEmpty( $data );

		$this->assertArrayHasKey( 'version', $data );
		$this->assertArrayHasKey( 'provider_name', $data );
		$this->assertArrayHasKey( 'provider_url', $data );
		$this->assertArrayHasKey( 'author_name', $data );
		$this->assertArrayHasKey( 'author_url', $data );
		$this->assertArrayHasKey( 'title', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'width', $data );

		$this->assertSame( '1.0', $data['version'] );
		$this->assertSame( get_bloginfo( 'name' ), $data['provider_name'] );
		$this->assertSame( home_url(), $data['provider_url'] );
		$this->assertSame( get_bloginfo( 'name' ), $data['author_name'] );
		$this->assertSame( home_url(), $data['author_url'] );
		$this->assertSame( $post->post_title, $data['title'] );
		$this->assertSame( 'rich', $data['type'] );
		$this->assertTrue( $data['width'] <= $request['maxwidth'] );

		update_option( 'show_on_front', 'posts' );
	}

	/**
	 * @ticket 45142
	 */
	public function test_proxy_filters_result_of_untrusted_oembed_provider() {
		wp_set_current_user( self::$editor );

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', self::UNTRUSTED_PROVIDER_URL );
		$request->set_param( 'maxwidth', 456 );
		$request->set_param( 'maxheight', 789 );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 1, $this->oembed_result_filter_count );
		$this->assertIsObject( $data );
		$this->assertSame( 'Untrusted', $data->provider_name );
		$this->assertSame( self::UNTRUSTED_PROVIDER_URL, $data->provider_url );
		$this->assertSame( 'rich', $data->type );
		$this->assertFalse( $data->html );
	}

	/**
	 * @ticket 45142
	 */
	public function test_proxy_does_not_filter_result_of_trusted_oembed_provider() {
		wp_set_current_user( self::$editor );

		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', 'https://www.youtube.com/watch?v=' . self::YOUTUBE_VIDEO_ID );
		$request->set_param( 'maxwidth', 456 );
		$request->set_param( 'maxheight', 789 );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 1, $this->oembed_result_filter_count );
		$this->assertIsObject( $data );

		$this->assertStringStartsWith( '<b>Unfiltered</b>', $data->html );
	}
}
