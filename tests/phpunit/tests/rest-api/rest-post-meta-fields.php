<?php
/**
 * Unit tests covering WP_REST_Posts meta functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_Test_REST_Post_Meta_Fields extends WP_Test_REST_TestCase {
	protected static $wp_meta_keys_saved;
	protected static $post_id;
	protected static $cpt_post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		register_post_type(
			'cpt',
			array(
				'show_in_rest' => true,
				'supports'     => array( 'custom-fields' ),
			)
		);

		self::$wp_meta_keys_saved = isset( $GLOBALS['wp_meta_keys'] ) ? $GLOBALS['wp_meta_keys'] : array();
		self::$post_id            = $factory->post->create();
		self::$cpt_post_id        = $factory->post->create( array( 'post_type' => 'cpt' ) );
	}

	public static function wpTearDownAfterClass() {
		$GLOBALS['wp_meta_keys'] = self::$wp_meta_keys_saved;
		wp_delete_post( self::$post_id, true );
		wp_delete_post( self::$cpt_post_id, true );

		unregister_post_type( 'cpt' );
	}

	public function set_up() {
		parent::set_up();

		register_meta(
			'post',
			'test_single',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_meta(
			'post',
			'test_multi',
			array(
				'show_in_rest' => true,
				'single'       => false,
				'type'         => 'string',
			)
		);
		register_meta(
			'post',
			'test_bad_auth',
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'auth_callback' => '__return_false',
				'type'          => 'string',
			)
		);
		register_meta(
			'post',
			'test_bad_auth_multi',
			array(
				'show_in_rest'  => true,
				'single'        => false,
				'auth_callback' => '__return_false',
				'type'          => 'string',
			)
		);
		register_meta( 'post', 'test_no_rest', array() );
		register_meta(
			'post',
			'test_rest_disabled',
			array(
				'show_in_rest' => false,
				'type'         => 'string',
			)
		);
		register_meta(
			'post',
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
			'post',
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
			'post',
			'test_invalid_type',
			array(
				'single'       => true,
				'type'         => 'lalala',
				'show_in_rest' => true,
			)
		);
		register_meta(
			'post',
			'test_no_type',
			array(
				'single'       => true,
				'type'         => null,
				'show_in_rest' => true,
			)
		);

		register_meta(
			'post',
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
			'post',
			'test_custom_name_multi',
			array(
				'single'       => false,
				'type'         => 'string',
				'show_in_rest' => array(
					'name' => 'new_name_multi',
				),
			)
		);

		register_post_type(
			'cpt',
			array(
				'show_in_rest' => true,
				'supports'     => array( 'custom-fields' ),
			)
		);

		register_post_meta(
			'cpt',
			'test_cpt_single',
			array(
				'show_in_rest' => true,
				'single'       => true,
			)
		);

		register_post_meta(
			'cpt',
			'test_cpt_multi',
			array(
				'show_in_rest' => true,
				'single'       => false,
			)
		);

		// Register 'test_single' on subtype to override for bad auth.
		register_post_meta(
			'cpt',
			'test_single',
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'auth_callback' => '__return_false',
			)
		);

		register_meta(
			'post',
			'test_boolean_update',
			array(
				'single'            => true,
				'type'              => 'boolean',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		);

		register_meta(
			'post',
			'test_textured_text_update',
			array(
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		);

		register_meta(
			'post',
			'test_json_encoded',
			array(
				'single'       => true,
				'type'         => 'string',
				'show_in_rest' => true,
			)
		);

		register_meta(
			'post',
			'test\'slashed\'key',
			array(
				'single'       => true,
				'type'         => 'string',
				'show_in_rest' => true,
			)
		);

		register_post_meta(
			'post',
			'with_default',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => 'Goodnight Moon',
			)
		);

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = new Spy_REST_Server;
		do_action( 'rest_api_init', $wp_rest_server );
	}

	protected function grant_write_permission() {
		// Ensure we have write permission.
		$user = $this->factory->user->create(
			array(
				'role' => 'editor',
			)
		);
		wp_set_current_user( $user );
	}

	public function test_get_value() {
		add_post_meta( self::$post_id, 'test_single', 'testvalue' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
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
	 */
	public function test_get_multi_value() {
		add_post_meta( self::$post_id, 'test_multi', 'value1' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_multi', $meta );
		$this->assertIsArray( $meta['test_multi'] );
		$this->assertContains( 'value1', $meta['test_multi'] );

		// Check after an update.
		add_post_meta( self::$post_id, 'test_multi', 'value2' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertContains( 'value1', $meta['test_multi'] );
		$this->assertContains( 'value2', $meta['test_multi'] );
	}

	/**
	 * @depends test_get_value
	 */
	public function test_get_unregistered() {
		add_post_meta( self::$post_id, 'test_unregistered', 'value1' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayNotHasKey( 'test_unregistered', $meta );
	}

	/**
	 * @depends test_get_value
	 */
	public function test_get_registered_no_api_access() {
		add_post_meta( self::$post_id, 'test_no_rest', 'for_the_wicked' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayNotHasKey( 'test_no_rest', $meta );
	}

	/**
	 * @depends test_get_value
	 */
	public function test_get_registered_api_disabled() {
		add_post_meta( self::$post_id, 'test_rest_disabled', 'sleepless_nights' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayNotHasKey( 'test_rest_disabled', $meta );
	}

	public function test_get_value_types() {
		register_meta(
			'post',
			'test_string',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_meta(
			'post',
			'test_number',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'number',
			)
		);
		register_meta(
			'post',
			'test_bool',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'boolean',
			)
		);

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = new Spy_REST_Server;
		do_action( 'rest_api_init', $wp_rest_server );

		add_post_meta( self::$post_id, 'test_string', 42 );
		add_post_meta( self::$post_id, 'test_number', '42' );
		add_post_meta( self::$post_id, 'test_bool', 1 );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
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

	public function test_get_value_custom_name() {
		add_post_meta( self::$post_id, 'test_custom_name', 'janet' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'new_name', $meta );
		$this->assertSame( 'janet', $meta['new_name'] );
	}

	/**
	 * @depends test_get_value
	 */
	public function test_set_value() {
		// Ensure no data exists currently.
		$values = get_post_meta( self::$post_id, 'test_single', false );
		$this->assertEmpty( $values );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_single' => 'test_value',
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_single', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertSame( 'test_value', $meta[0] );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_single', $meta );
		$this->assertSame( 'test_value', $meta['test_single'] );
	}

	/**
	 * @depends test_get_value
	 */
	public function test_set_duplicate_single_value() {
		// Start with an existing metakey and value.
		$values = update_post_meta( self::$post_id, 'test_single', 'test_value' );
		$this->assertSame( 'test_value', get_post_meta( self::$post_id, 'test_single', true ) );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_single' => 'test_value',
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_single', true );
		$this->assertNotEmpty( $meta );
		$this->assertSame( 'test_value', $meta );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_single', $meta );
		$this->assertSame( 'test_value', $meta['test_single'] );
	}

	/**
	 * @depends test_set_value
	 */
	public function test_set_value_unauthenticated() {
		$data = array(
			'meta' => array(
				'test_single' => 'test_value',
			),
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 401 );

		// Check that the value wasn't actually updated.
		$this->assertEmpty( get_post_meta( self::$post_id, 'test_single', false ) );
	}

	/**
	 * @depends test_set_value
	 */
	public function test_set_value_blocked() {
		$data = array(
			'meta' => array(
				'test_bad_auth' => 'test_value',
			),
		);

		$this->grant_write_permission();

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_update', $response, 403 );
		$this->assertEmpty( get_post_meta( self::$post_id, 'test_bad_auth', false ) );
	}

	/**
	 * @depends test_set_value
	 */
	public function test_set_value_db_error() {
		$data = array(
			'meta' => array(
				'test_single' => 'test_value',
			),
		);

		$this->grant_write_permission();

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		/**
		 * Disable showing error as the below is going to intentionally
		 * trigger a DB error.
		 */
		global $wpdb;
		$wpdb->suppress_errors = true;
		add_filter( 'query', array( $this, 'error_insert_query' ) );

		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'query', array( $this, 'error_insert_query' ) );
		$wpdb->show_errors = true;

		$this->assertErrorResponse( 'rest_meta_database_error', $response, 500 );
	}

	public function test_set_value_invalid_type() {
		$values = get_post_meta( self::$post_id, 'test_invalid_type', false );
		$this->assertEmpty( $values );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_invalid_type' => 'test_value',
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEmpty( get_post_meta( self::$post_id, 'test_invalid_type', false ) );
	}

	public function test_set_value_multiple() {
		// Ensure no data exists currently.
		$values = get_post_meta( self::$post_id, 'test_multi', false );
		$this->assertEmpty( $values );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_multi' => array( 'val1' ),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_multi', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertSame( 'val1', $meta[0] );

		// Add another value.
		$data = array(
			'meta' => array(
				'test_multi' => array( 'val1', 'val2' ),
			),
		);
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_multi', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 2, $meta );
		$this->assertContains( 'val1', $meta );
		$this->assertContains( 'val2', $meta );
	}

	/**
	 * Test removing only one item with duplicate items.
	 */
	public function test_set_value_remove_one() {
		add_post_meta( self::$post_id, 'test_multi', 'c' );
		add_post_meta( self::$post_id, 'test_multi', 'n' );
		add_post_meta( self::$post_id, 'test_multi', 'n' );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_multi' => array( 'c', 'n' ),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_multi', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 2, $meta );
		$this->assertContains( 'c', $meta );
		$this->assertContains( 'n', $meta );
	}

	/**
	 * @depends test_set_value_multiple
	 */
	public function test_set_value_multiple_unauthenticated() {
		// Ensure no data exists currently.
		$values = get_post_meta( self::$post_id, 'test_multi', false );
		$this->assertEmpty( $values );

		wp_set_current_user( 0 );

		$data    = array(
			'meta' => array(
				'test_multi' => array( 'val1' ),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 401 );

		$meta = get_post_meta( self::$post_id, 'test_multi', false );
		$this->assertEmpty( $meta );
	}

	public function test_set_value_invalid_value() {
		register_meta(
			'post',
			'my_meta_key',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'my_meta_key' => array( 'c', 'n' ),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_type', $response, 400 );
	}

	public function test_set_value_invalid_value_multiple() {
		register_meta(
			'post',
			'my_meta_key',
			array(
				'show_in_rest' => true,
				'single'       => false,
				'type'         => 'string',
			)
		);

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'my_meta_key' => array( array( 'a' ) ),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_type', $response, 400 );
	}

	public function test_set_value_sanitized() {
		register_meta(
			'post',
			'my_meta_key',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'integer',
			)
		);

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'my_meta_key' => '1', // Set to a string.
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 1, $data['meta']['my_meta_key'] );
	}

	public function test_set_value_csv() {
		register_meta(
			'post',
			'my_meta_key',
			array(
				'show_in_rest' => true,
				'single'       => false,
				'type'         => 'integer',
			)
		);

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'my_meta_key' => '1,2,3', // Set to a string.
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array( 1, 2, 3 ), $data['meta']['my_meta_key'] );
	}

	/**
	 * @depends test_set_value_multiple
	 */
	public function test_set_value_multiple_blocked() {
		$data = array(
			'meta' => array(
				'test_bad_auth_multi' => array( 'test_value' ),
			),
		);

		$this->grant_write_permission();

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_update', $response, 403 );
		$this->assertEmpty( get_post_meta( self::$post_id, 'test_bad_auth_multi', false ) );
	}

	public function test_add_multi_value_db_error() {
		// Ensure no data exists currently.
		$values = get_post_meta( self::$post_id, 'test_multi', false );
		$this->assertEmpty( $values );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_multi' => array( 'val1' ),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		/**
		 * Disable showing error as the below is going to intentionally
		 * trigger a DB error.
		 */
		global $wpdb;
		$wpdb->suppress_errors = true;
		add_filter( 'query', array( $this, 'error_insert_query' ) );

		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'query', array( $this, 'error_insert_query' ) );
		$wpdb->show_errors = true;

		$this->assertErrorResponse( 'rest_meta_database_error', $response, 500 );
	}

	/**
	 * @depends test_get_value
	 */
	public function test_set_value_single_custom_schema() {
		// Ensure no data exists currently.
		$values = get_post_meta( self::$post_id, 'test_custom_schema', false );
		$this->assertEmpty( $values );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_custom_schema' => 3,
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_custom_schema', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 3, $meta[0] );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_custom_schema', $meta );
		$this->assertEquals( 3, $meta['test_custom_schema'] );
	}

	public function test_set_value_multiple_custom_schema() {
		// Ensure no data exists currently.
		$values = get_post_meta( self::$post_id, 'test_custom_schema_multi', false );
		$this->assertEmpty( $values );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_custom_schema_multi' => array( 2 ),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_custom_schema_multi', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 2, $meta[0] );

		// Add another value.
		$data = array(
			'meta' => array(
				'test_custom_schema_multi' => array( 2, 8 ),
			),
		);
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_custom_schema_multi', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 2, $meta );
		$this->assertContains( 2, $meta );
		$this->assertContains( 8, $meta );
	}

	/**
	 * @depends test_get_value_custom_name
	 */
	public function test_set_value_custom_name() {
		// Ensure no data exists currently.
		$values = get_post_meta( self::$post_id, 'test_custom_name', false );
		$this->assertEmpty( $values );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'new_name' => 'janet',
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_custom_name', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertSame( 'janet', $meta[0] );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'new_name', $meta );
		$this->assertSame( 'janet', $meta['new_name'] );
	}

	public function test_set_value_custom_name_multiple() {
		// Ensure no data exists currently.
		$values = get_post_meta( self::$post_id, 'test_custom_name_multi', false );
		$this->assertEmpty( $values );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'new_name_multi' => array( 'janet' ),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_custom_name_multi', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertSame( 'janet', $meta[0] );

		// Add another value.
		$data = array(
			'meta' => array(
				'new_name_multi' => array( 'janet', 'graeme' ),
			),
		);
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_custom_name_multi', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 2, $meta );
		$this->assertContains( 'janet', $meta );
		$this->assertContains( 'graeme', $meta );
	}

	/**
	 * @ticket 38989
	 */
	public function test_set_value_invalid_meta_string_request_type() {
		update_post_meta( self::$post_id, 'test_single', 'So I tied an onion to my belt, which was the style at the time.' );
		$post_original = get_post( self::$post_id );

		$this->grant_write_permission();

		$data = array(
			'title' => 'Ignore this title',
			'meta'  => 'Not an array.',
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// The meta value should not have changed.
		$current_value = get_post_meta( self::$post_id, 'test_single', true );
		$this->assertSame( 'So I tied an onion to my belt, which was the style at the time.', $current_value );

		// Ensure the post title update was not processed.
		$post_updated = get_post( self::$post_id );
		$this->assertSame( $post_original->post_title, $post_updated->post_title );
	}

	/**
	 * @ticket 38989
	 */
	public function test_set_value_invalid_meta_float_request_type() {
		update_post_meta( self::$post_id, 'test_single', 'Now, to take the ferry cost a nickel, and in those days, nickels had pictures of bumblebees on them.' );
		$post_original = get_post( self::$post_id );

		$this->grant_write_permission();

		$data = array(
			'content' => 'Ignore this content.',
			'meta'    => 1.234,
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// The meta value should not have changed.
		$current_value = get_post_meta( self::$post_id, 'test_single', true );
		$this->assertSame( 'Now, to take the ferry cost a nickel, and in those days, nickels had pictures of bumblebees on them.', $current_value );

		// Ensure the post content update was not processed.
		$post_updated = get_post( self::$post_id );
		$this->assertSame( $post_original->post_content, $post_updated->post_content );
	}

	/**
	 * @ticket 50790
	 */
	public function test_remove_multi_value_with_empty_array() {
		add_post_meta( self::$post_id, 'test_multi', 'val1' );
		$values = get_post_meta( self::$post_id, 'test_multi', false );
		$this->assertSame( array( 'val1' ), $values );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_multi' => array(),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_multi', false );
		$this->assertEmpty( $meta );
	}

	/**
	 * Ensure deleting non-existent meta data behaves gracefully.
	 *
	 * @ticket 52787
	 * @dataProvider data_delete_does_not_trigger_error_if_no_meta_values
	 *
	 * @param array|null $delete_value Value used to delete meta data.
	 */
	public function test_delete_does_not_trigger_error_if_no_meta_values( $delete_value ) {
		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_multi' => $delete_value,
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Data provider for test_delete_does_not_trigger_error_if_no_meta_values().
	 *
	 * @return array[] Array of test parameters.
	 */
	public function data_delete_does_not_trigger_error_if_no_meta_values() {
		return array(
			array( array() ),
			array( null ),
		);
	}

	public function test_remove_multi_value_db_error() {
		add_post_meta( self::$post_id, 'test_multi', 'val1' );
		$values = get_post_meta( self::$post_id, 'test_multi', false );
		$this->assertSame( array( 'val1' ), $values );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_multi' => array(),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		/**
		 * Disable showing error as the below is going to intentionally
		 * trigger a DB error.
		 */
		global $wpdb;
		$wpdb->suppress_errors = true;
		add_filter( 'query', array( $this, 'error_delete_query' ) );

		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'query', array( $this, 'error_delete_query' ) );
		$wpdb->show_errors = true;

		$this->assertErrorResponse( 'rest_meta_database_error', $response, 500 );
	}


	public function test_delete_value() {
		add_post_meta( self::$post_id, 'test_single', 'val1' );
		$current = get_post_meta( self::$post_id, 'test_single', true );
		$this->assertSame( 'val1', $current );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_single' => null,
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_single', false );
		$this->assertEmpty( $meta );
	}

	/**
	 * @depends test_delete_value
	 */
	public function test_delete_value_blocked() {
		add_post_meta( self::$post_id, 'test_bad_auth', 'val1' );
		$current = get_post_meta( self::$post_id, 'test_bad_auth', true );
		$this->assertSame( 'val1', $current );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_bad_auth' => null,
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );

		$meta = get_post_meta( self::$post_id, 'test_bad_auth', true );
		$this->assertSame( 'val1', $meta );
	}

	/**
	 * @depends test_delete_value
	 */
	public function test_delete_value_db_error() {
		add_post_meta( self::$post_id, 'test_single', 'val1' );
		$current = get_post_meta( self::$post_id, 'test_single', true );
		$this->assertSame( 'val1', $current );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'test_single' => null,
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );
		/**
		 * Disable showing error as the below is going to intentionally
		 * trigger a DB error.
		 */
		global $wpdb;
		$wpdb->suppress_errors = true;
		add_filter( 'query', array( $this, 'error_delete_query' ) );

		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'query', array( $this, 'error_delete_query' ) );
		$wpdb->show_errors = true;

		$this->assertErrorResponse( 'rest_meta_database_error', $response, 500 );
	}

	public function test_delete_value_custom_name() {
		add_post_meta( self::$post_id, 'test_custom_name', 'janet' );
		$current = get_post_meta( self::$post_id, 'test_custom_name', true );
		$this->assertSame( 'janet', $current );

		$this->grant_write_permission();

		$data    = array(
			'meta' => array(
				'new_name' => null,
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$meta = get_post_meta( self::$post_id, 'test_custom_name', false );
		$this->assertEmpty( $meta );
	}

	public function test_get_schema() {
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$data   = $response->get_data();
		$schema = $data['schema'];

		$this->assertArrayHasKey( 'meta', $schema['properties'] );
		$meta_schema = $schema['properties']['meta']['properties'];

		$this->assertArrayHasKey( 'test_single', $meta_schema );
		$this->assertSame( 'string', $meta_schema['test_single']['type'] );

		$this->assertArrayHasKey( 'test_multi', $meta_schema );
		$this->assertSame( 'array', $meta_schema['test_multi']['type'] );
		$this->assertArrayHasKey( 'items', $meta_schema['test_multi'] );
		$this->assertSame( 'string', $meta_schema['test_multi']['items']['type'] );

		$this->assertArrayHasKey( 'test_custom_schema', $meta_schema );
		$this->assertSame( 'number', $meta_schema['test_custom_schema']['type'] );

		$this->assertArrayNotHasKey( 'test_no_rest', $meta_schema );
		$this->assertArrayNotHasKey( 'test_rest_disabled', $meta_schema );
		$this->assertArrayNotHasKey( 'test_invalid_type', $meta_schema );
		$this->assertArrayNotHasKey( 'test_no_type', $meta_schema );
	}

	/**
	 * @ticket 38323
	 *
	 * @dataProvider data_get_subtype_meta_value
	 */
	public function test_get_subtype_meta_value( $post_type, $meta_key, $single, $in_post_type ) {
		$post_id  = self::$post_id;
		$endpoint = 'posts';
		if ( 'cpt' === $post_type ) {
			$post_id  = self::$cpt_post_id;
			$endpoint = 'cpt';
		}

		$meta_value = 'testvalue';

		add_post_meta( $post_id, $meta_key, $meta_value );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/%s/%d', $endpoint, $post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'meta', $data );
		$this->assertIsArray( $data['meta'] );

		if ( $in_post_type ) {
			$expected_value = $meta_value;
			if ( ! $single ) {
				$expected_value = array( $expected_value );
			}

			$this->assertArrayHasKey( $meta_key, $data['meta'] );
			$this->assertSame( $expected_value, $data['meta'][ $meta_key ] );
		} else {
			$this->assertArrayNotHasKey( $meta_key, $data['meta'] );
		}
	}

	public function data_get_subtype_meta_value() {
		return array(
			array( 'cpt', 'test_cpt_single', true, true ),
			array( 'cpt', 'test_cpt_multi', false, true ),
			array( 'cpt', 'test_single', true, true ),
			array( 'cpt', 'test_multi', false, true ),
			array( 'post', 'test_cpt_single', true, false ),
			array( 'post', 'test_cpt_multi', false, false ),
			array( 'post', 'test_single', true, true ),
			array( 'post', 'test_multi', false, true ),
		);
	}

	/**
	 * @ticket 38323
	 *
	 * @dataProvider data_set_subtype_meta_value
	 */
	public function test_set_subtype_meta_value( $post_type, $meta_key, $single, $in_post_type, $can_write ) {
		$post_id  = self::$post_id;
		$endpoint = 'posts';
		if ( 'cpt' === $post_type ) {
			$post_id  = self::$cpt_post_id;
			$endpoint = 'cpt';
		}

		$meta_value = 'value_to_set';

		$this->grant_write_permission();

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/%s/%d', $endpoint, $post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					$meta_key => $meta_value,
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		if ( ! $can_write ) {
			$this->assertSame( 403, $response->get_status() );
			$this->assertEmpty( get_post_meta( $post_id, $meta_key, $single ) );

			return;
		}

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertIsArray( $data['meta'] );

		if ( $in_post_type ) {
			$expected_value = $meta_value;
			if ( ! $single ) {
				$expected_value = array( $expected_value );
			}

			$this->assertSame( $expected_value, get_post_meta( $post_id, $meta_key, $single ) );
			$this->assertArrayHasKey( $meta_key, $data['meta'] );
			$this->assertSame( $expected_value, $data['meta'][ $meta_key ] );
		} else {
			$this->assertEmpty( get_post_meta( $post_id, $meta_key, $single ) );
			$this->assertArrayNotHasKey( $meta_key, $data['meta'] );
		}
	}

	public function data_set_subtype_meta_value() {
		$data = $this->data_get_subtype_meta_value();

		foreach ( $data as $index => $dataset ) {
			$can_write = true;

			// This combination is not writable because of an auth callback of '__return_false'.
			if ( 'cpt' === $dataset[0] && 'test_single' === $dataset[1] ) {
				$can_write = false;
			}

			$data[ $index ][] = $can_write;
		}

		return $data;
	}

	/**
	 * @ticket 42069
	 *
	 * @dataProvider data_update_value_return_success_with_same_value
	 */
	public function test_update_value_return_success_with_same_value( $meta_key, $meta_value ) {
		add_post_meta( self::$post_id, $meta_key, $meta_value );

		$this->grant_write_permission();

		$data = array(
			'meta' => array(
				$meta_key => $meta_value,
			),
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params( $data );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function data_update_value_return_success_with_same_value() {
		return array(
			array( 'test_boolean_update', false ),
			array( 'test_boolean_update', true ),
			array( 'test_textured_text_update', 'She said, "What about the > 10,000 penguins in the kitchen?"' ),
			array( 'test_textured_text_update', "He's about to do something rash..." ),
			array( 'test_json_encoded', json_encode( array( 'foo' => 'bar' ) ) ),
			array( 'test\'slashed\'key', 'Hello' ),
		);
	}

	/**
	 * @ticket 42069
	 */
	public function test_slashed_meta_key() {

		add_post_meta( self::$post_id, 'test\'slashed\'key', 'Hello' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'test\'slashed\'key', $data['meta'] );
		$this->assertSame( 'Hello', $data['meta']['test\'slashed\'key'] );
	}

	/**
	 * @ticket 43392
	 */
	public function test_object_single() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'object',
			array(
				'single'       => true,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'object' => array(
						'project' => 'WordPress',
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'object', $data['meta'] );
		$this->assertArrayHasKey( 'project', $data['meta']['object'] );
		$this->assertSame( 'WordPress', $data['meta']['object']['project'] );

		$meta = get_post_meta( self::$post_id, 'object', true );
		$this->assertArrayHasKey( 'project', $meta );
		$this->assertSame( 'WordPress', $meta['project'] );
	}

	/**
	 * @ticket 43392
	 */
	public function test_object_multiple() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'object',
			array(
				'single'       => false,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'object' => array(
						array(
							'project' => 'WordPress',
						),
						array(
							'project' => 'bbPress',
						),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'object', $data['meta'] );
		$this->assertCount( 2, $data['meta']['object'] );

		$this->assertArrayHasKey( 'project', $data['meta']['object'][0] );
		$this->assertSame( 'WordPress', $data['meta']['object'][0]['project'] );

		$this->assertArrayHasKey( 'project', $data['meta']['object'][1] );
		$this->assertSame( 'bbPress', $data['meta']['object'][1]['project'] );

		$meta = get_post_meta( self::$post_id, 'object' );

		$this->assertCount( 2, $meta );

		$this->assertArrayHasKey( 'project', $meta[0] );
		$this->assertSame( 'WordPress', $meta[0]['project'] );

		$this->assertArrayHasKey( 'project', $meta[1] );
		$this->assertSame( 'bbPress', $meta[1]['project'] );
	}

	/**
	 * @ticket 43392
	 */
	public function test_array_single() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'list',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'list' => array( 'WordPress', 'bbPress' ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'list', $data['meta'] );
		$this->assertSame( array( 'WordPress', 'bbPress' ), $data['meta']['list'] );

		$meta = get_post_meta( self::$post_id, 'list', true );
		$this->assertSame( array( 'WordPress', 'bbPress' ), $meta );
	}

	/**
	 * @ticket 43392
	 */
	public function test_array_of_objects_multiple() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'list_of_objects',
			array(
				'single'       => false,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'version' => array(
									'type' => 'string',
								),
								'artist'  => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'list_of_objects' => array(
						// Meta 1.
						array(
							array(
								'version' => '5.2',
								'artist'  => 'Jaco',
							),
							array(
								'version' => '5.1',
								'artist'  => 'Betty',
							),
						),
						// Meta 2.
						array(
							array(
								'version' => '4.9',
								'artist'  => 'Tipton',
							),
						),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'list_of_objects', $data['meta'] );
		$this->assertCount( 2, $data['meta']['list_of_objects'] );

		$this->assertSame(
			array(
				array(
					'version' => '5.2',
					'artist'  => 'Jaco',
				),
				array(
					'version' => '5.1',
					'artist'  => 'Betty',
				),
			),
			$data['meta']['list_of_objects'][0]
		);

		$this->assertSame(
			array(
				array(
					'version' => '4.9',
					'artist'  => 'Tipton',
				),
			),
			$data['meta']['list_of_objects'][1]
		);

		$meta = get_post_meta( self::$post_id, 'list_of_objects' );

		$this->assertCount( 2, $meta );

		$this->assertSame(
			array(
				array(
					'version' => '5.2',
					'artist'  => 'Jaco',
				),
				array(
					'version' => '5.1',
					'artist'  => 'Betty',
				),
			),
			$meta[0]
		);

		$this->assertSame(
			array(
				array(
					'version' => '4.9',
					'artist'  => 'Tipton',
				),
			),
			$meta[1]
		);
	}

	/**
	 * @ticket 43392
	 */
	public function test_php_objects_returned_as_null() {
		register_post_meta(
			'post',
			'object',
			array(
				'single'       => true,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$basic          = new Basic_Object();
		$basic->project = 'WordPress';
		update_post_meta( self::$post_id, 'object', $basic );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'object', $data['meta'] );
		$this->assertNull( $data['meta']['object'] );
	}

	/**
	 * @ticket 43392
	 */
	public function test_php_objects_returned_as_null_multiple() {
		register_post_meta(
			'post',
			'object',
			array(
				'single'       => false,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$basic          = new Basic_Object();
		$basic->project = 'WordPress';
		add_post_meta( self::$post_id, 'object', array( 'project' => 'bbPress' ) );
		add_post_meta( self::$post_id, 'object', $basic );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'object', $data['meta'] );
		$this->assertCount( 2, $data['meta']['object'] );
		$this->assertSame( array( 'project' => 'bbPress' ), $data['meta']['object'][0] );
		$this->assertNull( $data['meta']['object'][1] );
	}

	/**
	 * @ticket 43392
	 */
	public function test_php_jsonserializable_object_returns_value() {
		require_once __DIR__ . '/../../includes/class-jsonserializable-object.php';

		register_post_meta(
			'post',
			'object',
			array(
				'single'       => true,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		update_post_meta( self::$post_id, 'object', new JsonSerializable_Object( array( 'project' => 'WordPress' ) ) );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'object', $data['meta'] );
		$this->assertSame( array( 'project' => 'WordPress' ), $data['meta']['object'] );
	}

	/**
	 * @ticket 43392
	 */
	public function test_updating_meta_to_null_for_key_with_existing_php_object_does_not_delete_meta_value() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'object',
			array(
				'single'       => true,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$basic          = new Basic_Object();
		$basic->project = 'WordPress';
		update_post_meta( self::$post_id, 'object', $basic );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'object' => null,
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 500, $response->get_status() );
	}

	/**
	 * @ticket 43392
	 */
	public function test_updating_non_single_meta_to_null_for_key_with_existing_php_object_does_not_set_meta_value_to_null() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'object',
			array(
				'single'       => false,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$basic          = new Basic_Object();
		$basic->project = 'WordPress';
		add_post_meta( self::$post_id, 'object', array( 'project' => 'bbPress' ) );
		add_post_meta( self::$post_id, 'object', $basic );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'object' => array(
						array( 'project' => 'BuddyPress' ),
						null,
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 500, $response->get_status() );
	}

	/**
	 * @ticket 43392
	 */
	public function test_object_rejects_additional_properties_by_default() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'object',
			array(
				'single'       => true,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'object' => array(
						'project'     => 'BuddyPress',
						'awesomeness' => 100,
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * @ticket 43392
	 */
	public function test_object_allows_additional_properties_if_explicitly_set() {
		$this->grant_write_permission();

		$value = array(
			'project'     => 'BuddyPress',
			'awesomeness' => 100,
		);

		register_post_meta(
			'post',
			'object',
			array(
				'single'       => true,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'properties'           => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'object' => $value,
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $value, $response->get_data()['meta']['object'] );

		$this->assertSame( $value, get_post_meta( self::$post_id, 'object', true ) );
	}

	/**
	 * @ticket 43392
	 */
	public function test_object_allows_additional_properties_and_uses_its_schema() {
		$this->grant_write_permission();

		$value = array(
			'project'     => 'BuddyPress',
			'awesomeness' => 'fabulous',
		);

		register_post_meta(
			'post',
			'object',
			array(
				'single'       => true,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array(
							'type' => 'number',
						),
						'properties'           => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'object' => $value,
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * @ticket 43392
	 */
	public function test_invalid_meta_value_are_set_to_null_in_response() {
		register_post_meta(
			'post',
			'email',
			array(
				'single'       => true,
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'   => 'string',
						'format' => 'email',
					),
				),
			)
		);

		update_post_meta( self::$post_id, 'email', 'invalid_meta_value' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertNull( $response->get_data()['meta']['email'] );
	}

	/**
	 * @ticket 43392
	 * @ticket 48363
	 * @dataProvider _dp_meta_values_are_not_set_to_null_in_response_if_type_safely_serializable
	 */
	public function test_meta_values_are_not_set_to_null_in_response_if_type_safely_serializable( $type, $stored, $expected ) {
		register_post_meta(
			'post',
			'safe',
			array(
				'single'       => true,
				'show_in_rest' => true,
				'type'         => $type,
			)
		);

		update_post_meta( self::$post_id, 'safe', $stored );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( $expected, $response->get_data()['meta']['safe'] );
	}

	public function _dp_meta_values_are_not_set_to_null_in_response_if_type_safely_serializable() {
		return array(
			array( 'boolean', 'true', true ),
			array( 'boolean', 'false', false ),
			array( 'boolean', '1', true ),
			array( 'boolean', '0', false ),
			array( 'boolean', '', false ),
			array( 'integer', '', 0 ),
			array( 'integer', '1', 1 ),
			array( 'integer', '0', 0 ),
			array( 'number', '', 0.0 ),
			array( 'number', '1.1', 1.1 ),
			array( 'number', '0.0', 0.0 ),
			array( 'string', '', '' ),
			array( 'string', '1', '1' ),
			array( 'string', '0', '0' ),
			array( 'string', 'str', 'str' ),
		);
	}

	/**
	 * @ticket 43392
	 */
	public function test_update_multi_meta_value_object() {
		register_post_meta(
			'post',
			'object',
			array(
				'single'       => false,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		add_post_meta(
			self::$post_id,
			'object',
			array(
				'project' => 'WordPress',
			)
		);
		add_post_meta(
			self::$post_id,
			'object',
			array(
				'project' => 'bbPress',
			)
		);

		$this->grant_write_permission();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'object' => array(
						array( 'project' => 'WordPress' ),
						array( 'project' => 'BuddyPress' ),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'object', $data['meta'] );

		$this->assertCount( 2, $data['meta']['object'] );
		$this->assertSame( array( 'project' => 'WordPress' ), $data['meta']['object'][0] );
		$this->assertSame( array( 'project' => 'BuddyPress' ), $data['meta']['object'][1] );

		$meta = get_post_meta( self::$post_id, 'object' );
		$this->assertCount( 2, $meta );
		$this->assertSame( array( 'project' => 'WordPress' ), $meta[0] );
		$this->assertSame( array( 'project' => 'BuddyPress' ), $meta[1] );
	}

	/**
	 * @ticket 43392
	 */
	public function test_update_multi_meta_value_array() {
		register_post_meta(
			'post',
			'list',
			array(
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		add_post_meta( self::$post_id, 'list', array( 'WordPress', 'bbPress' ) );
		add_post_meta( self::$post_id, 'list', array( 'WordCamp' ) );

		$this->grant_write_permission();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'list' => array(
						array( 'WordPress', 'bbPress' ),
						array( 'BuddyPress' ),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'list', $data['meta'] );

		$this->assertCount( 2, $data['meta']['list'] );
		$this->assertSame( array( 'WordPress', 'bbPress' ), $data['meta']['list'][0] );
		$this->assertSame( array( 'BuddyPress' ), $data['meta']['list'][1] );

		$meta = get_post_meta( self::$post_id, 'list' );
		$this->assertCount( 2, $meta );
		$this->assertSame( array( 'WordPress', 'bbPress' ), $meta[0] );
		$this->assertSame( array( 'BuddyPress' ), $meta[1] );
	}

	/**
	 * @ticket 47928
	 */
	public function test_update_meta_with_unchanged_array_values() {
		register_post_meta(
			'post',
			'list',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		add_post_meta( self::$post_id, 'list', array( 'WordCamp' ) );

		$this->grant_write_permission();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'list' => array( 'WordCamp' ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( array( 'WordCamp' ), $data['meta']['list'] );
	}

	/**
	 * @ticket 47928
	 */
	public function test_update_meta_with_unchanged_object_values() {
		register_post_meta(
			'post',
			'object',
			array(
				'single'       => true,
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'project' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		add_post_meta( self::$post_id, 'object', array( 'project' => 'WordCamp' ) );

		$this->grant_write_permission();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'object' => array( 'project' => 'WordCamp' ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( array( 'project' => 'WordCamp' ), $data['meta']['object'] );
	}

	/**
	 * @ticket 43392
	 */
	public function test_register_meta_issues_doing_it_wrong_when_show_in_rest_is_true() {
		$this->setExpectedIncorrectUsage( 'register_meta' );

		$registered = register_meta(
			'post',
			'invalid_array',
			array(
				'type'         => 'array',
				'show_in_rest' => true,
			)
		);

		self::assertFalse( $registered );
	}

	/**
	 * @ticket 43392
	 */
	public function test_register_meta_issues_doing_it_wrong_when_show_in_rest_omits_schema() {
		$this->setExpectedIncorrectUsage( 'register_meta' );

		$registered = register_meta(
			'post',
			'invalid_array',
			array(
				'type'         => 'array',
				'show_in_rest' => array(
					'prepare_callback' => 'rest_sanitize_value_from_schema',
				),
			)
		);

		self::assertFalse( $registered );
	}

	/**
	 * @ticket 43392
	 */
	public function test_register_meta_issues_doing_it_wrong_when_show_in_rest_omits_schema_items() {
		$this->setExpectedIncorrectUsage( 'register_meta' );

		$registered = register_meta(
			'post',
			'invalid_array',
			array(
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'default' => array( 'Hi!' ),
					),
				),
			)
		);

		self::assertFalse( $registered );
	}

	/**
	 * @ticket 48264
	 */
	public function test_update_array_of_ints_meta() {
		$this->grant_write_permission();
		register_post_meta(
			'post',
			'items',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type' => 'integer',
						),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'items' => array( 1, 2, 3 ),
				),
			)
		);

		rest_get_server()->dispatch( $request );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @ticket 48264
	 */
	public function test_update_array_of_ints_meta_stored_strings_are_updated() {
		$this->grant_write_permission();
		register_post_meta(
			'post',
			'items',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type' => 'integer',
						),
					),
				),
			)
		);

		update_post_meta( self::$post_id, 'items', array( '1', '2', '3' ) );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) ) );
		$this->assertSame( array( 1, 2, 3 ), $response->get_data()['meta']['items'] );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'items' => array( 1, 2, 3 ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 1, 2, 3 ), get_post_meta( self::$post_id, 'items', true ) );
	}

	/**
	 * @ticket 48264
	 */
	public function test_update_array_of_ints_meta_string_request_data_is_set_as_ints() {
		$this->grant_write_permission();
		register_post_meta(
			'post',
			'items',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type' => 'integer',
						),
					),
				),
			)
		);

		update_post_meta( self::$post_id, 'items', array( 1, 2, 3 ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'items' => array( '1', '2', '3' ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 1, 2, 3 ), get_post_meta( self::$post_id, 'items', true ) );
	}

	/**
	 * @ticket 48264
	 */
	public function test_update_array_of_ints_meta_string_request_data_and_string_stored_data() {
		$this->grant_write_permission();
		register_post_meta(
			'post',
			'items',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type' => 'integer',
						),
					),
				),
			)
		);

		update_post_meta( self::$post_id, 'items', array( '1', '2', '3' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'items' => array( '1', '2', '3' ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 1, 2, 3 ), get_post_meta( self::$post_id, 'items', true ) );
	}

	/**
	 * @ticket 48264
	 */
	public function test_update_array_of_bools_meta() {
		$this->grant_write_permission();
		register_post_meta(
			'post',
			'items',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type' => 'boolean',
						),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'items' => array( true, false ),
				),
			)
		);

		rest_get_server()->dispatch( $request );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @ticket 48264
	 */
	public function test_update_array_of_bools_meta_stored_strings_are_updated() {
		$this->grant_write_permission();
		register_post_meta(
			'post',
			'items',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type' => 'boolean',
						),
					),
				),
			)
		);

		update_post_meta( self::$post_id, 'items', array( '1', '0' ) );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) ) );
		$this->assertSame( array( true, false ), $response->get_data()['meta']['items'] );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'items' => array( true, false ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( true, false ), get_post_meta( self::$post_id, 'items', true ) );
	}

	/**
	 * @ticket 48264
	 */
	public function test_update_array_of_bools_meta_string_request_data_is_set_as_bools() {
		$this->grant_write_permission();
		register_post_meta(
			'post',
			'items',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type' => 'boolean',
						),
					),
				),
			)
		);

		update_post_meta( self::$post_id, 'items', array( true, false ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'items' => array( '1', '0' ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( true, false ), get_post_meta( self::$post_id, 'items', true ) );
	}

	/**
	 * @ticket 48264
	 */
	public function test_update_array_of_bools_meta_string_request_data_and_string_stored_data() {
		$this->grant_write_permission();
		register_post_meta(
			'post',
			'items',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type' => 'boolean',
						),
					),
				),
			)
		);

		update_post_meta( self::$post_id, 'items', array( '1', '0' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'items' => array( '1', '0' ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( true, false ), get_post_meta( self::$post_id, 'items', true ) );
	}

	/**
	 * @ticket 48264
	 */
	public function test_update_array_of_bools_with_string_values_stored_and_opposite_request_data() {
		$this->grant_write_permission();
		register_post_meta(
			'post',
			'items',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type' => 'boolean',
						),
					),
				),
			)
		);

		update_post_meta( self::$post_id, 'items', array( '1', '0' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'items' => array( false, true ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( false, true ), get_post_meta( self::$post_id, 'items', true ) );
	}

	/**
	 * @ticket 48363
	 */
	public function test_boolean_meta_update_to_false_stores_0() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'boolean',
			array(
				'single'            => true,
				'type'              => 'boolean',
				'show_in_rest'      => true,
				'sanitize_callback' => function( $value ) {
					return $value ? '1' : '0';
				},
			)
		);

		update_post_meta( self::$post_id, 'boolean', 1 );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'boolean' => false,
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '0', get_post_meta( self::$post_id, 'boolean', true ) );
	}

	/**
	 * @ticket 49339
	 */
	public function test_update_multi_meta_value_handles_integer_types() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'multi_integer',
			array(
				'type'         => 'integer',
				'show_in_rest' => true,
			)
		);

		$mid1 = add_post_meta( self::$post_id, 'multi_integer', 1 );
		$mid2 = add_post_meta( self::$post_id, 'multi_integer', 2 );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'multi_integer' => array( 2, 3 ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 2, 3 ), $response->get_data()['meta']['multi_integer'] );

		$this->assertFalse( get_metadata_by_mid( 'post', $mid1 ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $mid2 ) );
	}

	/**
	 * @ticket 49339
	 */
	public function test_update_multi_meta_value_handles_boolean_types() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'multi_boolean',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		);

		$mid1 = add_post_meta( self::$post_id, 'multi_boolean', 1 );
		$mid2 = add_post_meta( self::$post_id, 'multi_boolean', 0 );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'multi_boolean' => array( 0 ),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertEquals( array( 0 ), $response->get_data()['meta']['multi_boolean'] );

		$this->assertFalse( get_metadata_by_mid( 'post', $mid1 ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $mid2 ) );
	}

	/**
	 * @ticket 49339
	 */
	public function test_update_multi_meta_value_handles_object_types() {
		$this->grant_write_permission();

		register_post_meta(
			'post',
			'multi_object',
			array(
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'a' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$mid1 = add_post_meta( self::$post_id, 'multi_object', array( 'a' => 'ant' ) );
		$mid2 = add_post_meta( self::$post_id, 'multi_object', array( 'a' => 'anaconda' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'meta' => array(
					'multi_object' => array(
						array( 'a' => 'anaconda' ),
						array( 'a' => 'alpaca' ),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				array( 'a' => 'anaconda' ),
				array( 'a' => 'alpaca' ),
			),
			$response->get_data()['meta']['multi_object']
		);

		$this->assertFalse( get_metadata_by_mid( 'post', $mid1 ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $mid2 ) );
	}

	/**
	 * @ticket 43941
	 * @dataProvider data_get_default_data
	 */
	public function test_get_default_value( $args, $expected ) {
		$object_type = 'post';
		$meta_key    = 'registered_key1';
		$registered  = register_meta(
			$object_type,
			$meta_key,
			$args
		);

		$this->assertTrue( $registered );

		// Check for default value.
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( $meta_key, $meta );
		$this->assertSame( $expected, $meta[ $meta_key ] );
	}

	public function data_get_default_data() {
		return array(
			array(
				array(
					'show_in_rest' => true,
					'single'       => true,
					'default'      => 'wibble',
				),
				'wibble',
			),
			array(
				array(
					'show_in_rest' => true,
					'single'       => false,
					'default'      => 'wibble',
				),
				array( 'wibble' ),
			),
			array(
				array(
					'show_in_rest'   => true,
					'single'         => true,
					'object_subtype' => 'post',
					'default'        => 'wibble',
				),
				'wibble',
			),
			array(
				array(
					'show_in_rest'   => true,
					'single'         => false,
					'object_subtype' => 'post',
					'default'        => 'wibble',
				),
				array( 'wibble' ),
			),
			array(
				array(
					'single'       => true,
					'show_in_rest' => array(
						'schema' => array(
							'type'       => 'object',
							'properties' => array(
								'wibble' => array(
									'type' => 'string',
								),
							),
						),
					),
					'type'         => 'object',
					'default'      => array( 'wibble' => 'dibble' ),
				),
				array( 'wibble' => 'dibble' ),
			),
			array(
				array(
					'show_in_rest' => array(
						'schema' => array(
							'type'       => 'object',
							'properties' => array(
								'wibble' => array(
									'type' => 'string',
								),
							),
						),
					),
					'type'         => 'object',
					'single'       => false,
					'default'      => array( 'wibble' => 'dibble' ),
				),
				array( array( 'wibble' => 'dibble' ) ),
			),

			array(
				array(
					'show_in_rest' => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
							),
						),
					),
					'single'       => true,
					'type'         => 'array',
					'default'      => array( 'dibble' ),
				),
				array( 'dibble' ),
			),
			array(
				array(
					'show_in_rest' => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
							),
						),
					),
					'single'       => false,
					'type'         => 'array',
					'default'      => array( 'dibble' ),
				),
				array( array( 'dibble' ) ),
			),
			'array of objects' => array(
				array(
					'type'         => 'array',
					'single'       => true,
					'show_in_rest' => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name' => array(
										'type' => 'string',
									),
								),
							),
						),
					),
					'default'      => array(
						array(
							'name' => 'Kirk',
						),
					),
				),
				array(
					array(
						'name' => 'Kirk',
					),
				),
			),
		);
	}

	/**
	 * @ticket 43941
	 */
	public function test_set_default_in_schema() {
		register_post_meta(
			'post',
			'greeting',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => array(
					'schema' => array(
						'default' => 'Hello World',
					),
				),
			)
		);

		$response = rest_do_request( '/wp/v2/posts/' . self::$post_id );
		$this->assertSame( 'Hello World', $response->get_data()['meta']['greeting'] );
	}

	/**
	 * @ticket 43941
	 */
	public function test_default_is_added_to_schema() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' );
		$response = rest_do_request( $request );

		$schema = $response->get_data()['schema']['properties']['meta']['properties']['with_default'];
		$this->assertArrayHasKey( 'default', $schema );
		$this->assertSame( 'Goodnight Moon', $schema['default'] );
	}

	/**
	 * Internal function used to disable an insert query which
	 * will trigger a wpdb error for testing purposes.
	 */
	public function error_insert_query( $query ) {
		if ( strpos( $query, 'INSERT' ) === 0 ) {
			$query = '],';
		}
		return $query;
	}

	/**
	 * Internal function used to disable an insert query which
	 * will trigger a wpdb error for testing purposes.
	 */
	public function error_delete_query( $query ) {
		if ( strpos( $query, 'DELETE' ) === 0 ) {
			$query = '],';
		}
		return $query;
	}
}
