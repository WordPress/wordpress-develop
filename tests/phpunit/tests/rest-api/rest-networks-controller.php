<?php
/**
 * Unit tests covering WP_REST_Networks_Controller functionality, used for Networks
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
if ( is_multisite() ) {

	class WP_Test_REST_Network_Controller extends WP_Test_REST_Controller_Testcase {
		protected static $superadmin_id;
		protected static $editor;
		protected static $network_ids;

		public static function wpSetUpBeforeClass( $factory ) {
			self::$superadmin_id = $factory->user->create(
				array(
					'role'       => 'administrator',
					'user_login' => 'superadmin',
				)
			);
			update_site_option( 'site_admins', array( 'superadmin' ) );

			self::$editor = $factory->user->create(
				array(
					'role'       => 'editor',
					'user_login' => 'editor',
				)
			);

			self::$network_ids = array(
				'example.com/foo' => array(
					'domain' => 'example.com',
					'path'   => '/foo/',
				),
				'example.com/bar' => array(
					'domain' => 'example.com',
					'path'   => '/bar/',
				),
			);

			foreach ( self::$network_ids as &$id ) {
				$id = $factory->network->create( $id );
			}
			unset( $id );
		}

		public static function wpTearDownAfterClass() {
			global $wpdb;

			foreach ( self::$network_ids as $id ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d", $id ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->site} WHERE id= %d", $id ) );
			}
		}

		public function test_register_routes() {
			$routes = rest_get_server()->get_routes();
			$this->assertArrayHasKey( '/wp/v2/networks', $routes );
			$this->assertArrayHasKey( '/wp/v2/networks/(?P<id>[\d]+)', $routes );
		}

		public function test_context_param() {
			wp_set_current_user( self::$superadmin_id );
			$this->assertTrue( current_user_can( 'manage_options' ) );
			// Collections
			$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/networks' );
			$response = rest_get_server()->dispatch( $request );
			$data     = $response->get_data();
			$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
			$this->assertEqualSets( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
			// Single
			$site_id  = self::$network_ids['example.com/bar'];
			$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/networks/' . $site_id );
			$response = rest_get_server()->dispatch( $request );
			$data     = $response->get_data();
			$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
			$this->assertEqualSets( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		}

		public function test_get_items() {
			wp_set_current_user( self::$superadmin_id );
			$this->assertTrue( current_user_can( 'manage_options' ) );
			$request  = new WP_REST_Request( 'GET', '/wp/v2/networks' );
			$response = rest_get_server()->dispatch( $request );
			$data     = $response->get_data();
			$this->assertEquals( 200, $response->get_status() );
			$this->assertEquals( 3, count( $data ) );
		}

		public function test_get_items_with_no_manage_options() {
			wp_set_current_user( self::$editor );
			$this->assertFalse( current_user_can( 'manage_options' ) );
			$request  = new WP_REST_Request( 'GET', '/wp/v2/networks' );
			$response = rest_get_server()->dispatch( $request );
			$data     = $response->get_data();
			$this->assertEquals( 403, $response->get_status() );
		}

		public function test_get_item() {
			wp_set_current_user( self::$superadmin_id );
			$this->assertTrue( current_user_can( 'manage_options' ) );
			$site_id  = self::$network_ids['example.com/bar'];
			$request  = new WP_REST_Request( 'GET', '/wp/v2/networks/' . $site_id );
			$response = rest_get_server()->dispatch( $request );
			$data     = $response->get_data();
			$this->assertEquals( 200, $response->get_status() );
			$this->assertEquals( $site_id, $data['id'] );
			$this->assertEquals( array_keys( self::$network_ids )[1] . '/', $data['domain'] . $data['path'] );
		}

		public function test_get_item_with_non_existing_id() {
			wp_set_current_user( self::$superadmin_id );
			$this->assertTrue( current_user_can( 'manage_options' ) );
			$request  = new WP_REST_Request( 'GET', '/wp/v2/networks/212' );
			$response = rest_get_server()->dispatch( $request );
			$data     = $response->get_data();
			$this->assertEquals( 404, $response->get_status() );
		}

		public function test_get_item_schema() {
			$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/networks' );
			$response   = rest_get_server()->dispatch( $request );
			$data       = $response->get_data();
			$properties = $data['schema']['properties'];
			$this->assertEquals( 7, count( $properties ) );
			$this->assertArrayHasKey( 'cookie_domain', $properties );
			$this->assertArrayHasKey( 'domain', $properties );
			$this->assertArrayHasKey( 'id', $properties );
			$this->assertArrayHasKey( 'meta', $properties );
			$this->assertArrayHasKey( 'path', $properties );
			$this->assertArrayHasKey( 'site_id', $properties );
			$this->assertArrayHasKey( 'site_name', $properties );
		}

		public function test_create_item() {
			$this->markTestSkipped( 'This method does not exist for networks' );
		}

		public function test_update_item() {
			$this->markTestSkipped( 'This method does not exist for networks' );
		}

		public function test_delete_item() {
			$this->markTestSkipped( 'This method does not exist for networks' );
		}

		public function test_prepare_item() {
			$this->markTestSkipped( 'This method does not exist for networks' );
		}
	}
}
