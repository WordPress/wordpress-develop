<?php
/**
 * Unit tests covering WP_REST_Site_Meta_Fields functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 *
 * @group restapi
 * @group ms-required
 *
 * @coversDefaultClass WP_REST_Site_Meta_Fields
 */
abstract class WP_Test_REST_Site_Meta_Fields extends WP_Test_REST_TestCase {
	protected static $wp_meta_keys_saved;
	protected static $site_admins_saved;
	protected static $site_id;
	protected static $superadmin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$wp_meta_keys_saved = $GLOBALS['wp_meta_keys'] ?? array();
		self::$site_admins_saved  = get_site_option( 'site_admins', array() );

		self::$superadmin_id = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);

		update_site_option( 'site_admins', array( 'superadmin' ) );
		self::$site_id = $factory->blog->create(
			array(
				'path' => '/site-meta/',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		$GLOBALS['wp_meta_keys'] = self::$wp_meta_keys_saved;
		update_site_option( 'site_admins', self::$site_admins_saved );

		wp_delete_site( self::$site_id );
		self::delete_user( self::$superadmin_id );
	}

	public function set_up() {
		parent::set_up();

		if ( ! is_site_meta_supported() ) {
			$this->markTestSkipped( 'Site meta is not supported on this installation.' );
		}

		wp_set_current_user( self::$superadmin_id );

		register_meta(
			'blog',
			'test_single',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_meta(
			'blog',
			'test_multi',
			array(
				'show_in_rest' => true,
				'single'       => false,
				'type'         => 'string',
			)
		);
		register_meta(
			'blog',
			'test_bad_auth',
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'auth_callback' => '__return_false',
				'type'          => 'string',
			)
		);
		register_meta(
			'blog',
			'test_bad_auth_multi',
			array(
				'show_in_rest'  => true,
				'single'        => false,
				'auth_callback' => '__return_false',
				'type'          => 'string',
			)
		);
		register_meta( 'blog', 'test_no_rest', array() );
		register_meta(
			'blog',
			'test_rest_disabled',
			array(
				'show_in_rest' => false,
				'type'         => 'string',
			)
		);
		register_meta(
			'blog',
			'test_custom_schema',
			array(
				'single'       => true,
				'type'         => 'integer',
				'show_in_rest' => array(
					'schema' => array(
						'type' => 'number',
					),
				),
			)
		);
		register_meta(
			'blog',
			'test_custom_schema_multi',
			array(
				'single'       => false,
				'type'         => 'integer',
				'show_in_rest' => array(
					'schema' => array(
						'type' => 'number',
					),
				),
			)
		);
		register_meta(
			'blog',
			'test_invalid_type',
			array(
				'single'       => true,
				'type'         => 'lalala',
				'show_in_rest' => true,
			)
		);
		register_meta(
			'blog',
			'test_no_type',
			array(
				'single'       => true,
				'type'         => null,
				'show_in_rest' => true,
			)
		);
		register_meta(
			'blog',
			'test_custom_name',
			array(
				'single'       => true,
				'type'         => 'string',
				'show_in_rest' => array(
					'name' => 'new_name',
				),
			)
		);
		register_meta(
			'blog',
			'test_custom_name_multi',
			array(
				'single'       => false,
				'type'         => 'string',
				'show_in_rest' => array(
					'name' => 'new_name_multi',
				),
			)
		);

		$GLOBALS['wp_rest_server'] = new Spy_REST_Server();
		do_action( 'rest_api_init', $GLOBALS['wp_rest_server'] );
	}

	protected function grant_write_permission() {
		wp_set_current_user( self::$superadmin_id );
	}

	protected function reload_rest_server() {
		$GLOBALS['wp_rest_server'] = new Spy_REST_Server();
		do_action( 'rest_api_init', $GLOBALS['wp_rest_server'] );
	}

	/**
	 * @covers ::get_item
	 */
	public function test_get_value() {
		update_site_meta( self::$site_id, 'test_single', 'testvalue' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$site_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_single', $meta );
		$this->assertSame( 'testvalue', $meta['test_single'] );
	}

	/**
	 * @depends test_get_value
	 * @covers ::get_item
	 */
	public function test_get_multi_value() {
		add_site_meta( self::$site_id, 'test_multi', 'value1' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$site_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_multi', $meta );
		$this->assertIsArray( $meta['test_multi'] );
		$this->assertContains( 'value1', $meta['test_multi'] );

		add_site_meta( self::$site_id, 'test_multi', 'value2' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertContains( 'value1', $meta['test_multi'] );
		$this->assertContains( 'value2', $meta['test_multi'] );
	}

	/**
	 * @depends test_get_value
	 * @covers ::get_item
	 */
	public function test_get_unregistered() {
		update_site_meta( self::$site_id, 'test_unregistered', 'value1' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$site_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayNotHasKey( 'test_unregistered', $meta );
	}

	/**
	 * @depends test_get_value
	 * @covers ::get_item
	 */
	public function test_get_registered_no_api_access() {
		update_site_meta( self::$site_id, 'test_no_rest', 'for_the_wicked' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$site_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayNotHasKey( 'test_no_rest', $meta );
	}

	/**
	 * @depends test_get_value
	 * @covers ::get_item
	 */
	public function test_get_registered_api_disabled() {
		update_site_meta( self::$site_id, 'test_rest_disabled', 'sleepless_nights' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$site_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayNotHasKey( 'test_rest_disabled', $meta );
	}

	/**
	 * @covers ::get_item
	 */
	public function test_get_value_types() {
		register_meta(
			'blog',
			'test_string',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_meta(
			'blog',
			'test_number',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'number',
			)
		);
		register_meta(
			'blog',
			'test_bool',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'boolean',
			)
		);

		$GLOBALS['wp_rest_server'] = new Spy_REST_Server();
		do_action( 'rest_api_init', $GLOBALS['wp_rest_server'] );

		update_site_meta( self::$site_id, 'test_string', 42 );
		update_site_meta( self::$site_id, 'test_number', '42' );
		update_site_meta( self::$site_id, 'test_bool', 1 );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$site_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];

		$this->assertArrayHasKey( 'test_string', $meta );
		$this->assertIsString( $meta['test_string'] );
		$this->assertSame( '42', $meta['test_string'] );

		$this->assertArrayHasKey( 'test_number', $meta );
		$this->assertIsFloat( $meta['test_number'] );
		$this->assertSame( 42.0, $meta['test_number'] );

		$this->assertArrayHasKey( 'test_bool', $meta );
		$this->assertIsBool( $meta['test_bool'] );
		$this->assertTrue( $meta['test_bool'] );
	}

	/**
	 * @covers ::get_item
	 */
	public function test_get_value_custom_name() {
		update_site_meta( self::$site_id, 'test_custom_name', 'janet' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$site_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'new_name', $meta );
		$this->assertSame( 'janet', $meta['new_name'] );
	}

	/**
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema_omits_meta_when_site_meta_is_unsupported() {
		add_filter( 'pre_site_option_site_meta_supported', '__return_zero' );
		$this->reload_rest_server();

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayNotHasKey( 'meta', $data['schema']['properties'] );
	}

	/**
	 * @depends test_get_value
	 * @covers ::update_item
	 */
	public function test_set_value() {
		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_single' => 'test_value',
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/sites/%d', self::$site_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_site_meta( self::$site_id, 'test_single', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertSame( 'test_value', $meta[0] );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_single', $meta );
		$this->assertSame( 'test_value', $meta['test_single'] );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_create_item_rejects_meta_when_site_meta_is_unsupported() {
		add_filter( 'pre_site_option_site_meta_supported', '__return_zero' );
		$this->reload_rest_server();

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/meta-disabled/' );
		$request->set_param(
			'meta',
			array(
				'test_single' => 'test_value',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_meta_not_supported', $response, 400 );
	}

	/**
	 * @covers ::update_item
	 */
	public function test_update_item_rejects_meta_when_site_meta_is_unsupported() {
		add_filter( 'pre_site_option_site_meta_supported', '__return_zero' );
		$this->reload_rest_server();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$site_id ) );
		$request->set_param(
			'meta',
			array(
				'test_single' => 'test_value',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_meta_not_supported', $response, 400 );
	}

	/**
	 * @depends test_get_value
	 * @covers ::update_item
	 */
	public function test_set_duplicate_single_value() {
		update_site_meta( self::$site_id, 'test_single', 'test_value' );
		$this->assertSame( 'test_value', get_site_meta( self::$site_id, 'test_single', true ) );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_single' => 'test_value',
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/sites/%d', self::$site_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$this->assertSame( 'test_value', get_site_meta( self::$site_id, 'test_single', true ) );
	}
}
