<?php
/**
 * Unit tests covering WP_REST_Block_Directory_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_REST_Block_Directory_Controller_Test extends WP_Test_REST_Controller_Testcase {

	/**
	 * Administrator user id.
	 *
	 * @since 5.5.0
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Set up class test fixtures.
	 *
	 * @since 5.5.0
	 *
	 * @param WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
	}

	/**
	 * @ticket 50321
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/block-directory/search', $routes );
	}

	/**
	 * @ticket 50321
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/block-directory/search' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @ticket 50321
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );
		$this->mock_remote_request(
			array(
				'body' => '{"info":{"page":1,"pages":0,"results":0},"plugins":[]}',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/block-directory/search' );
		$request->set_query_params( array( 'term' => 'foo' ) );

		$result = rest_do_request( $request );
		$this->assertNotWPError( $result->as_error() );
		$this->assertSame( 200, $result->status );
	}

	/**
	 * @ticket 50321
	 */
	public function test_get_items_wdotorg_unavailable() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/block-directory/search' );
		$request->set_query_params( array( 'term' => 'foo' ) );

		$this->prevent_requests_to_host( 'api.wordpress.org' );

		$this->expectWarning();
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'plugins_api_failed', $response, 500 );
	}

	/**
	 * @ticket 50321
	 */
	public function test_get_items_logged_out() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/block-directory/search' );
		$request->set_query_params( array( 'term' => 'foo' ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_block_directory_cannot_view', $response );
	}

	/**
	 * @ticket 50321
	 */
	public function test_get_items_no_results() {
		wp_set_current_user( self::$admin_id );
		$this->mock_remote_request(
			array(
				'body' => '{"info":{"page":1,"pages":0,"results":0},"plugins":[]}',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/block-directory/search' );
		$request->set_query_params( array( 'term' => '0c4549ee68f24eaaed46a49dc983ecde' ) );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		// Should produce a 200 status with an empty array.
		$this->assertSame( 200, $response->status );
		$this->assertSame( array(), $data );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// Controller does not implement get_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_create_item() {
		// Controller does not implement create_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_update_item() {
		// Controller does not implement update_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Controller does not implement delete_item().
	}

	/**
	 * @ticket 50321
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$admin_id );

		$controller = new WP_REST_Block_Directory_Controller();

		$plugin  = $this->get_mock_plugin();
		$request = new WP_REST_Request( 'GET', '/wp/v2/block-directory/search' );
		$request->set_query_params( array( 'term' => 'block' ) );

		$response = $controller->prepare_item_for_response( $plugin, $request );

		$expected = array(
			'name'                => 'sortabrilliant/guidepost',
			'title'               => 'Guidepost',
			'description'         => 'A guidepost gives you directions. It lets you know where you’re going. It gives you a preview of what’s to come.',
			'id'                  => 'guidepost',
			'rating'              => 4.3,
			'rating_count'        => 90,
			'active_installs'     => 100,
			'author_block_rating' => 0,
			'author_block_count'  => 1,
			'author'              => 'sorta brilliant',
			'icon'                => 'https://ps.w.org/guidepost/assets/icon-128x128.jpg?rev=2235512',
			'last_updated'        => gmdate( 'Y-m-d\TH:i:s', strtotime( $plugin['last_updated'] ) ),
			'humanized_updated'   => sprintf( '%s ago', human_time_diff( strtotime( $plugin['last_updated'] ) ) ),
		);

		$this->assertSame( $expected, $response->get_data() );
	}

	/**
	 * @ticket 50321
	 */
	public function test_get_item_schema() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/block-directory/search' );
		$request->set_query_params( array( 'term' => 'foo' ) );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		// Check endpoints
		$this->assertSame( array( 'GET' ), $data['endpoints'][0]['methods'] );
		$this->assertTrue( $data['endpoints'][0]['args']['term']['required'] );

		$properties = $data['schema']['properties'];

		$this->assertCount( 13, $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'rating', $properties );
		$this->assertArrayHasKey( 'rating_count', $properties );
		$this->assertArrayHasKey( 'active_installs', $properties );
		$this->assertArrayHasKey( 'author_block_rating', $properties );
		$this->assertArrayHasKey( 'author_block_count', $properties );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'icon', $properties );
		$this->assertArrayHasKey( 'last_updated', $properties );
		$this->assertArrayHasKey( 'humanized_updated', $properties );
	}

	/**
	 * @ticket 53621
	 */
	public function test_get_items_response_conforms_to_schema() {
		wp_set_current_user( self::$admin_id );
		$plugin = $this->get_mock_plugin();

		// Fetch the block directory schema.
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/block-directory/search' );
		$schema  = rest_get_server()->dispatch( $request )->get_data()['schema'];

		add_filter(
			'plugins_api',
			static function () use ( $plugin ) {
				return (object) array(
					'info'    =>
						array(
							'page'    => 1,
							'pages'   => 1,
							'results' => 1,
						),
					'plugins' => array(
						$plugin,
					),
				);
			}
		);

		// Fetch a block plugin.
		$request = new WP_REST_Request( 'GET', '/wp/v2/block-directory/search' );
		$request->set_query_params( array( 'term' => 'cache' ) );

		$result = rest_get_server()->dispatch( $request );
		$data   = $result->get_data();

		$valid = rest_validate_value_from_schema( $data[0], $schema );

		$this->assertNotWPError( $valid );
	}

	/**
	 * Simulate a network failure on outbound http requests to a given hostname.
	 *
	 * @since 5.5.0
	 *
	 * @param string $blocked_host The host to block connections to.
	 */
	private function prevent_requests_to_host( $blocked_host = 'api.wordpress.org' ) {
		add_filter(
			'pre_http_request',
			static function ( $return, $args, $url ) use ( $blocked_host ) {
				if ( @parse_url( $url, PHP_URL_HOST ) === $blocked_host ) {
					return new WP_Error( 'plugins_api_failed', "An expected error occurred connecting to $blocked_host because of a unit test", "cURL error 7: Failed to connect to $blocked_host port 80: Connection refused" );

				}

				return $return;
			},
			10,
			3
		);
	}

	/**
	 * Gets an example of the data returned from the {@see plugins_api()} for a block.
	 *
	 * @since 5.5.0
	 *
	 * @return array
	 */
	private function get_mock_plugin() {
		return array(
			'name'                     => 'Guidepost',
			'slug'                     => 'guidepost',
			'version'                  => '1.2.1',
			'author'                   => '<a href="https://sortabrilliant.com">sorta brilliant</a>',
			'author_profile'           => 'https://profiles.wordpress.org/sortabrilliant',
			'requires'                 => '5.0',
			'tested'                   => '5.4.0',
			'requires_php'             => '5.6',
			'rating'                   => 86,
			'ratings'                  => array(
				5 => 50,
				4 => 25,
				3 => 7,
				2 => 5,
				1 => 3,
			),
			'num_ratings'              => 90,
			'support_threads'          => 1,
			'support_threads_resolved' => 0,
			'active_installs'          => 100,
			'downloaded'               => 1112,
			'last_updated'             => '2020-03-23 5:13am GMT',
			'added'                    => '2020-01-29',
			'homepage'                 => 'https://sortabrilliant.com/guidepost/',
			'description'              => '<p>A guidepost gives you directions. It lets you know where you’re going. It gives you a preview of what’s to come. How does it work? Guideposts are magic, no they really are.</p>',
			'short_description'        => 'A guidepost gives you directions. It lets you know where you’re going. It gives you a preview of what’s to come.',
			'download_link'            => 'https://downloads.wordpress.org/plugin/guidepost.1.2.1.zip',
			'tags'                     => array(
				'block'   => 'block',
				'heading' => 'heading',
				'style'   => 'style',
			),
			'donate_link'              => '',
			'icons'                    => array(
				'1x' => 'https://ps.w.org/guidepost/assets/icon-128x128.jpg?rev=2235512',
				'2x' => 'https://ps.w.org/guidepost/assets/icon-256x256.jpg?rev=2235512',
			),
			'blocks'                   => array(
				'sortabrilliant/guidepost' => array(
					'name'  => 'sortabrilliant/guidepost',
					'title' => 'Guidepost',
				),
			),
			'block_assets'             => array(
				0 => '/tags/1.2.1/build/index.js',
				1 => '/tags/1.2.1/build/guidepost-editor.css',
				2 => '/tags/1.2.1/build/guidepost-style.css',
				3 => '/tags/1.2.1/build/guidepost-theme.js',
			),
			'author_block_count'       => 1,
			'author_block_rating'      => 0,
		);
	}

	/**
	 * Mocks the remote request via `'pre_http_request'` filter by
	 * returning the expected response.
	 *
	 * @since 5.9.0
	 *
	 * @param array $expected Expected response, which is merged with the default response.
	 */
	private function mock_remote_request( array $expected ) {
		add_filter(
			'pre_http_request',
			static function() use ( $expected ) {
				$default = array(
					'headers'  => array(),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => '',
					'cookies'  => array(),
					'filename' => null,
				);
				return array_merge( $default, $expected );
			}
		);
	}
}
