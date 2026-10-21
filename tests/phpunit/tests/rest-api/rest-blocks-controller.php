<?php
/**
 * Unit tests covering WP_REST_Blocks_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.0.0
 *
 * @covers WP_REST_Blocks_Controller
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
	 * Our fake user IDs, keyed by their role.
	 *
	 * @since 5.0.0
	 *
	 * @var array
	 */
	protected static $user_ids;

	/**
	 * Create fake data before our tests run.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'My cool block',
				'post_content' => '<!-- wp:paragraph --><p>Hello!</p><!-- /wp:paragraph -->',
			)
		);

		self::$user_ids = array(
			'editor'      => $factory->user->create( array( 'role' => 'editor' ) ),
			'author'      => $factory->user->create( array( 'role' => 'author' ) ),
			'contributor' => $factory->user->create( array( 'role' => 'contributor' ) ),
		);
	}

	/**
	 * Delete our fake data after our tests run.
	 *
	 * @since 5.0.0
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id );

		foreach ( self::$user_ids as $user_id ) {
			self::delete_user( $user_id );
		}
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
	 * update, and delete synced patterns.
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
			$user_id = self::$user_ids[ $role ];
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
						'content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->',
					)
				);

				$response = rest_get_server()->dispatch( $request );
				$this->assertSame( $expected_status, $response->get_status() );

				break;

			case 'read':
				$request = new WP_REST_Request( 'GET', '/wp/v2/blocks/' . self::$post_id );

				$response = rest_get_server()->dispatch( $request );
				$this->assertSame( $expected_status, $response->get_status() );

				break;

			case 'update_delete_own':
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'wp_block',
						'post_status'  => 'publish',
						'post_title'   => 'My cool block',
						'post_content' => '<!-- wp:paragraph --><p>Hello!</p><!-- /wp:paragraph -->',
						'post_author'  => $user_id,
					)
				);

				$request = new WP_REST_Request( 'PUT', '/wp/v2/blocks/' . $post_id );
				$request->set_body_params(
					array(
						'title'   => 'Test',
						'content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->',
					)
				);

				$response = rest_get_server()->dispatch( $request );
				$this->assertSame( $expected_status, $response->get_status() );

				$request = new WP_REST_Request( 'DELETE', '/wp/v2/blocks/' . $post_id );

				$response = rest_get_server()->dispatch( $request );
				$this->assertSame( $expected_status, $response->get_status() );

				wp_delete_post( $post_id );

				break;

			case 'update_delete_others':
				$request = new WP_REST_Request( 'PUT', '/wp/v2/blocks/' . self::$post_id );
				$request->set_body_params(
					array(
						'title'   => 'Test',
						'content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->',
					)
				);

				$response = rest_get_server()->dispatch( $request );
				$this->assertSame( $expected_status, $response->get_status() );

				$request = new WP_REST_Request( 'DELETE', '/wp/v2/blocks/' . self::$post_id );

				$response = rest_get_server()->dispatch( $request );
				$this->assertSame( $expected_status, $response->get_status() );

				break;

			default:
				$this->fail( "'$action' is not a valid action." );
		}
	}

	/**
	 * Check that the raw title and content of a block can be accessed when there
	 * is no set schema, and that the rendered content of a block is not included
	 * in the response.
	 */
	public function test_content() {
		wp_set_current_user( self::$user_ids['author'] );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/blocks/' . self::$post_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame(
			array(
				'raw' => 'My cool block',
			),
			$data['title']
		);
		$this->assertSame(
			array(
				'raw'       => '<!-- wp:paragraph --><p>Hello!</p><!-- /wp:paragraph -->',
				'protected' => false,
			),
			$data['content']
		);
	}

	/**
	 * Check that the `wp_pattern_sync_status` postmeta is moved from meta array to top
	 * level of response.
	 *
	 * @ticket 58677
	 */
	public function test_wp_patterns_sync_status_post_meta() {
		register_post_meta(
			'wp_block',
			'wp_pattern_sync_status',
			array(
				'single'       => true,
				'type'         => 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'string',
						'properties' => array(
							'sync_status' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);
		wp_set_current_user( self::$user_ids['author'] );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/blocks/' . self::$post_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'wp_pattern_sync_status', $data );
		$this->assertArrayNotHasKey( 'wp_pattern_sync_status', $data['meta'] );
	}

	/**
	 * Check that the response includes the pattern's author.
	 *
	 * @ticket 66307
	 */
	public function test_author_in_response() {
		wp_set_current_user( self::$user_ids['editor'] );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'wp_block',
				'post_author' => self::$user_ids['author'],
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/blocks/' . $post_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( self::$user_ids['author'], $data['author'] );
	}

	/**
	 * Check that patterns can be filtered by author.
	 *
	 * @ticket 66307
	 */
	public function test_filter_by_author() {
		wp_set_current_user( self::$user_ids['editor'] );

		$editor_post_id = self::factory()->post->create(
			array(
				'post_type'   => 'wp_block',
				'post_author' => self::$user_ids['editor'],
			)
		);
		$author_post_id = self::factory()->post->create(
			array(
				'post_type'   => 'wp_block',
				'post_author' => self::$user_ids['author'],
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/blocks' );
		$request->set_param( 'author', self::$user_ids['author'] );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( array( $author_post_id ), wp_list_pluck( $response->get_data(), 'id' ), 'The author filter should only return the author\'s patterns.' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/blocks' );
		$request->set_param( 'author_exclude', self::$user_ids['author'] );
		$response = rest_get_server()->dispatch( $request );
		$ids      = wp_list_pluck( $response->get_data(), 'id' );

		$this->assertContains( $editor_post_id, $ids, 'The author_exclude filter should return other users\' patterns.' );
		$this->assertNotContains( $author_post_id, $ids, 'The author_exclude filter should not return the author\'s patterns.' );
	}

	/**
	 * Check that only users who can edit others' patterns can change a pattern's author.
	 *
	 * @ticket 66307
	 *
	 * @dataProvider data_change_author
	 *
	 * @param string $role            User role to test.
	 * @param int    $expected_status Expected HTTP response status.
	 */
	public function test_change_author( $role, $expected_status ) {
		$user_id = self::$user_ids[ $role ];
		wp_set_current_user( $user_id );

		$post_id    = self::factory()->post->create(
			array(
				'post_type'   => 'wp_block',
				'post_author' => $user_id,
			)
		);
		$new_author = 'editor' === $role ? self::$user_ids['author'] : self::$user_ids['editor'];

		$request = new WP_REST_Request( 'PUT', '/wp/v2/blocks/' . $post_id );
		$request->set_body_params( array( 'author' => $new_author ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( $expected_status, $response->get_status(), 'The response status should match.' );
		$this->assertSame( 200 === $expected_status ? $new_author : $user_id, (int) get_post( $post_id )->post_author, 'The pattern\'s author should match.' );
	}

	/**
	 * Test cases for test_change_author().
	 *
	 * @return array[]
	 */
	public function data_change_author() {
		return array(
			'editor' => array( 'editor', 200 ),
			'author' => array( 'author', 403 ),
		);
	}
}
