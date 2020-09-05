<?php
/**
 * WP_REST_Batch_Controller tests.
 *
 * @package    WordPress
 * @subpackage REST_API
 * @since      5.5.0
 */

/**
 * Tests for WP_REST_Batch_Controller.
 *
 * @since 5.5.0
 *
 * @group restapi
 */
class REST_Batch_Controller_Test extends WP_Test_REST_TestCase {
	/**
	 * Test API user's ID.
	 *
	 * @since 5.5.0
	 *
	 * @var int
	 */
	protected static $administrator_id;

	/**
	 * Create test data before the tests run.
	 *
	 * @since 5.5.0
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$administrator_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
	}

	/**
	 * Delete test data after our tests run.
	 *
	 * @since 5.5.0
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$administrator_id );
	}

	/**
	 * @ticket 50244
	 */
	public function test_pre_validation() {
		wp_set_current_user( self::$administrator_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/batch' );
		$request->set_body_params(
			array(
				'validation' => 'pre',
				'requests'   => array(
					array(
						'path' => '/wp/v2/posts',
						'body' => array(
							'title'   => 'Hello World',
							'content' => 'From the moon.',
						),
					),
					array(
						'path' => '/wp/v2/posts',
						'body' => array(
							'title'   => 'Hello Moon',
							'content' => 'From the world.',
							'status'  => 'garbage',
						),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 207, $response->get_status() );
		$this->assertArrayHasKey( 'pre-validation', $data );
		$this->assertCount( 2, $data['pre-validation'] );
		$this->assertTrue( $data['pre-validation'][0]['body'] );
		$this->assertEquals( 400, $data['pre-validation'][1]['status'] );
	}

	/**
	 * @ticket 50244
	 */
	public function test_batch_create() {
		wp_set_current_user( self::$administrator_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/batch' );
		$request->set_body_params(
			array(
				'requests' => array(
					array(
						'path' => '/wp/v2/posts',
						'body' => array(
							'title'   => 'Hello World',
							'content' => 'From the moon.',
						),
					),
					array(
						'path' => '/wp/v2/posts',
						'body' => array(
							'title'   => 'Hello Moon',
							'status'  => 'draft',
							'content' => 'From the world.',
						),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 207, $response->get_status() );
		$this->assertArrayHasKey( 'responses', $data );
		$this->assertCount( 2, $data['responses'] );
		$this->assertEquals( 'Hello World', $data['responses'][0]['body']['title']['rendered'] );
		$this->assertEquals( 'Hello Moon', $data['responses'][1]['body']['title']['rendered'] );
	}
}
