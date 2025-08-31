<?php
/**
 * Tests for the get_post_by() function.
 *
 * @group post
 * @covers ::get_post_by
 */
class Tests_Post_GetPostBy extends WP_UnitTestCase {
	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Test page ID.
	 *
	 * @var int
	 */
	private static $page_id;

	/**
	 * Test custom post type ID.
	 *
	 * @var int
	 */
	private static $cpt_id;

	/**
	 * Set up test data before the class is instantiated.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title'   => 'Test Post Title',
				'post_name'    => 'test-post-slug',
				'post_content' => 'Test post content.',
				'post_type'    => 'post',
				'post_status'  => 'publish',
			)
		);

		$parent_page_id = $factory->post->create(
			array(
				'post_title'   => 'Parent Page',
				'post_name'    => 'parent-page',
				'post_content' => 'Parent page content.',
				'post_type'    => 'page',
				'post_status'  => 'publish',
			)
		);

		self::$page_id = $factory->post->create(
			array(
				'post_title'   => 'Child Page',
				'post_name'    => 'child-page',
				'post_content' => 'Child page content.',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_parent'  => $parent_page_id,
			)
		);

		register_post_type(
			'test_cpt',
			array(
				'public'       => true,
				'hierarchical' => true,
			)
		);

		self::$cpt_id = $factory->post->create(
			array(
				'post_title'   => 'Custom Post Type',
				'post_name'    => 'custom-post-type',
				'post_content' => 'Custom post type content.',
				'post_type'    => 'test_cpt',
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * Clean up after all tests.
	 */
	public static function wpTearDownAfterClass() {
		unregister_post_type( 'test_cpt' );
	}

	/**
	 * Test getting a post by ID.
	 *
	 * @ticket 12726
	 */
	public function test_get_post_by_id() {
		$post = get_post_by( 'id', self::$post_id, array() );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertSame( self::$post_id, $post->ID );
		$this->assertSame( 'post', $post->post_type );

		// Test with custom post type.
		$cpt = get_post_by( 'id', self::$cpt_id, array( 'post_type' => 'test_cpt' ) );
		$this->assertInstanceOf( 'WP_Post', $cpt );
		$this->assertSame( self::$cpt_id, $cpt->ID );
		$this->assertSame( 'test_cpt', $cpt->post_type );

		// Test with non-existent ID.
		$non_existent = get_post_by( 'id', 999999, array() );
		$this->assertNull( $non_existent );
	}

	/**
	 * Test getting a post by title.
	 *
	 * @ticket 12726
	 */
	public function test_get_post_by_title() {
		// Test with post.
		$post = get_post_by( 'title', 'Test Post Title', array( 'post_type' => 'post' ) );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertSame( self::$post_id, $post->ID );
		$this->assertSame( 'Test Post Title', $post->post_title );

		// Test with page.
		$page = get_post_by( 'title', 'Child Page', array( 'post_type' => 'page' ) );
		$this->assertInstanceOf( 'WP_Post', $page );
		$this->assertSame( self::$page_id, $page->ID );
		$this->assertSame( 'Child Page', $page->post_title );

		// Test with custom post type.
		$cpt = get_post_by( 'title', 'Custom Post Type', array( 'post_type' => 'test_cpt' ) );
		$this->assertInstanceOf( 'WP_Post', $cpt );
		$this->assertSame( self::$cpt_id, $cpt->ID );
		$this->assertSame( 'Custom Post Type', $cpt->post_title );

		// Test with non-existent title.
		$non_existent = get_post_by( 'title', 'Non-existent Title', array( 'post_type' => 'post' ) );
		$this->assertNull( $non_existent );
	}

	/**
	 * Test getting a post by path.
	 *
	 * @ticket 12726
	 */
	public function test_get_post_by_path() {
		// Test with post.
		$post = get_post_by( 'path', 'test-post-slug', array( 'post_type' => 'post' ) );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertSame( self::$post_id, $post->ID );
		$this->assertSame( 'test-post-slug', $post->post_name );

		// Test with hierarchical path.
		$page = get_post_by( 'path', 'parent-page/child-page', array( 'post_type' => 'page' ) );
		$this->assertInstanceOf( 'WP_Post', $page );
		$this->assertSame( self::$page_id, $page->ID );
		$this->assertSame( 'child-page', $page->post_name );

		// Test with custom post type.
		$cpt = get_post_by( 'path', 'custom-post-type', array( 'post_type' => 'test_cpt' ) );
		$this->assertInstanceOf( 'WP_Post', $cpt );
		$this->assertSame( self::$cpt_id, $cpt->ID );
		$this->assertSame( 'custom-post-type', $cpt->post_name );

		// Test with non-existent path.
		$non_existent = get_post_by( 'path', 'non-existent-path', array( 'post_type' => 'post' ) );
		$this->assertNull( $non_existent );
	}

	/**
	 * Test with invalid field.
	 *
	 * @ticket 12726
	 */
	public function test_get_post_by_invalid_field() {
		$result = get_post_by( 'invalid_field', 'some_value', array() );
		$this->assertNull( $result );
	}

	/**
	 * Test with different output formats.
	 *
	 * @ticket 12726
	 */
	public function test_get_post_by_with_different_output_formats() {
		// Test with ARRAY_A output.
		$post_array = get_post_by( 'id', self::$post_id, array( 'output' => ARRAY_A ) );
		$this->assertIsArray( $post_array );
		$this->assertSame( self::$post_id, $post_array['ID'] );

		// Test with ARRAY_N output.
		$post_array_n = get_post_by( 'id', self::$post_id, array( 'output' => ARRAY_N ) );
		$this->assertIsArray( $post_array_n );
		$this->assertContains( self::$post_id, $post_array_n );
	}
}
