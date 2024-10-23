<?php
/**
 * Unit tests covering WP_REST_Search_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 *
 * @group restapi
 */
class WP_Test_REST_Search_Controller extends WP_Test_REST_Controller_Testcase {

	/**
	 * Posts with title 'my-footitle'.
	 *
	 * @var array
	 */
	private static $my_title_post_ids = array();

	/**
	 * Pages with title 'my-footitle'.
	 *
	 * @var array
	 */
	private static $my_title_page_ids = array();

	/**
	 * Posts with content 'my-foocontent'.
	 *
	 * @var array
	 */
	private static $my_content_post_ids = array();

	/**
	 * Attachments
	 *
	 * @var array
	 */
	private static $my_attachment_ids;

	/**
	 * Categories.
	 *
	 * @var int
	 */
	private static $my_category_id;

	/**
	 * Tags.
	 *
	 * @var int
	 */
	private static $my_tag_id;

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		add_theme_support( 'post-formats', array( 'aside' ) );

		self::$my_title_post_ids = $factory->post->create_many(
			4,
			array(
				'post_title' => 'my-footitle',
				'post_type'  => 'post',
			)
		);

		self::$my_title_page_ids = $factory->post->create_many(
			4,
			array(
				'post_title' => 'my-footitle',
				'post_type'  => 'page',
			)
		);

		self::$my_content_post_ids = $factory->post->create_many(
			6,
			array(
				'post_content' => 'my-foocontent',
			)
		);

		set_post_format( self::$my_title_post_ids[0], 'aside' );

