<?php

/**
 * Tests for get_post_type_capabilities().
 *
 * @group post
 *
 * @covers ::get_post_type_capabilities
 */
class Tests_Post_GetPostTypeCapabilities extends WP_UnitTestCase {

	/**
	 * The meta capabilities stored before each test.
	 *
	 * @var array<string, string>
	 */
	private $original_post_type_meta_caps;

	public function set_up() {
		global $post_type_meta_caps;

		parent::set_up();

		$this->original_post_type_meta_caps = $post_type_meta_caps;
	}

	public function tear_down() {
		global $post_type_meta_caps;

		$post_type_meta_caps = $this->original_post_type_meta_caps;

		parent::tear_down();
	}

	/**
	 * Tests the capabilities built from the post type registration arguments.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_capabilities
	 *
	 * @param string|string[]       $capability_type Capability type.
	 * @param bool                  $map_meta_cap    Whether to use the default meta capability handling.
	 * @param array<string, string> $capabilities    Custom capabilities.
	 * @param array<string, string> $expected        Expected capabilities.
	 */
	public function test_returns_capabilities( $capability_type, $map_meta_cap, $capabilities, $expected ) {
		$args = (object) array(
			'capability_type' => $capability_type,
			'map_meta_cap'    => $map_meta_cap,
			'capabilities'    => $capabilities,
		);

		$actual = get_post_type_capabilities( $args );

		$this->assertSameSetsWithIndex( $expected, get_object_vars( $actual ), 'The capabilities do not match.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_capabilities() {
		$book_capabilities = array(
			'edit_post'          => 'edit_book',
			'read_post'          => 'read_book',
			'delete_post'        => 'delete_book',
			'edit_posts'         => 'edit_books',
			'edit_others_posts'  => 'edit_others_books',
			'delete_posts'       => 'delete_books',
			'publish_posts'      => 'publish_books',
			'read_private_posts' => 'read_private_books',
			'create_posts'       => 'edit_books',
		);

		$book_capabilities_for_mapping = array(
			'read'                   => 'read',
			'delete_private_posts'   => 'delete_private_books',
			'delete_published_posts' => 'delete_published_books',
			'delete_others_posts'    => 'delete_others_books',
			'edit_private_posts'     => 'edit_private_books',
			'edit_published_posts'   => 'edit_published_books',
		);

		return array(
			'array capability type'               => array(
				'capability_type' => array( 'story', 'stories' ),
				'map_meta_cap'    => false,
				'capabilities'    => array(),
				'expected'        => array(
					'edit_post'          => 'edit_story',
					'read_post'          => 'read_story',
					'delete_post'        => 'delete_story',
					'edit_posts'         => 'edit_stories',
					'edit_others_posts'  => 'edit_others_stories',
					'delete_posts'       => 'delete_stories',
					'publish_posts'      => 'publish_stories',
					'read_private_posts' => 'read_private_stories',
					'create_posts'       => 'edit_stories',
				),
			),
			'custom meta capability with mapping' => array(
				'capability_type' => 'book',
				'map_meta_cap'    => true,
				'capabilities'    => array( 'edit_post' => 'edit_a_book' ),
				'expected'        => array_merge(
					$book_capabilities,
					$book_capabilities_for_mapping,
					array( 'edit_post' => 'edit_a_book' )
				),
			),
			'additional capability'               => array(
				'capability_type' => 'book',
				'map_meta_cap'    => false,
				'capabilities'    => array( 'export_posts' => 'export_books' ),
				'expected'        => array_merge( $book_capabilities, array( 'export_posts' => 'export_books' ) ),
			),
		);
	}
}
