<?php
/**
 * Unit tests covering WP_Test_REST_Widget_Types_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.8.0
 *
 * @covers WP_REST_Widget_Types_Controller
 *
 * @see WP_TEST_REST_Controller_Testcase
 * @group restapi
 * @group widgets
 */
class WP_Test_REST_Widget_Types_Controller extends WP_Test_REST_Controller_Testcase {

	/**
	 * Admin user ID.
	 *
	 * @since 5.8.0
	 *
	 * @var int $subscriber_id
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @since 5.8.0
	 *
	 * @var int $subscriber_id
	 */
	protected static $subscriber_id;

	/**
	 * Create fake data before our tests run.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$subscriber_id );
	}

	private function setup_widget( $id_base, $number, $settings ) {
		global $wp_widget_factory;

		$option_name = "widget_$id_base";
		update_option(
			$option_name,
			array(
				$number => $settings,
			)
		);

		$widget_object = $wp_widget_factory->get_widget_object( $id_base );
		$widget_object->_set( $number );
		$widget_object->_register_one( $number );
	}

	/**
	 * @ticket 41683
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/widget-types', $routes );
		$this->assertCount( 1, $routes['/wp/v2/widget-types'] );
		$this->assertArrayHasKey( '/wp/v2/widget-types/(?P<id>[a-zA-Z0-9_-]+)', $routes );
		$this->assertCount( 1, $routes['/wp/v2/widget-types/(?P<id>[a-zA-Z0-9_-]+)'] );
		$this->assertArrayHasKey( '/wp/v2/widget-types/(?P<id>[a-zA-Z0-9_-]+)/encode', $routes );
		$this->assertCount( 1, $routes['/wp/v2/widget-types/(?P<id>[a-zA-Z0-9_-]+)/encode'] );
	}

	/**
	 * @ticket 41683
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/widget-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/widget-types/calendar' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items() {
		wp_widgets_init();
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widget-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertGreaterThan( 1, count( $data ) );
		$endpoint = new WP_REST_Widget_Types_Controller();
		foreach ( $data as $item ) {
			$widget_type = $endpoint->get_widget( $item['name'] );
			$this->check_widget_type_object( $widget_type, $item, $item['_links'] );
		}
	}

	/**
	 * @ticket 56481
	 */
	public function test_get_items_with_head_request_should_not_prepare_widget_types_data() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'HEAD', '/wp/v2/widget-types' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * @ticket 53303
	 */
	public function test_get_items_ordering() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widget-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertGreaterThan( 1, count( $data ) );
		$ids    = wp_list_pluck( $data, 'id' );
		$sorted = $ids;
		sort( $sorted );