		self::$my_category_id = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Test Category',
			)
		);

		self::$my_tag_id = $factory->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'Test Tag',
			)
		);

		self::$my_attachment_ids = $factory->attachment->create_many(
			4,
			array(
				'post_title' => 'my-footitle',
			)
		);
	}

	/**
	 * Delete our fake data after our tests run.
	 */
	public static function wpTearDownAfterClass() {
		remove_theme_support( 'post-formats' );

		$post_ids = array_merge(
			self::$my_title_post_ids,
			self::$my_title_page_ids,
			self::$my_content_post_ids
		);

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$term_ids = array(
			self::$my_category_id,
			self::$my_tag_id,
		);

		foreach ( $term_ids as $term_id ) {
			wp_delete_term( $term_id, true );
		}
	}

	/**
	 * Check that our routes get set up properly.
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/search', $routes );
		$this->assertCount( 1, $routes['/wp/v2/search'] );
	}

	/**
	 * Check the context parameter.
	 */
	public function test_context_param() {
		$response = $this->do_request_with_params( array(), 'OPTIONS' );
		$data     = $response->get_data();

		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * Search through all content.
	 */
	public function test_get_items() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array_merge(
				self::$my_title_post_ids,
				self::$my_title_page_ids,
				self::$my_content_post_ids
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Search through all content with a low limit.
	 */
	public function test_get_items_with_limit() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 3,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 3, $response->get_data() );
	}

	/**
	 * Search through posts of any post type.
	 */
	public function test_get_items_search_type_post() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'post',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array_merge(
				self::$my_title_post_ids,
				self::$my_title_page_ids,
				self::$my_content_post_ids
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Search through posts of post type 'post'.
	 */
	public function test_get_items_search_type_post_subtype_post() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'post',
				'subtype'  => 'post',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array_merge(
				self::$my_title_post_ids,
				self::$my_content_post_ids
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Search through posts of post type 'page'.
	 */
	public function test_get_items_search_type_post_subtype_page() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'post',
				'subtype'  => 'page',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			self::$my_title_page_ids,
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Search through an invalid type
	 */
	public function test_get_items_search_type_invalid() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'invalid',
			)
		);

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Search through posts of an invalid post type.
	 */
	public function test_get_items_search_type_post_subtype_invalid() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'post',
				'subtype'  => 'invalid',
			)
		);

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Search through posts and pages.
	 */
	public function test_get_items_search_posts_and_pages() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'post',
				'subtype'  => 'post,page',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array_merge(
				self::$my_title_post_ids,
				self::$my_title_page_ids,
				self::$my_content_post_ids
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Search through all that matches a 'footitle' search.
	 */
	public function test_get_items_search_for_footitle() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'search'   => 'footitle',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array_merge(
				self::$my_title_post_ids,
				self::$my_title_page_ids
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Search through all that matches a 'foocontent' search.
	 */
	public function test_get_items_search_for_foocontent() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'search'   => 'foocontent',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			self::$my_content_post_ids,
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * @ticket 55674
	 */
	public function test_get_items_search_prime_ids() {
		$action = new MockAction();
		add_filter( 'query', array( $action, 'filter' ), 10, 2 );

		$query_args = array(
			'per_page' => 100,
			'search'   => 'foocontent',
		);
		$response   = $this->do_request_with_params( $query_args );
		$this->assertSame( 200, $response->get_status(), 'Request Status Response is not 200.' );

		$ids = wp_list_pluck( $response->get_data(), 'id' );
		$this->assertSameSets( self::$my_content_post_ids, $ids, 'Query result posts ids do not match with expected ones.' );

		$args               = $action->get_args();
		$primed_query_found = false;
		foreach ( $args as $arg ) {
			// Primed query will use WHERE ID IN clause.
			if ( str_contains( $arg[0], 'WHERE ID IN (' . implode( ',', $ids ) ) ) {
				$primed_query_found = true;
				break;
			}
		}

		$this->assertTrue( $primed_query_found, 'Prime query was not executed.' );
	}

	/**
	 * Test retrieving a single item isn't possible.
	 */
	public function test_get_item() {
		/** The search controller does not allow getting individual item content */
		$request  = new WP_REST_Request( 'GET', '/wp/v2/search' . self::$my_title_post_ids[0] );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test creating an item isn't possible.
	 */
	public function test_create_item() {
		/** The search controller does not allow creating content */
		$request  = new WP_REST_Request( 'POST', '/wp/v2/search' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test updating an item isn't possible.
	 */
	public function test_update_item() {
		/** The search controller does not allow upading content */
		$request  = new WP_REST_Request( 'POST', '/wp/v2/search' . self::$my_title_post_ids[0] );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test deleting an item isn't possible.
	 */
	public function test_delete_item() {
		/** The search controller does not allow deleting content */
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/search' . self::$my_title_post_ids[0] );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test preparing the data contains the correct fields.
	 */
	public function test_prepare_item() {
		$response = $this->do_request_with_params();
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame(
			array(
				'id',
				'title',
				'url',
				'type',
				'subtype',
				'label',
				'_links',
			),
			array_keys( $data[0] )
		);
	}

	/**
	 * Test preparing the data with limited fields contains the correct fields.
	 */
	public function test_prepare_item_limit_fields() {
		if ( ! method_exists( 'WP_REST_Controller', 'get_fields_for_response' ) ) {
			$this->markTestSkipped( 'Limiting fields requires the WP_REST_Controller::get_fields_for_response() method.' );
		}

		$response = $this->do_request_with_params(
			array(
				'_fields' => 'id,title',
			)
		);
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame(
			array(
				'id',
				'title',
			),
			array_keys( $data[0] )
		);
	}

	/**
	 * Tests the item schema is correct.
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/search' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'url', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'subtype', $properties );
		$this->assertArrayHasKey( 'label', $properties );
	}

	/**
	 * Tests that non-public post types are not allowed.
	 */
	public function test_non_public_post_type() {
		$response = $this->do_request_with_params(
			array(
				'type'    => 'post',
				'subtype' => 'post,nav_menu_item',
			)
		);
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test getting items directly with a custom search handler.
	 */
	public function test_custom_search_handler_get_items() {
		$controller = new WP_REST_Search_Controller( array( new WP_REST_Test_Search_Handler( 10 ) ) );

		$request  = $this->get_request(
			array(
				'page'     => 1,
				'per_page' => 10,
				'type'     => 'test',
				'subtype'  => array( WP_REST_Search_Controller::TYPE_ANY ),
			)
		);
		$response = $controller->get_items( $request );
		$this->assertSameSets( range( 1, 10 ), wp_list_pluck( $response->get_data(), 'id' ) );

		$request  = $this->get_request(
			array(
				'page'     => 1,
				'per_page' => 10,
				'type'     => 'test',
				'subtype'  => array( 'test_first_type' ),
			)
		);
		$response = $controller->get_items( $request );
		$this->assertSameSets( range( 1, 5 ), wp_list_pluck( $response->get_data(), 'id' ) );
	}

	/**
	 * Test preparing an item directly with a custom search handler.
	 */
	public function test_custom_search_handler_prepare_item() {
		$controller = new WP_REST_Search_Controller( array( new WP_REST_Test_Search_Handler( 10 ) ) );

		$request  = $this->get_request(
			array(
				'type'    => 'test',
				'subtype' => array( WP_REST_Search_Controller::TYPE_ANY ),
			)
		);
		$response = $controller->prepare_item_for_response( 1, $request );
		$data     = $response->get_data();
		$this->assertSame(
			array(
				'id',
				'title',
				'url',
				'type',
				'subtype',
				'label',
			),
			array_keys( $data )
		);
	}

	/**
	 * Test preparing an item directly with a custom search handler with limited fields.
	 */
	public function test_custom_search_handler_prepare_item_limit_fields() {
		if ( ! method_exists( 'WP_REST_Controller', 'get_fields_for_response' ) ) {
			$this->markTestSkipped( 'Limiting fields requires the WP_REST_Controller::get_fields_for_response() method.' );
		}

		$controller = new WP_REST_Search_Controller( array( new WP_REST_Test_Search_Handler( 10 ) ) );

		$request  = $this->get_request(
			array(
				'type'    => 'test',
				'subtype' => array( WP_REST_Search_Controller::TYPE_ANY ),
				'_fields' => 'id,title',
			)
		);
		$response = $controller->prepare_item_for_response( 1, $request );
		$data     = $response->get_data();
		$this->assertSame(
			array(
				'id',
				'title',
			),
			array_keys( $data )
		);
	}

	/**
	 * Test getting the collection params directly with a custom search handler.
	 */
	public function test_custom_search_handler_get_collection_params() {
		$controller = new WP_REST_Search_Controller( array( new WP_REST_Test_Search_Handler( 10 ) ) );

		$params = $controller->get_collection_params();
		$this->assertSame( 'test', $params[ WP_REST_Search_Controller::PROP_TYPE ]['default'] );
		$this->assertSameSets( array( 'test' ), $params[ WP_REST_Search_Controller::PROP_TYPE ]['enum'] );
		$this->assertSameSets( array( 'test_first_type', 'test_second_type', WP_REST_Search_Controller::TYPE_ANY ), $params[ WP_REST_Search_Controller::PROP_SUBTYPE ]['items']['enum'] );
	}

	/**
	 * @ticket 47684
	 */
	public function test_search_result_links_are_embedded() {
		$response = $this->do_request_with_params( array( 'per_page' => 1 ) );
		$data     = rest_get_server()->response_to_data( $response, true )[0];

		$this->assertArrayHasKey( '_embedded', $data );
		$this->assertArrayHasKey( 'self', $data['_embedded'] );
		$this->assertCount( 1, $data['_embedded']['self'] );
		$this->assertArrayHasKey( WP_REST_Search_Controller::PROP_ID, $data['_embedded']['self'][0] );
		$this->assertSame( $data[ WP_REST_Search_Controller::PROP_ID ], $data['_embedded']['self'][0][ WP_REST_Search_Controller::PROP_ID ] );
	}

	/**
	 * Search through terms of any type.
	 *
	 * @ticket 51458
	 */
	public function test_get_items_search_type_term() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'term',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array(
				0 => 1, // That is the default category.
				self::$my_category_id,
				self::$my_tag_id,
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Search through terms of subtype 'category'.
	 *
	 * @ticket 51458
	 */
	public function test_get_items_search_type_term_subtype_category() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'term',
				'subtype'  => 'category',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array(
				0 => 1, // That is the default category.
				self::$my_category_id,
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Search through posts of an invalid post type.
	 *
	 * @ticket 51458
	 */
	public function test_get_items_search_term_subtype_invalid() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'term',
				'subtype'  => 'invalid',
			)
		);

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Search through posts and pages.
	 *
	 * @ticket 51458
	 */
	public function test_get_items_search_categories_and_tags() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'term',
				'subtype'  => 'category,post_tag',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array(
				0 => 1, // This is the default category.
				self::$my_category_id,
				self::$my_tag_id,
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Search through all that matches a 'Test Category' search.
	 *
	 * @ticket 51458
	 */
	public function test_get_items_search_for_test_category() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'search'   => 'Test Category',
				'type'     => 'term',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array(
				self::$my_category_id,
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Search through all that matches a 'Test Tag' search.
	 *
	 * @ticket 51458
	 */
	public function test_get_items_search_for_test_tag() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'search'   => 'Test Tag',
				'type'     => 'term',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array(
				self::$my_tag_id,
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * Searching for a term that doesn't exist should return an empty result.
	 *
	 * @ticket 51458
	 */
	public function test_get_items_search_for_missing_term() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'search'   => 'Doesn\'t exist',
				'type'     => 'term',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertEmpty( $response->get_data() );
	}

	/**
	 * Search through post formats of any type.
	 *
	 * @ticket 51459
	 */
	public function test_get_items_search_type_post_format() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'post-format',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertContains(
			'Aside',
			wp_list_pluck( $response->get_data(), 'title' )
		);
	}

	/**
	 * Search through all that matches a 'Aside' search.
	 *
	 * @ticket 51459
	 */
	public function test_get_items_search_for_test_post_format() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'search'   => 'Aside',
				'type'     => 'post-format',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains(
			'Aside',
			wp_list_pluck( $response->get_data(), 'title' )
		);
	}

	/**
	 * Searching for a post format that doesn't exist should return an empty
	 * result.
	 *
	 * @ticket 51459
	 */
	public function test_get_items_search_for_missing_post_format() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'search'   => 'Doesn\'t exist',
				'type'     => 'post-format',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertEmpty( $response->get_data() );
	}

	/**
	 * Perform a REST request to our search endpoint with given parameters.
	 */
	private function do_request_with_params( $params = array(), $method = 'GET' ) {
		$request = $this->get_request( $params, $method );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Get a REST request object for given parameters.
	 */
	private function get_request( $params = array(), $method = 'GET' ) {
		$request = new WP_REST_Request( $method, '/wp/v2/search' );

		foreach ( $params as $param => $value ) {
			$request->set_param( $param, $value );
		}

		return $request;
	}

	/**
	 * @ticket 56546
	 */
	public function test_get_items_search_posts_include_ids() {
		$response = $this->do_request_with_params(
			array(
				'include' => array_slice( self::$my_title_post_ids, 1, 2 ),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array( self::$my_title_post_ids[1], self::$my_title_post_ids[2] ),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * @ticket 56546
	 */
	public function test_get_items_search_posts_exclude_ids() {
		$response = $this->do_request_with_params(
			array(
				'exclude' => self::$my_title_page_ids,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array_merge(
				self::$my_title_post_ids,
				self::$my_content_post_ids
			),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * @ticket 56546
	 */
	public function test_get_items_search_terms_include_ids() {
		$response = $this->do_request_with_params(
			array(
				'include' => self::$my_tag_id,
				'type'    => 'term',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array( self::$my_tag_id ),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * @ticket 56546
	 */
	public function test_get_items_search_terms_exclude_ids() {
		$response = $this->do_request_with_params(
			array(
				// "1" is the default category.
				'exclude' => array( 1, self::$my_tag_id ),
				'type'    => 'term',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			array( self::$my_category_id ),
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * @ticket 60771
	 */
	public function test_sanitize_subtypes_validates_type() {
		$response = $this->do_request_with_params(
			array(
				'subtype' => 'page',
				'type'    => array( 'invalid' ),
			)
		);

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Search through attachments.
	 *
	 * @ticket 61254
	 */
	public function test_get_items_search_type_media() {
		$response = $this->do_request_with_params(
			array(
				'per_page' => 100,
				'type'     => 'media',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets(
			self::$my_attachment_ids,
			wp_list_pluck( $response->get_data(), 'id' )
		);
	}

	/**
	 * @ticket 61254
	 */
	public function test_label_field_contain_the_post_type_singular_name() {
		$response = $this->do_request_with_params(
			array(
				'subtype'  => 'page',
				'per_page' => 1,
			)
		);

		$response_data = $response->get_data();
		$this->assertSame( 'Page', $response_data[0]['label'] );
	}

	/**
	 * @ticket 61254
	 */
	public function test_label_field_contain_the_taxonomy_name() {
		$response = $this->do_request_with_params(
			array(
				'type'     => 'term',
				'subtype'  => 'category',
				'per_page' => 1,
			)
		);

		$response_data = $response->get_data();
		$this->assertSame( 'Category', $response_data[0]['label'] );
	}

	/**
	 * @ticket 61254
	 */
	public function test_label_field_contain_the_post_format_name() {
		$response = $this->do_request_with_params(
			array(
				'type'     => 'post-format',
				'per_page' => 1,
			)
		);

		$response_data = $response->get_data();
		$this->assertSame( 'Aside', $response_data[0]['label'] );
	}
}
