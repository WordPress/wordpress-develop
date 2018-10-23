<?php
/**
 * WP_REST_Blocks_Controller tests
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.0.0
 */

/**
 * Tests for WP_REST_Blocks_Controller.
 *
 * @since 5.0.0
 *
 * @see WP_Test_REST_Controller_Testcase
 *
 * @group restapi-blocks
 * @group restapi
 */
class REST_Blocks_Controller_Test extends WP_UnitTestCase {

	/**
	 * Our fake block's post ID.
	 *
	 * @since 5.0.0
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Our fake user's ID.
	 *
	 * @since 5.0.0
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before our tests run.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$post_id = wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'My cool block',
				'post_content' => '<!-- wp:core/paragraph --><p>Hello!</p><!-- /wp:core/paragraph -->',
			)
		);

		self::$user_id = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);
	}

	/**
	 * Delete our fake data after our tests run.
	 *
	 * @since 5.0.0
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id );

		self::delete_user( self::$user_id );
	}

	/**
	 * Test cases for test_capabilities().
	 *
	 * @since 5.0.0
	 */
	public function data_capabilities() {
		return array(
			array( 'create', 'editor', 201 ),
			array( 'create', 'author', 201 ),
			array( 'create', 'contributor', 403 ),
			array( 'create', null, 401 ),

			array( 'read', 'editor', 200 ),
			array( 'read', 'author', 200 ),
			array( 'read', 'contributor', 200 ),
			array( 'read', null, 401 ),

			array( 'update_delete_own', 'editor', 200 ),
			array( 'update_delete_own', 'author', 200 ),
			array( 'update_delete_own', 'contributor', 403 ),

			array( 'update_delete_others', 'editor', 200 ),
			array( 'update_delete_others', 'author', 403 ),
			array( 'update_delete_others', 'contributor', 403 ),
			array( 'update_delete_others', null, 401 ),
		);
	}

	/**
	 * Exhaustively check that each role either can or cannot create, edit,
	 * update, and delete reusable blocks.
	 *
	 * @ticket 45098
	 *
	 * @dataProvider data_capabilities
	 *
	 * @param string $action          Action to perform in the test.
	 * @param string $role            User role to test.
	 * @param int    $expected_status Expected HTTP response status.
	 */
	public function test_capabilities( $action, $role, $expected_status ) {
		if ( $role ) {
			$user_id = $this->factory->user->create( array( 'role' => $role ) );
			wp_set_current_user( $user_id );
		} else {
			wp_set_current_user( 0 );
		}

		switch ( $action ) {
			case 'create':
				$request = new WP_REST_Request( 'POST', '/wp/v2/blocks' );
				$request->set_body_params(
					array(
						'title'   => 'Test',
						'content' => '<!-- wp:core/paragraph --><p>Test</p><!-- /wp:core/paragraph -->',
					)
				);

				$response = rest_get_server()->dispatch( $request );
				$this->assertEquals( $expected_status, $response->get_status() );

				break;

			case 'read':
				$request = new WP_REST_Request( 'GET', '/wp/v2/blocks/' . self::$post_id );

				$response = rest_get_server()->dispatch( $request );
				$this->assertEquals( $expected_status, $response->get_status() );

				break;

			case 'update_delete_own':
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'wp_block',
						'post_status'  => 'publish',
						'post_title'   => 'My cool block',
						'post_content' => '<!-- wp:core/paragraph --><p>Hello!</p><!-- /wp:core/paragraph -->',
						'post_author'  => $user_id,
					)
				);

				$request = new WP_REST_Request( 'PUT', '/wp/v2/blocks/' . $post_id );
				$request->set_body_params(
					array(
						'title'   => 'Test',
						'content' => '<!-- wp:core/paragraph --><p>Test</p><!-- /wp:core/paragraph -->',
					)
				);

				$response = rest_get_server()->dispatch( $request );
				$this->assertEquals( $expected_status, $response->get_status() );

				$request = new WP_REST_Request( 'DELETE', '/wp/v2/blocks/' . $post_id );

				$response = rest_get_server()->dispatch( $request );
				$this->assertEquals( $expected_status, $response->get_status() );

				wp_delete_post( $post_id );

				break;

			case 'update_delete_others':
				$request = new WP_REST_Request( 'PUT', '/wp/v2/blocks/' . self::$post_id );
				$request->set_body_params(
					array(
						'title'   => 'Test',
						'content' => '<!-- wp:core/paragraph --><p>Test</p><!-- /wp:core/paragraph -->',
					)
				);

				$response = rest_get_server()->dispatch( $request );
				$this->assertEquals( $expected_status, $response->get_status() );

				$request = new WP_REST_Request( 'DELETE', '/wp/v2/blocks/' . self::$post_id );

				$response = rest_get_server()->dispatch( $request );
				$this->assertEquals( $expected_status, $response->get_status() );

				break;

			default:
				$this->fail( "'$action' is not a valid action." );
		}

		if ( isset( $user_id ) ) {
			self::delete_user( $user_id );
		}
	}
}
