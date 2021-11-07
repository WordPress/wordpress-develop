<?php
/**
 * Unit tests covering WP_Test_REST_Widget_Types_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.8.0
 */

/**
 * Tests for WP_REST_Widget_Types_Controller.
 *
 * @since 5.8.0
 *
 * @see WP_TEST_REST_Controller_Testcase
 * @group restapi
 * @covers WP_REST_Widget_Types_Controller
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
		$endpoint = new WP_REST_Widget_Types_Controller;
		foreach ( $data as $item ) {
			$widget_type = $endpoint->get_widget( $item['name'] );
			$this->check_widget_type_object( $widget_type, $item, $item['_links'] );
		}
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
			static function( $widget ) {
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
		$endpoint    = new WP_REST_Widget_Types_Controller;
		$widget_type = $endpoint->get_widget( $widget_name );
		$this->check_widget_type_object( $widget_type, $response->get_data(), $response->get_links() );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_widget_legacy() {
		$widget_id = 'legacy';
		wp_register_sidebar_widget(
			$widget_id,
			'WP legacy widget',
			static function() {}
		);
		wp_set_current_user( self::$admin_id );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/widget-types/' . $widget_id );
		$response    = rest_get_server()->dispatch( $request );
		$endpoint    = new WP_REST_Widget_Types_Controller;
		$widget_type = $endpoint->get_widget( $widget_id );
		$this->check_widget_type_object( $widget_type, $response->get_data(), $response->get_links() );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_widget_invalid_name() {
		$widget_type = 'fake';
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widget-types/' . $widget_type );
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
			static function() {},
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
	 * @ticket 41683
	 */
	public function test_get_items_wrong_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widget-types' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_wrong_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widget-types/calendar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widget-types' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widget-types/calendar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_prepare_item() {
		$endpoint    = new WP_REST_Widget_Types_Controller;
		$widget_type = $endpoint->get_widget( 'calendar' );
		$request     = new WP_REST_Request;
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
				'raw'     => new stdClass,
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
				'raw'     => new stdClass,
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
	 * The test_create_item() method does not exist for widget types.
	 */
	public function test_create_item() {}

	/**
	 * The test_update_item() method does not exist for widget types.
	 */
	public function test_update_item() {}

	/**
	 * The test_delete_item() method does not exist for widget types.
	 */
	public function test_delete_item() {}
}
