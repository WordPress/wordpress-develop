<?php
/**
 * Test WP_REST_Block_Directory_Controller_Test()
 *
 * @package Gutenberg
 * phpcs:disable
 */
class WP_REST_Block_Directory_Controller_Test extends WP_UnitTestCase {
	protected $controller = null;

	function setUp() {
		parent::setUp();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server;
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$this->controller = new WP_REST_Block_Directory_Controller();
		$this->controller->register_routes();

	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/v2/block-directory/search', $routes );
		$this->assertArrayHasKey( '/wp/v2/block-directory/install', $routes );
		$this->assertArrayHasKey( '/wp/v2/block-directory/uninstall', $routes );
	}

	/**
	 * Tests that an error is returned if the block plugin slug is not provided
	 */
	function test_should_throw_no_slug_error() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/block-directory/install', [] );

		$result = $this->controller->install_block( $request );
		$this->assertWPError( $result, 'Returns an error when a slug isn\'t passed' );
		$this->assertTrue( array_key_exists( 'plugins_api_failed', $result->errors ), 'Returns the correct error key' );
	}

	/**
	 * Tests that the search endpoint does not return an error
	 */
	function test_simple_search() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/block-directory/search' );
		$request->set_query_params( array( 'term' => 'foo' ) );

		$result = $this->controller->get_items( $request );
		$this->assertNotWPError( $result );
		$this->assertEquals( 200, $result->status );
	}

	/**
	 * Simulate a network failure on outbound http requests to a given hostname.
	 */
	function prevent_requests_to_host( $blocked_host = 'api.wordpress.org' ) {
		// apply_filters( 'pre_http_request', false, $parsed_args, $url );
		add_filter( 'pre_http_request', function( $return, $args, $url ) use ( $blocked_host ) {
			if ( @parse_url( $url, PHP_URL_HOST ) === $blocked_host ) {
				return new WP_Error( 'plugins_api_failed', "An expected error occurred connecting to $blocked_host because of a unit test", "cURL error 7: Failed to connect to $blocked_host port 80: Connection refused" );

			}
			return $return;
		}, 10, 3 );
	}

	/**
	 * Tests that the search endpoint returns WP_Error when the server is unreachable.
	 */
	function test_search_unreachable() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/block-directory/search' );
		$request->set_query_params( array( 'term' => 'foo' ) );

		$this->prevent_requests_to_host( 'api.wordpress.org' );

		$result = $this->controller->get_items( $request );
		$this->assertWPError( $result );
		$this->assertTrue( array_key_exists( 'plugins_api_failed', $result->errors ), 'Returns the correct error key' );

	}

	/**
	 * Should fail with a permission error if requesting user is not logged in.
	 */
	function test_simple_search_no_perms() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-directory/search' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( $data['code'], 'rest_user_cannot_view' );
	}

	/**
	 * Make sure a search with the right permissions returns something.
	 */
	function test_simple_search_with_perms() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// This will hit the live API. We're searching for `block` which should definitely return at least one result.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-directory/search' );
		$request->set_query_params( array( 'term' => 'block' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->status );
		// At least one result
		$this->assertGreaterThanOrEqual( 1, count( $data ) );
		// Each result should be an object with important attributes set
		foreach ( $data as $plugin ) {
			$this->assertObjectHasAttribute( 'name', $plugin );
			$this->assertObjectHasAttribute( 'title', $plugin );
			$this->assertObjectHasAttribute( 'id', $plugin );
			$this->assertObjectHasAttribute( 'author_block_rating', $plugin );
			$this->assertObjectHasAttribute( 'assets', $plugin );
			$this->assertObjectHasAttribute( 'humanized_updated', $plugin );
		}
	}

}
