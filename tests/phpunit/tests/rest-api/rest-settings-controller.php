<?php
/**
 * Unit tests covering WP_Test_REST_Settings_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_Test_REST_Settings_Controller extends WP_Test_REST_Controller_Testcase {

	protected static $administrator;
	protected static $author;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$administrator = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		self::$author = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$administrator );
		self::delete_user( self::$author );
	}

	public function set_up() {
		parent::set_up();
		$this->endpoint = new WP_REST_Settings_Controller();
	}

	public function tear_down() {
		$settings_to_unregister = array(
			'mycustomsetting',
			'mycustomsetting1',
			'mycustomsetting2',
			'mycustomarraysetting',
		);

		$registered_settings = get_registered_settings();

		foreach ( $settings_to_unregister as $setting ) {
			if ( isset( $registered_settings[ $setting ] ) ) {
				unregister_setting( 'somegroup', $setting );
			}
		}

		parent::tear_down();
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/settings', $routes );
	}

	public function test_get_item() {
		/** Individual settings can't be gotten */
		wp_set_current_user( self::$administrator );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings/title' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_context_param() {
	}

	public function test_get_item_is_not_public_not_authenticated() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_get_item_is_not_public_no_permission() {
		wp_set_current_user( self::$author );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_get_items() {
		wp_set_current_user( self::$administrator );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$actual   = array_keys( $data );

		$expected = array(
			'title',
			'description',
			'timezone',
			'date_format',
			'time_format',
			'site_logo',
			'start_of_week',
			'language',
			'use_smilies',
			'default_category',
			'default_post_format',
			'posts_per_page',
			'default_ping_status',
			'default_comment_status',
		);

		if ( ! is_multisite() ) {
			$expected[] = 'url';
			$expected[] = 'email';
		}

		sort( $expected );
		sort( $actual );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $expected, $actual );
	}

	public function test_get_item_value_is_cast_to_type() {
		wp_set_current_user( self::$administrator );
		update_option( 'posts_per_page', 'invalid_number' ); // This is cast to (int) 1.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $data['posts_per_page'] );
	}

	public function test_get_item_with_custom_setting() {
		wp_set_current_user( self::$administrator );

		register_setting(
			'somegroup',
			'mycustomsetting',
			array(
				'show_in_rest' => array(
					'name'   => 'mycustomsettinginrest',
					'schema' => array(
						'enum'    => array( 'validvalue1', 'validvalue2' ),
						'default' => 'validvalue1',
					),
				),
				'type'         => 'string',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'mycustomsettinginrest', $data );
		$this->assertSame( 'validvalue1', $data['mycustomsettinginrest'] );

		update_option( 'mycustomsetting', 'validvalue2' );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'validvalue2', $data['mycustomsettinginrest'] );
	}

	public function test_get_item_with_custom_array_setting() {
		wp_set_current_user( self::$administrator );

		register_setting(
			'somegroup',
			'mycustomsetting',
			array(
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
				),
				'type'         => 'array',
			)
		);

		// Array is cast to correct types.
		update_option( 'mycustomsetting', array( '1', '2' ) );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array( 1, 2 ), $data['mycustomsetting'] );

		// Empty array works as expected.
		update_option( 'mycustomsetting', array() );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array(), $data['mycustomsetting'] );

		// Invalid value.
		update_option( 'mycustomsetting', array( array( 1 ) ) );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertNull( $data['mycustomsetting'] );

		// No option value.
		delete_option( 'mycustomsetting' );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertNull( $data['mycustomsetting'] );
	}

	public function test_get_item_with_custom_object_setting() {
		wp_set_current_user( self::$administrator );

		register_setting(
			'somegroup',
			'mycustomsetting',
			array(
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'a' => array(
								'type' => 'integer',
							),
						),
					),
				),
				'type'         => 'object',
			)
		);

		// We have to re-register the route, as the args changes based off registered settings.
		rest_get_server()->override_by_default = true;
		$this->endpoint->register_routes();

		// Object is cast to correct types.
		update_option( 'mycustomsetting', array( 'a' => '1' ) );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array( 'a' => 1 ), $data['mycustomsetting'] );

		// Empty array works as expected.
		update_option( 'mycustomsetting', array() );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array(), $data['mycustomsetting'] );

		// Invalid value.
		update_option(
			'mycustomsetting',
			array(
				'a' => 1,
				'b' => 2,
			)
		);
		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertNull( $data['mycustomsetting'] );
	}

	public function get_setting_custom_callback( $result, $name, $args ) {
		switch ( $name ) {
			case 'mycustomsetting1':
				return 'filtered1';
		}
		return $result;
	}

	public function test_get_item_with_filter() {
		wp_set_current_user( self::$administrator );

		add_filter( 'rest_pre_get_setting', array( $this, 'get_setting_custom_callback' ), 10, 3 );

		register_setting(
			'somegroup',
			'mycustomsetting1',
			array(
				'show_in_rest' => array(
					'name' => 'mycustomsettinginrest1',
				),
				'type'         => 'string',
			)
		);

		register_setting(
			'somegroup',
			'mycustomsetting2',
			array(
				'show_in_rest' => array(
					'name' => 'mycustomsettinginrest2',
				),
				'type'         => 'string',
			)
		);

		update_option( 'mycustomsetting1', 'unfiltered1' );
		update_option( 'mycustomsetting2', 'unfiltered2' );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );

		$this->assertArrayHasKey( 'mycustomsettinginrest1', $data );
		$this->assertSame( 'unfiltered1', $data['mycustomsettinginrest1'] );

		$this->assertArrayHasKey( 'mycustomsettinginrest2', $data );
		$this->assertSame( 'unfiltered2', $data['mycustomsettinginrest2'] );

		remove_all_filters( 'rest_pre_get_setting' );
	}

	public function test_get_item_with_invalid_value_array_in_options() {
		wp_set_current_user( self::$administrator );

		register_setting(
			'somegroup',
			'mycustomsetting',
			array(
				'show_in_rest' => array(
					'name'   => 'mycustomsettinginrest',
					'schema' => array(
						'enum'    => array( 'validvalue1', 'validvalue2' ),
						'default' => 'validvalue1',
					),
				),
				'type'         => 'string',
			)
		);

		update_option( 'mycustomsetting', array( 'A sneaky array!' ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertNull( $data['mycustomsettinginrest'] );
	}

	public function test_get_item_with_invalid_object_array_in_options() {
		wp_set_current_user( self::$administrator );

		register_setting(
			'somegroup',
			'mycustomsetting',
			array(
				'show_in_rest' => array(
					'name'   => 'mycustomsettinginrest',
					'schema' => array(
						'enum'    => array( 'validvalue1', 'validvalue2' ),
						'default' => 'validvalue1',
					),
				),
				'type'         => 'string',
			)
		);

		update_option( 'mycustomsetting', (object) array( 'A sneaky array!' ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertNull( $data['mycustomsettinginrest'] );
	}


	public function test_create_item() {
	}

	public function test_update_item() {
		wp_set_current_user( self::$administrator );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'title', 'The new title!' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'The new title!', $data['title'] );
		$this->assertSame( get_option( 'blogname' ), $data['title'] );
	}

	public function update_setting_custom_callback( $result, $name, $value, $args ) {
		if ( 'title' === $name && 'The new title!' === $value ) {
			// Do not allow changing the title in this case.
			return true;
		}

		return false;
	}

	public function test_update_item_with_array() {
		register_setting(
			'somegroup',
			'mycustomsetting',
			array(
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
				),
				'type'         => 'array',
			)
		);

		// We have to re-register the route, as the args changes based off registered settings.
		rest_get_server()->override_by_default = true;
		$this->endpoint->register_routes();
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'mycustomsetting', array( '1', '2' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array( 1, 2 ), $data['mycustomsetting'] );
		$this->assertSame( array( 1, 2 ), get_option( 'mycustomsetting' ) );

		// Setting an empty array.
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'mycustomsetting', array() );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array(), $data['mycustomsetting'] );
		$this->assertSame( array(), get_option( 'mycustomsetting' ) );

		// Setting an invalid array.
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'mycustomsetting', array( 'invalid' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_item_with_nested_object() {
		register_setting(
			'somegroup',
			'mycustomsetting',
			array(
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'a' => array(
								'type'       => 'object',
								'properties' => array(
									'b' => array(
										'type' => 'number',
									),
								),
							),
						),
					),
				),
				'type'         => 'object',
			)
		);

		// We have to re-register the route, as the args changes based off registered settings.
		rest_get_server()->override_by_default = true;
		$this->endpoint->register_routes();
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param(
			'mycustomsetting',
			array(
				'a' => array(
					'b' => 1,
					'c' => 1,
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_item_with_object() {
		register_setting(
			'somegroup',
			'mycustomsetting',
			array(
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'a' => array(
								'type' => 'integer',
							),
						),
					),
				),
				'type'         => 'object',
			)
		);

		// We have to re-register the route, as the args changes based off registered settings.
		rest_get_server()->override_by_default = true;
		$this->endpoint->register_routes();
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'mycustomsetting', array( 'a' => 1 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array( 'a' => 1 ), $data['mycustomsetting'] );
		$this->assertSame( array( 'a' => 1 ), get_option( 'mycustomsetting' ) );

		// Setting an empty object.
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'mycustomsetting', array() );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array(), $data['mycustomsetting'] );
		$this->assertSame( array(), get_option( 'mycustomsetting' ) );

		// Provide more keys.
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param(
			'mycustomsetting',
			array(
				'a' => 1,
				'b' => 2,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// Setting an invalid object.
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'mycustomsetting', array( 'a' => 'invalid' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_item_with_filter() {
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'title', 'The old title!' );
		$request->set_param( 'description', 'The old description!' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'The old title!', $data['title'] );
		$this->assertSame( 'The old description!', $data['description'] );
		$this->assertSame( get_option( 'blogname' ), $data['title'] );
		$this->assertSame( get_option( 'blogdescription' ), $data['description'] );

		add_filter( 'rest_pre_update_setting', array( $this, 'update_setting_custom_callback' ), 10, 4 );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'title', 'The new title!' );
		$request->set_param( 'description', 'The new description!' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'The old title!', $data['title'] );
		$this->assertSame( 'The new description!', $data['description'] );
		$this->assertSame( get_option( 'blogname' ), $data['title'] );
		$this->assertSame( get_option( 'blogdescription' ), $data['description'] );

		remove_all_filters( 'rest_pre_update_setting' );
	}

	public function test_update_item_with_invalid_type() {
		wp_set_current_user( self::$administrator );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'title', array( 'rendered' => 'This should fail.' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_item_with_integer() {
		wp_set_current_user( self::$administrator );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'posts_per_page', 11 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_update_item_with_invalid_float_for_integer() {
		wp_set_current_user( self::$administrator );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'posts_per_page', 10.5 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Setting an item to "null" will essentially restore it to it's default value.
	 */
	public function test_update_item_with_null() {
		update_option( 'posts_per_page', 9 );

		wp_set_current_user( self::$administrator );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'posts_per_page', null );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 10, $data['posts_per_page'] );
	}

	public function test_update_item_with_invalid_enum() {
		update_option( 'posts_per_page', 9 );

		wp_set_current_user( self::$administrator );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'default_ping_status', 'open&closed' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_item_with_invalid_stored_value_in_options() {
		wp_set_current_user( self::$administrator );

		register_setting(
			'somegroup',
			'mycustomsetting',
			array(
				'show_in_rest' => true,
				'type'         => 'string',
			)
		);
		update_option( 'mycustomsetting', array( 'A sneaky array!' ) );

		wp_set_current_user( self::$administrator );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'mycustomsetting', null );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_stored_value', $response, 500 );
	}

	public function test_delete_item() {
		/** Settings can't be deleted */
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/settings/title' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_prepare_item() {
	}

	public function test_get_item_schema() {
	}

	/**
	 * @ticket 42875
	 */
	public function test_register_setting_issues_doing_it_wrong_when_show_in_rest_is_true() {
		$this->setExpectedIncorrectUsage( 'register_setting' );

		register_setting(
			'somegroup',
			'mycustomarraysetting',
			array(
				'type'         => 'array',
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * @ticket 42875
	 */
	public function test_register_setting_issues_doing_it_wrong_when_show_in_rest_omits_schema() {
		$this->setExpectedIncorrectUsage( 'register_setting' );

		register_setting(
			'somegroup',
			'mycustomarraysetting',
			array(
				'type'         => 'array',
				'show_in_rest' => array(
					'prepare_callback' => 'rest_sanitize_value_from_schema',
				),
			)
		);
	}

	/**
	 * @ticket 42875
	 */
	public function test_register_setting_issues_doing_it_wrong_when_show_in_rest_omits_schema_items() {
		$this->setExpectedIncorrectUsage( 'register_setting' );

		register_setting(
			'somegroup',
			'mycustomarraysetting',
			array(
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'default' => array( 'Hi!' ),
					),
				),
			)
		);
	}
}
