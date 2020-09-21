<?php
/**
 * Unit tests covering WP_REST_Application_Passwords_Controller functionality.
 *
 * @package    WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_Test_REST_Application_Passwords_Controller extends WP_Test_REST_Controller_Testcase {

	/**
	 * Subscriber user ID.
	 *
	 * @since ?.?.0
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * Administrator user id.
	 *
	 * @since ?.?.0
	 *
	 * @var int
	 */
	private static $admin;

	/**
	 * Set up class test fixtures.
	 *
	 * @since ?.?.0
	 *
	 * @param WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		self::$admin         = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
	}

	/**
	 * Clean up test fixtures.
	 *
	 * @since ?.?.0
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$subscriber_id );
		self::delete_user( self::$admin );
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/users/(?P<user_id>(?:[\\d]+|me))/application-passwords', $routes );
		$this->assertCount( 3, $routes['/wp/v2/users/(?P<user_id>(?:[\\d]+|me))/application-passwords'] );
		$this->assertArrayHasKey( '/wp/v2/users/(?P<user_id>(?:[\\d]+|me))/application-passwords/(?P<slug>[\\da-fA-F]{12})', $routes );
		$this->assertCount( 2, $routes['/wp/v2/users/(?P<user_id>(?:[\\d]+|me))/application-passwords/(?P<slug>[\\da-fA-F]{12})'] );
	}

	public function test_context_param() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'New App' );

		$slug = WP_Application_Passwords::password_unique_slug( $item );

		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/users/me/application-passwords' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/users/me/application-passwords/' . $slug );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_get_items() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$response = rest_do_request( '/wp/v2/users/me/application-passwords' );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$this->check_response( $response->get_data()[0], $item );
	}

	public function test_get_items_self_user_id_admin() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$response = rest_do_request( sprintf( '/wp/v2/users/%d/application-passwords', self::$admin ) );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$this->check_response( $response->get_data()[0], $item );
	}

	public function test_get_items_self_user_id_subscriber() {
		wp_set_current_user( self::$subscriber_id );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$subscriber_id, 'App' );

		$response = rest_do_request( sprintf( '/wp/v2/users/%d/application-passwords', self::$subscriber_id ) );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$this->check_response( $response->get_data()[0], $item );
	}

	public function test_get_items_other_user_id() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$subscriber_id, 'App' );

		$response = rest_do_request( sprintf( '/wp/v2/users/%d/application-passwords', self::$subscriber_id ) );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$this->check_response( $response->get_data()[0], $item );
	}

	public function test_get_items_other_user_id_invalid_permission() {
		wp_set_current_user( self::$subscriber_id );

		$response = rest_do_request( sprintf( '/wp/v2/users/%d/application-passwords', self::$admin ) );
		$this->assertErrorResponse( 'rest_cannot_manage_application_passwords', $response, 403 );
	}

	public function test_get_items_logged_out() {
		$response = rest_do_request( '/wp/v2/users/me/application-passwords' );
		$this->assertErrorResponse( 'rest_not_logged_in', $response, 401 );
	}

	public function test_get_items_invalid_user_id() {
		wp_set_current_user( self::$admin );

		$response = rest_do_request( '/wp/v2/users/0/application-passwords' );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_get_item() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$response = rest_do_request( '/wp/v2/users/me/application-passwords/' . $slug );
		$this->assertEquals( 200, $response->get_status() );
		$this->check_response( $response->get_data(), $item );
	}

	public function test_get_item_self_user_id_admin() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$response = rest_do_request( sprintf( '/wp/v2/users/%d/application-passwords/%s', self::$admin, $slug ) );
		$this->assertEquals( 200, $response->get_status() );
		$this->check_response( $response->get_data(), $item );
	}

	public function test_get_item_self_user_id_subscriber() {
		wp_set_current_user( self::$subscriber_id );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$subscriber_id, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$response = rest_do_request( sprintf( '/wp/v2/users/%d/application-passwords/%s', self::$subscriber_id, $slug ) );
		$this->assertEquals( 200, $response->get_status() );
		$this->check_response( $response->get_data(), $item );
	}

	public function test_get_item_other_user_id() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$subscriber_id, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$response = rest_do_request( sprintf( '/wp/v2/users/%d/application-passwords/%s', self::$subscriber_id, $slug ) );
		$this->assertEquals( 200, $response->get_status() );
		$this->check_response( $response->get_data(), $item );
	}

	public function test_get_item_other_user_id_invalid_permission() {
		wp_set_current_user( self::$subscriber_id );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$response = rest_do_request( sprintf( '/wp/v2/users/%d/application-passwords/%s', self::$admin, $slug ) );
		$this->assertErrorResponse( 'rest_cannot_manage_application_passwords', $response, 403 );
	}

	public function test_get_item_logged_out() {
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$response = rest_do_request( sprintf( '/wp/v2/users/me/application-passwords/%s', $slug ) );
		$this->assertErrorResponse( 'rest_not_logged_in', $response, 401 );
	}

	public function test_get_item_invalid_user_id() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$response = rest_do_request( '/wp/v2/users/0/application-passwords/' . $slug );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_get_item_invalid_password_slug() {
		wp_set_current_user( self::$admin );
		$response = rest_do_request( sprintf( '/wp/v2/users/%d/application-passwords/%s', self::$admin, '123456abcdef' ) );
		$this->assertErrorResponse( 'rest_application_password_not_found', $response, 404 );
	}

	public function test_create_item() {
		wp_set_current_user( self::$admin );

		$request = new WP_REST_Request( 'POST', '/wp/v2/users/me/application-passwords' );
		$request->set_body_params( array( 'name' => 'App' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 201, $response->get_status() );

		$passwords = WP_Application_Passwords::get_user_application_passwords( self::$admin );
		$this->assertCount( 1, $passwords );
		$this->check_response( $response->get_data(), $passwords[0], true );
		$this->assertEquals( 'App', $response->get_data()['name'] );
		$this->assertNull( $response->get_data()['last_used'] );
		$this->assertNull( $response->get_data()['last_ip'] );
	}

	public function test_create_item_self_user_id_admin() {
		wp_set_current_user( self::$admin );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/application-passwords', self::$admin ) );
		$request->set_body_params( array( 'name' => 'App' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 201, $response->get_status() );

		$passwords = WP_Application_Passwords::get_user_application_passwords( self::$admin );
		$this->assertCount( 1, $passwords );
		$this->check_response( $response->get_data(), $passwords[0], true );
	}

	public function test_create_item_self_user_id_subscriber() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/application-passwords', self::$subscriber_id ) );
		$request->set_body_params( array( 'name' => 'App' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 201, $response->get_status() );

		$passwords = WP_Application_Passwords::get_user_application_passwords( self::$subscriber_id );
		$this->assertCount( 1, $passwords );
		$this->check_response( $response->get_data(), $passwords[0], true );
	}

	public function test_create_item_other_user_id() {
		wp_set_current_user( self::$admin );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/application-passwords', self::$subscriber_id ) );
		$request->set_body_params( array( 'name' => 'App' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 201, $response->get_status() );

		$passwords = WP_Application_Passwords::get_user_application_passwords( self::$subscriber_id );
		$this->assertCount( 1, $passwords );
		$this->check_response( $response->get_data(), $passwords[0], true );
	}

	public function test_create_item_other_user_id_invalid_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/application-passwords', self::$admin ) );
		$request->set_body_params( array( 'name' => 'App' ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_application_passwords', $response, 403 );
	}

	public function test_create_item_invalid_user_id() {
		wp_set_current_user( self::$admin );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/application-passwords', 0 ) );
		$request->set_body_params( array( 'name' => 'App' ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_update_item() {
		$this->markTestSkipped( 'Application passwords cannot be updated.' );
	}

	public function test_delete_item() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/users/me/application-passwords/' . $slug );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'deleted', $response->get_data() );
		$this->assertTrue( $response->get_data()['deleted'] );
		$this->assertArrayHasKey( 'previous', $response->get_data() );
		$this->check_response( $response->get_data()['previous'], $item );

		$this->assertNull( WP_Application_Passwords::get_user_application_password( self::$admin, $slug ) );
	}

	public function test_delete_item_self_user_id_admin() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/application-passwords/%s', self::$admin, $slug ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->check_response( $response->get_data()['previous'], $item );
	}

	public function test_delete_item_self_user_id_subscriber() {
		wp_set_current_user( self::$subscriber_id );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$subscriber_id, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/application-passwords/%s', self::$subscriber_id, $slug ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->check_response( $response->get_data()['previous'], $item );
	}

	public function test_delete_item_other_user_id() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$subscriber_id, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/application-passwords/%s', self::$subscriber_id, $slug ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->check_response( $response->get_data()['previous'], $item );
	}

	public function test_delete_item_other_user_id_invalid_permission() {
		wp_set_current_user( self::$subscriber_id );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/application-passwords/%s', self::$admin, $slug ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_application_passwords', $response, 403 );
	}

	public function test_delete_item_logged_out() {
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/me/application-passwords/%s', $slug ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_not_logged_in', $response, 401 );
	}

	public function test_delete_item_invalid_user_id() {
		wp_set_current_user( self::$admin );
		list( , $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug     = WP_Application_Passwords::password_unique_slug( $item );
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/users/0/application-passwords/' . $slug );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_delete_item_invalid_password_slug() {
		wp_set_current_user( self::$admin );
		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/application-passwords/%s', self::$admin, '123456abcdef' ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_application_password_not_found', $response, 404 );
	}

	public function test_delete_items() {
		wp_set_current_user( self::$admin );
		WP_Application_Passwords::create_new_application_password( self::$admin, 'App 1' );
		WP_Application_Passwords::create_new_application_password( self::$admin, 'App 2' );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/users/me/application-passwords' );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'deleted', $response->get_data() );
		$this->assertTrue( $response->get_data()['deleted'] );
		$this->assertArrayHasKey( 'count', $response->get_data() );
		$this->assertEquals( 2, $response->get_data()['count'] );

		$this->assertCount( 0, WP_Application_Passwords::get_user_application_passwords( self::$admin ) );
	}

	public function test_delete_items_self_user_id_admin() {
		wp_set_current_user( self::$admin );
		WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/application-passwords', self::$admin ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 0, WP_Application_Passwords::get_user_application_passwords( self::$admin ) );
	}

	public function test_delete_items_self_user_id_subscriber() {
		wp_set_current_user( self::$subscriber_id );
		WP_Application_Passwords::create_new_application_password( self::$subscriber_id, 'App' );

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/application-passwords', self::$subscriber_id ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 0, WP_Application_Passwords::get_user_application_passwords( self::$admin ) );
	}

	public function test_delete_items_other_user_id() {
		wp_set_current_user( self::$admin );
		WP_Application_Passwords::create_new_application_password( self::$subscriber_id, 'App' );

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/application-passwords', self::$subscriber_id ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 0, WP_Application_Passwords::get_user_application_passwords( self::$admin ) );
	}

	public function test_delete_items_other_user_id_invalid_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/application-passwords', self::$admin ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_application_passwords', $response, 403 );
	}

	public function test_delete_items_logged_out() {
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/users/me/application-passwords' );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_not_logged_in', $response, 401 );
	}

	public function test_delete_items_invalid_user_id() {
		wp_set_current_user( self::$admin );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/users/0/application-passwords' );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_prepare_item() {
		wp_set_current_user( self::$admin );
		list( $password, $item ) = WP_Application_Passwords::create_new_application_password( self::$admin, 'App' );

		$slug                 = WP_Application_Passwords::password_unique_slug( $item );
		$item['slug']         = $slug;
		$item['new_password'] = $password;

		$request = new WP_REST_Request( 'GET', '/wp/v2/users/me/application-passwords/' . $slug );
		$request->set_param( 'context', 'edit' );
		$request->set_url_params(
			array(
				'user_id' => 'me',
				'slug'    => $slug,
			)
		);
		$prepared = ( new WP_REST_Application_Passwords_Controller() )->prepare_item_for_response( $item, $request );
		$this->assertNotWPError( $prepared );
		$this->check_response( $prepared->get_data(), $item, true );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users/me/application-passwords/' . $slug );
		$request->set_param( 'context', 'view' );
		$request->set_url_params(
			array(
				'user_id' => 'me',
				'slug'    => $slug,
			)
		);
		$prepared = ( new WP_REST_Application_Passwords_Controller() )->prepare_item_for_response( $item, $request );
		$this->assertNotWPError( $prepared );
		$this->check_response( $prepared->get_data(), $item );

		WP_Application_Passwords::used_application_password( self::$admin, $slug );

		$item         = WP_Application_Passwords::get_user_application_password( self::$admin, $slug );
		$item['slug'] = $slug;

		$request = new WP_REST_Request( 'GET', '/wp/v2/users/me/application-passwords/' . $slug );
		$request->set_param( 'context', 'view' );
		$request->set_url_params(
			array(
				'user_id' => 'me',
				'slug'    => $slug,
			)
		);
		$prepared = ( new WP_REST_Application_Passwords_Controller() )->prepare_item_for_response( $item, $request );
		$this->assertNotWPError( $prepared );
		$this->check_response( $prepared->get_data(), $item );
	}

	/**
	 * Checks the password response matches the exepcted format.
	 *
	 * @since ?.?.0
	 *
	 * @param array $response The response data.
	 * @param array $item     The created password item.
	 * @param bool  $password If the password is expected.
	 */
	protected function check_response( $response, $item, $password = false ) {
		$this->assertArrayHasKey( 'slug', $response );
		$this->assertArrayHasKey( 'name', $response );
		$this->assertArrayHasKey( 'created', $response );
		$this->assertArrayHasKey( 'last_used', $response );
		$this->assertArrayHasKey( 'last_ip', $response );

		$this->assertEquals( WP_Application_Passwords::password_unique_slug( $item ), $response['slug'] );
		$this->assertEquals( $item['name'], $response['name'] );
		$this->assertEquals( gmdate( 'Y-m-d\TH:i:s', $item['created'] ), $response['created'] );

		if ( $item['last_used'] ) {
			$this->assertEquals( gmdate( 'Y-m-d\TH:i:s', $item['last_used'] ), $response['last_used'] );
		} else {
			$this->assertNull( $response['last_used'] );
		}

		if ( $item['last_ip'] ) {
			$this->assertEquals( $item['last_ip'], $response['last_ip'] );
		} else {
			$this->assertNull( $response['last_ip'] );
		}

		if ( $password ) {
			$this->assertArrayHasKey( 'password', $response );
		} else {
			$this->assertArrayNotHasKey( 'password', $response );
		}
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/users/me/application-passwords' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertCount( 6, $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'created', $properties );
		$this->assertArrayHasKey( 'last_used', $properties );
		$this->assertArrayHasKey( 'last_ip', $properties );
	}
}
