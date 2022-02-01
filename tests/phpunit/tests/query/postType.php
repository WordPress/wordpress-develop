<?php

/**
 * Tests to make sure querying posts based on various type parameters works as expected.
 *
 * @group query
 */
class Tests_Query_PostType extends WP_UnitTestCase {

	public static $users;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::setup_cpt();
		self::$users['administrator'] = $factory->user->create( array( 'role' => 'administrator' ) );

		$post_ids = array_merge(
			$factory->post->create_many( 5 ),
			$factory->post->create_many( 5, array( 'post_type' => 'cpt_public' ) ),
			$factory->post->create_many( 5, array( 'post_type' => 'cpt_private' ) ),
			$factory->post->create_many( 5, array( 'post_type' => 'any' ) ),
		);

		foreach ( $post_ids as $post_id ) {
			$new_content = get_post( $post_id )->post_content . ' updated';
			$factory->post->update_object( $post_id, array( 'post_content' => $new_content ) );
		}
	}

	public function setUp() {
		parent::setUp();
		self::setup_cpt();
	}

	public static function setup_cpt() {
		register_post_type(
			'cpt_public',
			array(
				'public'   => true,
				'supports' => array( 'revisions' ),
			)
		);

		register_post_type(
			'cpt_private',
			array(
				'public'   => false,
				'supports' => array( 'revisions' ),
			)
		);
	}

	/**
	 * Test various post type queries.
	 *
	 * @dataProvider data_run_query
	 * @param array $args           WP_Query argument array.
	 * @param int   $expected_count Expected number of posts.
	 */
	public function test_run_query( $args, $expected_count ) {
		// Set to admin to ensure all post types can be read.
		wp_set_current_user( self::$users['administrator'] );

		$args  = array_merge( array( 'posts_per_page' => -1 ), $args );
		$query = new WP_Query( $args );
		$posts = $query->get_posts();

		$this->assertCount( $expected_count, $posts );
	}

	/**
	 * Data provider for test_run_query.
	 *
	 * @return array[] {
	 *     Arguments passed to test.
	 *
	 *     @type array $args           WP_Query arguments array.
	 *     @type int   $expected_count Number of posts expected to be returned.
	 * }
	 */
	public function data_run_query() {
		return array(
			array(
				array(
					'post_type' => 'any',
				),
				15,
			),
			array(
				array(
					'post_type' => array( 'any' ),
				),
				15,
			),
			array(
				array(
					'post_type' => array( 'any', 'cpt_private' ),
				),
				20,
			),
			array(
				array(
					'post_type'   => array( 'revision' ),
					'post_status' => array( 'inherit' ),
				),
				20,
			),
			array(
				array(
					'post_type'   => array( 'any', 'revision' ),
					'post_status' => array( 'publish', 'inherit' ),
				),
				30,
			),
		);
	}
}