		$this->assertSame( $sorted, $ids );
	}

	/**
	 * @ticket 53305
	 */
	public function test_get_items_removes_duplicates() {
		wp_set_current_user( self::$admin_id );
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_widget(
			'text',
			2,
			array(
				'text' => 'Custom text test',
			)
		);
		$request      = new WP_REST_Request( 'GET', '/wp/v2/widget-types' );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$text_widgets = array_filter(
			$data,
			static function ( $widget ) {
				return 'text' === $widget['id'];
			}
		);
		$this->assertCount( 1, $text_widgets );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item() {
		$widget_name = 'calendar';
		wp_set_current_user( self::$admin_id );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/widget-types/' . $widget_name );
		$response    = rest_get_server()->dispatch( $request );
		$endpoint    = new WP_REST_Widget_Types_Controller();
		$widget_type = $endpoint->get_widget( $widget_name );
		$this->check_widget_type_object( $widget_type, $response->get_data(), $response->get_links() );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_item_should_allow_adding_headers_via_filter( $method ) {
		$widget_name = 'calendar';
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( $method, '/wp/v2/widget-types/' . $widget_name );

		$hook_name = 'rest_prepare_widget_type';
		$filter    = new MockAction();
		$callback  = array( $filter, 'filter' );
		add_filter( $hook_name, $callback );
		$header_filter = new class() {
			public static function add_custom_header( $response ) {
				$response->header( 'X-Test-Header', 'Test' );

				return $response;
			}
		};
		add_filter( $hook_name, array( $header_filter, 'add_custom_header' ) );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( $hook_name, $callback );
		remove_filter( $hook_name, array( $header_filter, 'add_custom_header' ) );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
		$this->assertSame( 1, $filter->get_call_count(), 'The "' . $hook_name . '" filter was not called when it should be for GET/HEAD requests.' );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-Test-Header', $headers, 'The "X-Test-Header" header should be present in the response.' );
		$this->assertSame( 'Test', $headers['X-Test-Header'], 'The "X-Test-Header" header value should be equal to "Test".' );
		if ( 'HEAD' !== $method ) {
			return null;
		}
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * Data provider intended to provide HTTP method names for testing GET and HEAD requests.
	 *
	 * @return array
	 */
	public static function data_readable_http_methods() {
		return array(
			'GET request'  => array( 'GET' ),
			'HEAD request' => array( 'HEAD' ),
		);
	}

	/**
	 * @dataProvider data_head_request_with_specified_fields_returns_success_response
	 * @ticket 56481
	 *
	 * @param string $path The path to test.
	 */
	public function test_head_request_with_specified_fields_returns_success_response( $path ) {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'HEAD', $path );
		$request->set_param( '_fields', 'id' );
		$server   = rest_get_server();
		$response = $server->dispatch( $request );
		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $server, $request );
		remove_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10 );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
	}

	/**
	 * Data provider intended to provide paths for testing HEAD requests.
	 *
	 * @return array
	 */
	public static function data_head_request_with_specified_fields_returns_success_response() {
		return array(
			'get_item request'  => array( '/wp/v2/widget-types/calendar' ),
			'get_items request' => array( '/wp/v2/widget-types' ),
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_widget_legacy() {
		$widget_id = 'legacy';
		wp_register_sidebar_widget(
			$widget_id,
			'WP legacy widget',
			static function () {}
		);
		wp_set_current_user( self::$admin_id );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/widget-types/' . $widget_id );
		$response    = rest_get_server()->dispatch( $request );
		$endpoint    = new WP_REST_Widget_Types_Controller();
		$widget_type = $endpoint->get_widget( $widget_id );
		$this->check_widget_type_object( $widget_type, $response->get_data(), $response->get_links() );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 41683
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_widget_invalid_name( $method ) {
		$widget_type = 'fake';
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( $method, '/wp/v2/widget-types/' . $widget_type );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_widget_type_invalid', $response, 404 );
	}

	/**
	 * @ticket 53407
	 */
	public function test_get_widgets_decodes_html_entities() {
		wp_set_current_user( self::$admin_id );
		$widget_id = 'archives';
		wp_register_sidebar_widget(
			$widget_id,
			'&#8216;Legacy &#8209; Archive &#8209; Widget&#8217;',
			static function () {},
			array(
				'description' => '&#8220;A great &amp; interesting archive of your site&#8217;s posts!&#8221;',
			)
		);
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widget-types/archives' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( '‘Legacy ‑ Archive ‑ Widget’', $data['name'] );
		$this->assertSame( '“A great & interesting archive of your site’s posts!”', $data['description'] );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_schema() {
		wp_set_current_user( self::$admin_id );
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/widget-types' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 5, $properties );

		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'is_multi', $properties );
		$this->assertArrayHasKey( 'classname', $properties );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 41683
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_wrong_permission( $method ) {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( $method, '/wp/v2/widget-types' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 41683
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_item_wrong_permission( $method ) {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( $method, '/wp/v2/widget-types/calendar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 41683
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_no_permission( $method ) {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( $method, '/wp/v2/widget-types' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 41683
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_item_no_permission( $method ) {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( $method, '/wp/v2/widget-types/calendar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_prepare_item() {
		$endpoint    = new WP_REST_Widget_Types_Controller();
		$widget_type = $endpoint->get_widget( 'calendar' );
		$request     = new WP_REST_Request();
		$request->set_param( 'context', 'edit' );
		$response = $endpoint->prepare_item_for_response( $widget_type, $request );
		$this->check_widget_type_object( $widget_type, $response->get_data(), $response->get_links() );
	}

	/**
	 * Util check widget type object against.
	 *
	 * @since 5.8.0
	 *
	 * @param array $widget_type Sample widget type.
	 * @param array $data Data to compare against.
	 * @param array $links Links to compare again.
	 */
	protected function check_widget_type_object( $widget_type, $data, $links ) {
		// Test data.
		$extra_fields = array(
			'name',
			'id_base',
			'option_name',
			'control_options',
			'widget_options',
			'widget_class',
			'is_multi',
		);

		foreach ( $extra_fields as $extra_field ) {
			if ( isset( $widget_type->$extra_field ) ) {
				$this->assertSame( $data[ $extra_field ], $widget_type->$extra_field, 'Field ' . $extra_field );
			}
		}

		// Test links.
		$this->assertSame( rest_url( 'wp/v2/widget-types' ), $links['collection'][0]['href'] );
	}

	/**
	 * @ticket 41683
	 */
	public function test_encode_form_data_with_no_input() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'POST', '/wp/v2/widget-types/search/encode' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameIgnoreEOL(
			"<p>\n" .
			"\t\t\t<label for=\"widget-search--1-title\">Title:</label>\n" .
			"\t\t\t<input class=\"widefat\" id=\"widget-search--1-title\" name=\"widget-search[-1][title]\" type=\"text\" value=\"\" />\n" .
			"\t\t</p>",
			$data['form']
		);
		$this->assertStringMatchesFormat(
			"<div class=\"widget widget_search\"><form role=\"search\" method=\"get\" id=\"searchform\" class=\"searchform\" action=\"%s\">\n" .
			"\t\t\t\t<div>\n" .
			"\t\t\t\t\t<label class=\"screen-reader-text\" for=\"s\">Search for:</label>\n" .
			"\t\t\t\t\t<input type=\"text\" value=\"\" name=\"s\" id=\"s\" />\n" .
			"\t\t\t\t\t<input type=\"submit\" id=\"searchsubmit\" value=\"Search\" />\n" .
			"\t\t\t\t</div>\n" .
			"\t\t\t</form></div>",
			$data['preview']
		);
		$this->assertEqualSets(
			array(
				'encoded' => base64_encode( serialize( array() ) ),
				'hash'    => wp_hash( serialize( array() ) ),
				'raw'     => new stdClass(),
			),
			$data['instance']
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_encode_form_data_with_number() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/widget-types/search/encode' );
		$request->set_param( 'number', 8 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameIgnoreEOL(
			"<p>\n" .
			"\t\t\t<label for=\"widget-search-8-title\">Title:</label>\n" .
			"\t\t\t<input class=\"widefat\" id=\"widget-search-8-title\" name=\"widget-search[8][title]\" type=\"text\" value=\"\" />\n" .
			"\t\t</p>",
			$data['form']
		);
		$this->assertStringMatchesFormat(
			"<div class=\"widget widget_search\"><form role=\"search\" method=\"get\" id=\"searchform\" class=\"searchform\" action=\"%s\">\n" .
			"\t\t\t\t<div>\n" .
			"\t\t\t\t\t<label class=\"screen-reader-text\" for=\"s\">Search for:</label>\n" .
			"\t\t\t\t\t<input type=\"text\" value=\"\" name=\"s\" id=\"s\" />\n" .
			"\t\t\t\t\t<input type=\"submit\" id=\"searchsubmit\" value=\"Search\" />\n" .
			"\t\t\t\t</div>\n" .
			"\t\t\t</form></div>",
			$data['preview']
		);
		$this->assertEqualSets(
			array(
				'encoded' => base64_encode( serialize( array() ) ),
				'hash'    => wp_hash( serialize( array() ) ),
				'raw'     => new stdClass(),
			),
			$data['instance']
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_encode_form_data_with_instance() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/widget-types/search/encode' );
		$request->set_param(
			'instance',
			array(
				'encoded' => base64_encode( serialize( array( 'title' => 'Test title' ) ) ),
				'hash'    => wp_hash( serialize( array( 'title' => 'Test title' ) ) ),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameIgnoreEOL(
			"<p>\n" .
			"\t\t\t<label for=\"widget-search--1-title\">Title:</label>\n" .
			"\t\t\t<input class=\"widefat\" id=\"widget-search--1-title\" name=\"widget-search[-1][title]\" type=\"text\" value=\"Test title\" />\n" .
			"\t\t</p>",
			$data['form']
		);
		$this->assertStringMatchesFormat(
			"<div class=\"widget widget_search\"><h2 class=\"widgettitle\">Test title</h2><form role=\"search\" method=\"get\" id=\"searchform\" class=\"searchform\" action=\"%s\">\n" .
			"\t\t\t\t<div>\n" .
			"\t\t\t\t\t<label class=\"screen-reader-text\" for=\"s\">Search for:</label>\n" .
			"\t\t\t\t\t<input type=\"text\" value=\"\" name=\"s\" id=\"s\" />\n" .
			"\t\t\t\t\t<input type=\"submit\" id=\"searchsubmit\" value=\"Search\" />\n" .
			"\t\t\t\t</div>\n" .
			"\t\t\t</form></div>",
			$data['preview']
		);
		$this->assertSameSets(
			array(
				'encoded' => base64_encode( serialize( array( 'title' => 'Test title' ) ) ),
				'hash'    => wp_hash( serialize( array( 'title' => 'Test title' ) ) ),
				'raw'     => array( 'title' => 'Test title' ),
			),
			$data['instance']
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_encode_form_data_with_form_data() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/widget-types/search/encode' );
		$request->set_param( 'form_data', 'widget-search[-1][title]=Updated+title' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameIgnoreEOL(
			"<p>\n" .
			"\t\t\t<label for=\"widget-search--1-title\">Title:</label>\n" .
			"\t\t\t<input class=\"widefat\" id=\"widget-search--1-title\" name=\"widget-search[-1][title]\" type=\"text\" value=\"Updated title\" />\n" .
			"\t\t</p>",
			$data['form']
		);
		$this->assertStringMatchesFormat(
			"<div class=\"widget widget_search\"><h2 class=\"widgettitle\">Updated title</h2><form role=\"search\" method=\"get\" id=\"searchform\" class=\"searchform\" action=\"%s\">\n" .
			"\t\t\t\t<div>\n" .
			"\t\t\t\t\t<label class=\"screen-reader-text\" for=\"s\">Search for:</label>\n" .
			"\t\t\t\t\t<input type=\"text\" value=\"\" name=\"s\" id=\"s\" />\n" .
			"\t\t\t\t\t<input type=\"submit\" id=\"searchsubmit\" value=\"Search\" />\n" .
			"\t\t\t\t</div>\n" .
			"\t\t\t</form></div>",
			$data['preview']
		);
		$this->assertSameSets(
			array(
				'encoded' => base64_encode( serialize( array( 'title' => 'Updated title' ) ) ),
				'hash'    => wp_hash( serialize( array( 'title' => 'Updated title' ) ) ),
				'raw'     => array( 'title' => 'Updated title' ),
			),
			$data['instance']
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_encode_form_data_no_raw() {
		global $wp_widget_factory;
		wp_set_current_user( self::$admin_id );
		$wp_widget_factory->widgets['WP_Widget_Search']->widget_options['show_instance_in_rest'] = false;
		$request = new WP_REST_Request( 'POST', '/wp/v2/widget-types/search/encode' );
		$request->set_param(
			'instance',
			array(
				'encoded' => base64_encode( serialize( array( 'title' => 'Test title' ) ) ),
				'hash'    => wp_hash( serialize( array( 'title' => 'Test title' ) ) ),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameIgnoreEOL(
			"<p>\n" .
			"\t\t\t<label for=\"widget-search--1-title\">Title:</label>\n" .
			"\t\t\t<input class=\"widefat\" id=\"widget-search--1-title\" name=\"widget-search[-1][title]\" type=\"text\" value=\"Test title\" />\n" .
			"\t\t</p>",
			$data['form']
		);
		$this->assertStringMatchesFormat(
			"<div class=\"widget widget_search\"><h2 class=\"widgettitle\">Test title</h2><form role=\"search\" method=\"get\" id=\"searchform\" class=\"searchform\" action=\"%s\">\n" .
			"\t\t\t\t<div>\n" .
			"\t\t\t\t\t<label class=\"screen-reader-text\" for=\"s\">Search for:</label>\n" .
			"\t\t\t\t\t<input type=\"text\" value=\"\" name=\"s\" id=\"s\" />\n" .
			"\t\t\t\t\t<input type=\"submit\" id=\"searchsubmit\" value=\"Search\" />\n" .
			"\t\t\t\t</div>\n" .
			"\t\t\t</form></div>",
			$data['preview']
		);
		$this->assertSameSets(
			array(
				'encoded' => base64_encode( serialize( array( 'title' => 'Test title' ) ) ),
				'hash'    => wp_hash( serialize( array( 'title' => 'Test title' ) ) ),
			),
			$data['instance']
		);
		$wp_widget_factory->widgets['WP_Widget_Search']->widget_options['show_instance_in_rest'] = true;
	}

	/**
	 * The create_item() method does not exist for widget types.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_create_item() {
		// Controller does not implement create_item().
	}

	/**
	 * The update_item() method does not exist for widget types.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_update_item() {
		// Controller does not implement update_item().
	}

	/**
	 * The delete_item() method does not exist for widget types.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Controller does not implement delete_item().
	}
}
