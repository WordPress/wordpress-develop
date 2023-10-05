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
	}

	/**
	 * Tear down each test method.
	 */
	public function tear_down() {
		parent::tear_down();

		// Reset $collections static property of WP_Font_Library class.
		$reflection = new ReflectionClass( 'WP_Font_Library' );
		$property   = $reflection->getProperty( 'collections' );
		$property->setAccessible( true );
		$property->setValue( null, array() );

		// Clean up the /fonts directory.
		foreach ( $this->files_in_dir( static::$fonts_dir ) as $file ) {
			@unlink( $file );
		}
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
	public function test_get_items_with_no_collection_registered() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-collections' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
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
