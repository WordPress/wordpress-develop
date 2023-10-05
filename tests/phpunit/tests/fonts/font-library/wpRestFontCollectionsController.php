<?php
/**
 * Unit tests covering WP_REST_Font_Collections_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class Tests_Fonts_WpRestFontCollectionsController extends WP_Test_REST_TestCase {

	/**
	 * Fonts directory (in uploads).
	 *
	 * @var string
	 */
	protected static $fonts_dir;

	public function set_up() {
		parent::set_up();

		static::$fonts_dir = WP_Font_Library::get_fonts_dir();

		// Create a user with administrator role.
		$admin_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );


		// Mock font collection data file.
		$mock_file = wp_tempnam( 'one-collection-' );
		file_put_contents( $mock_file, '{"this is mock data":true}' );
		// Mock the wp_remote_request() function.
		add_filter( 'pre_http_request', array( $this, 'mock_request' ), 10, 3 );

		$config_with_file = array(
			'id'          => 'one-collection',
			'name'        => 'One Font Collection',
			'description' => 'Demo about how to a font collection to your WordPress Font Library.',
			'src'         => $mock_file,
		);
		wp_register_font_collection( $config_with_file );

		$config_with_url = array(
			'id'          => 'collection-with-url',
			'name'        => 'Another Font Collection',
			'description' => 'Demo about how to a font collection to your WordPress Font Library.',
			'src'         => 'https://wordpress.org/fonts/mock-font-collection.json',
		);

		wp_register_font_collection( $config_with_url );

		$config_with_non_existing_file = array(
			'id'          => 'collection-with-non-existing-file',
			'name'        => 'Another Font Collection',
			'description' => 'Demo about how to a font collection to your WordPress Font Library.',
			'src'         => '/home/non-existing-file.json',
		);

		wp_register_font_collection( $config_with_non_existing_file );

		$config_with_non_existing_url = array(
			'id'          => 'collection-with-non-existing-url',
			'name'        => 'Another Font Collection',
			'description' => 'Demo about how to a font collection to your WordPress Font Library.',
			'src'         => 'https://non-existing-url-1234x.com.ar/fake-path/missing-file.json',
		);

		wp_register_font_collection( $config_with_non_existing_url );
	}

	/**
	 * Tear down each test method.
	 */
	public function tear_down() {

		// Remove the mock to not affect other tests.
		remove_filter( 'pre_http_request', array( $this, 'mock_request' ) );

		// Reset $collections static property of WP_Font_Library class.
		$reflection = new ReflectionClass( 'WP_Font_Library' );
		$property   = $reflection->getProperty( 'collections' );
		$property->setAccessible( true );
		$property->setValue( null, array() );

		// Clean up the /fonts directory.
		foreach ( $this->files_in_dir( static::$fonts_dir ) as $file ) {
			@unlink( $file );
		}

		parent::tear_down();
	}

	public function mock_request( $preempt, $args, $url ) {
		// Check if it's the URL you want to mock.
		if ( 'https://wordpress.org/fonts/mock-font-collection.json' === $url ) {

			// Mock the response body.
			$mock_collection_data = array(
				'fontFamilies' => 'mock',
				'categories'   => 'mock',
			);

			return array(
				'body'     => json_encode( $mock_collection_data ),
				'response' => array(
					'code' => 200,
				),
			);
		}

		// For any other URL, return false which ensures the request is made as usual (or you can return other mock data).
		return false;
	}

	/**
	 * @ticket 59166
	 * @covers WP_REST_Font_Collections_Controller::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey(
			'/wp/v2/font-collections/(?P<id>[\/\w-]+)',
			$routes,
			"Font collections route doesn't exist."
		);
		$this->assertArrayHasKey( 'GET', $routes['/wp/v2/font-collections'][0]['methods'], 'The REST server does not have the GET method initialized for font collections.' );
		$this->assertArrayHasKey( 'GET', $routes['/wp/v2/font-collections/(?P<id>[\/\w-]+)'][0]['methods'], 'The REST server does not have the GET method initialized for a specific font collection.' );
		$this->assertCount( 1, $routes['/wp/v2/font-collections'], 'The REST server does not have the font collections path initialized.' );
		$this->assertCount( 1, $routes['/wp/v2/font-collections/(?P<id>[\/\w-]+)'], 'The REST server does not have the path initialized for a specific font collection.' );
	}
	public function test_get_items() {
		// Mock font collection data file.
		$mock_file = wp_tempnam( 'my-collection-data-' );
		file_put_contents( $mock_file, '{"this is mock data":true}' );

		// Add a font collection.
		$config = array(
			'id'          => 'my-font-collection',
			'name'        => 'My Font Collection',
			'description' => 'Demo about how to a font collection to your WordPress Font Library.',
			'src'         => $mock_file,
		);
		wp_register_font_collection( $config );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-collections' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status(), 'The response status is not 200.' );
		$this->assertCount( 1, $data, 'The response data is not an array with one element.' );
		$this->assertArrayHasKey( 'id', $data[0], 'The response data does not have the key with the collection ID.' );
		$this->assertArrayHasKey( 'name', $data[0], 'The response data does not have the key with the collection name.' );
	}

	public function test_get_font_collection_from_file() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-collections/one-collection' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status(), 'The response status is not 200.' );
		$this->assertArrayHasKey( 'data', $data, 'The response data does not have the key with the file data.' );
		$this->assertSame( array ( 'this_is_mock_data' => true, ), $data['data'], 'The response data does not have the expected file data.' );
	}

	public function test_get_items_with_no_collection_registered() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-collections' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	public function test_get_font_collection_from_url() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-collections/collection-with-url' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status(), 'The response status is not 200.' );
		$this->assertArrayHasKey( 'data', $data, 'The response data does not have the key with the file data.' );
	}

	public function test_get_non_existing_collection_should_return_404() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-collections/non-existing-collection-id' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_get_non_existing_file_should_return_500() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-collections/collection-with-non-existing-file' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 500, $response->get_status() );
	}

	public function test_get_non_existing_url_should_return_500() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-collections/collection-with-non-existing-url' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 500, $response->get_status() );
	}



	public function test_context_param() {
	}

	public function test_get_item() {
	}

	public function test_create_item() {

	}

	public function test_update_item() {
	}

	public function test_delete_item() {
	}

	public function test_prepare_item() {
	}

	public function test_get_item_schema() {
	}
}
