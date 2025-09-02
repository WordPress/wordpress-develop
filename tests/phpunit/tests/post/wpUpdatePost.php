<?php
/**
 * Test wp_update_post() function
 *
 * @package WordPress
 * @subpackage Post
 *
 * @since 6.9.0
 */

/**
 * Class to Test wp_update_post() function
 *
 * @group post
 * @covers ::wp_update_post
 */
class Tests_Post_WpUpdatePost extends WP_UnitTestCase {

	/**
	 * User IDs for the test.
	 *
	 * @var array{administrator: null, editor: null, contributor: null}
	 */
	protected static $user_ids = array(
		'administrator' => null,
		'editor'        => null,
		'contributor'   => null,
	);

	/**
	 * Set up before class.
	 *
	 * @param WP_UnitTest_Factory $factory The Unit Test Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_ids = array(
			'administrator' => $factory->user->create(
				array(
					'role' => 'administrator',
				)
			),
			'editor'        => $factory->user->create(
				array(
					'role' => 'editor',
				)
			),
			'contributor'   => $factory->user->create(
				array(
					'role' => 'contributor',
				)
			),
		);

		$role = get_role( 'administrator' );
		$role->add_cap( 'publish_mapped_meta_caps' );
		$role->add_cap( 'publish_unmapped_meta_caps' );
	}

	/**
	 * Tear down after class.
	 */
	public static function tear_down_after_class() {
		$role = get_role( 'administrator' );
		$role->remove_cap( 'publish_mapped_meta_caps' );
		$role->remove_cap( 'publish_unmapped_meta_caps' );

		parent::tear_down_after_class();
	}

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		register_post_type(
			'mapped_meta_caps',
			array(
				'capability_type' => array( 'mapped_meta_cap', 'mapped_meta_caps' ),
				'map_meta_cap'    => true,
			)
		);

		register_post_type(
			'unmapped_meta_caps',
			array(
				'capability_type' => array( 'unmapped_meta_cap', 'unmapped_meta_caps' ),
				'map_meta_cap'    => false,
			)
		);

		register_post_type(
			'no_admin_caps',
			array(
				'capability_type' => array( 'no_admin_cap', 'no_admin_caps' ),
				'map_meta_cap'    => false,
			)
		);
	}

	/**
	 * Test updating a post with an invalid ID.
	 *
	 * @ticket 23474
	 */
	public function test_update_invalid_post_id() {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id, ARRAY_A );

		$post['ID'] = 123456789;

		$this->assertSame( 0, wp_update_post( $post ) );

		$this->assertInstanceOf( 'WP_Error', wp_update_post( $post, true ) );
	}

	/**
	 * Test ensuring that wp_update_post() does not unintentionally modify post tags
	 * if the post has several tags with the same name but different slugs.
	 *
	 * Tags should only be modified if 'tags_input' parameter was explicitly provided,
	 * and is different from the existing tags.
	 *
	 * @ticket 45121
	 */
	public function test_update_post_should_only_modify_post_tags_if_different_tags_input_was_provided() {
		$tag_1 = wp_insert_term( 'wp_update_post_tag', 'post_tag', array( 'slug' => 'wp_update_post_tag_1' ) );
		$tag_2 = wp_insert_term( 'wp_update_post_tag', 'post_tag', array( 'slug' => 'wp_update_post_tag_2' ) );
		$tag_3 = wp_insert_term( 'wp_update_post_tag', 'post_tag', array( 'slug' => 'wp_update_post_tag_3' ) );

		$post_id = self::factory()->post->create(
			array(
				'tags_input' => array( $tag_1['term_id'], $tag_2['term_id'] ),
			)
		);

		$post = get_post( $post_id );

		$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
		$this->assertSameSets( array( $tag_1['term_id'], $tag_2['term_id'] ), $tags );

		wp_update_post( $post );

		$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
		$this->assertSameSets( array( $tag_1['term_id'], $tag_2['term_id'] ), $tags );

		wp_update_post(
			array(
				'ID'         => $post->ID,
				'tags_input' => array( $tag_2['term_id'], $tag_3['term_id'] ),
			)
		);

		$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
		$this->assertSameSets( array( $tag_2['term_id'], $tag_3['term_id'] ), $tags );
	}

	/**
	 * Test the wp_update_post() filters post content when 'post_status' is 'draft'.
	 *
	 * @ticket 22944
	 */
	public function test_wp_update_post_with_content_filtering() {
		kses_remove_filters();

		$post_id = wp_insert_post(
			array(
				'post_title' => '<script>Test</script>',
			)
		);
		$post    = get_post( $post_id );
		$this->assertSame( '<script>Test</script>', $post->post_title );
		$this->assertSame( 'draft', $post->post_status );

		kses_init_filters();

		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'publish',
			)
		);

		kses_remove_filters();

		$post = get_post( $post->ID );
		$this->assertSame( 'Test', $post->post_title );
	}

	/**
	 * Test updating a post and preserving post_date when changing to future status
	 *
	 * @ticket 62468
	 *
	 * @dataProvider data_update_post_preserves_date_for_future_posts
	 *
	 * @param string $initial_status Initial post status.
	 */
	public function test_update_post_preserves_date_for_future_posts( $initial_status ) {

		$post_id = self::factory()->post->create(
			array(
				'post_status' => $initial_status,
			)
		);

		$future_date = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		$update_data = array(
			'ID'          => $post_id,
			'post_status' => 'future',
			'post_date'   => $future_date,
		);

		wp_update_post( $update_data );
		$updated_post = get_post( $post_id );

		$this->assertSame( $future_date, $updated_post->post_date );
	}

	/**
	 * Data provider for test_update_post_preserves_date_for_future_posts
	 *
	 * @return array[] Test parameters
	 */
	public function data_update_post_preserves_date_for_future_posts() {
		return array(
			'pending to future' => array(
				'initial_status' => 'pending',
			),
			'draft to future'   => array(
				'initial_status' => 'draft',
			),
		);
	}
}
