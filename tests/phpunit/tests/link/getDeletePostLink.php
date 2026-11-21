<?php
/**
 * Tests for the `get_delete_post_link()` function.
 *
 * @group link
 * @group post
 *
 * @covers ::get_delete_post_link
 */
class Tests_Link_GetDeletePostLink extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test page ID.
	 *
	 * @var int
	 */
	protected static $page_id;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Sets up shared fixtures for the test class.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_id     = $factory->user->create( array( 'role' => 'author' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		self::$post_id = $factory->post->create(
			array(
				'post_title'  => 'Test Post',
				'post_author' => self::$admin_id,
				'post_status' => 'publish',
			)
		);

		self::$page_id = $factory->post->create(
			array(
				'post_title'  => 'Test Page',
				'post_type'   => 'page',
				'post_author' => self::$admin_id,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Sets up the environment before each test method.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Tears down the environment after each test method.
	 */
	public function tear_down() {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * Tests that get_delete_post_link() returns a trash link by default when trash is enabled.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_default_returns_trash_link() {
		$expected = wp_nonce_url(
			admin_url( 'post.php?post=' . self::$post_id . '&amp;action=trash' ),
			'trash-post_' . self::$post_id
		);

		$this->assertSame( $expected, get_delete_post_link( self::$post_id ) );
	}

	/**
	 * Tests that get_delete_post_link() returns a delete link when $force_delete is true.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_with_force_delete_true() {
		$expected = wp_nonce_url(
			admin_url( 'post.php?post=' . self::$post_id . '&amp;action=delete' ),
			'delete-post_' . self::$post_id
		);

		$this->assertSame( $expected, get_delete_post_link( self::$post_id, '', true ) );
	}

	/**
	 * Tests that get_delete_post_link() accepts a WP_Post object.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_accepts_post_object() {
		$post = get_post( self::$post_id );

		$this->assertSame(
			get_delete_post_link( self::$post_id ),
			get_delete_post_link( $post ),
			'Passing a WP_Post object should produce the same link as passing post ID.'
		);
	}

	/**
	 * Tests that get_delete_post_link() uses global $post when no post argument is provided.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_uses_global_post_when_unspecified() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$this->assertSame(
			get_delete_post_link( self::$post_id ),
			get_delete_post_link(),
			'get_delete_post_link() should fall back to global $post when no argument is passed.'
		);
	}

	/**
	 * Tests that get_delete_post_link() works for pages.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_for_page() {
		$expected = wp_nonce_url(
			admin_url( 'post.php?post=' . self::$page_id . '&amp;action=trash' ),
			'trash-post_' . self::$page_id
		);

		$this->assertSame( $expected, get_delete_post_link( self::$page_id ) );
	}

	/**
	 * Tests that get_delete_post_link() returns null for a non-existent post.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_returns_null_for_non_existent_post() {
		$this->assertNull( get_delete_post_link( 0 ), 'Should return null when post ID is 0 and no global post exists.' );
		$this->assertNull( get_delete_post_link( 999999 ), 'Should return null for non-existent post ID.' );
	}

	/**
	 * Tests that get_delete_post_link() returns null when user lacks delete_post capability.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_returns_null_when_user_cannot_delete_post() {
		wp_set_current_user( self::$subscriber_id );

		$this->assertNull(
			get_delete_post_link( self::$post_id ),
			'Should return null when current user lacks delete_post capability.'
		);
	}

	/**
	 * Tests that get_delete_post_link() returns a link for an author who can delete their own post.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_for_author_owning_post() {
		$author_post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Author Post',
				'post_author' => self::$author_id,
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( self::$author_id );

		$expected = wp_nonce_url(
			admin_url( 'post.php?post=' . $author_post_id . '&amp;action=trash' ),
			'trash-post_' . $author_post_id
		);

		$this->assertSame( $expected, get_delete_post_link( $author_post_id ) );
	}

	/**
	 * Tests that get_delete_post_link() returns null when post type is not registered.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_returns_null_for_unregistered_post_type() {
		register_post_type(
			'cpt_unreg_test',
			array(
				'public'     => true,
				'_edit_link' => 'post.php?post=%d',
			)
		);

		$cpt_post_id = self::factory()->post->create(
			array(
				'post_type'   => 'cpt_unreg_test',
				'post_author' => self::$admin_id,
			)
		);

		unregister_post_type( 'cpt_unreg_test' );

		$this->assertNull(
			get_delete_post_link( $cpt_post_id ),
			'Should return null when post type object cannot be retrieved.'
		);
	}

	/**
	 * Tests that get_delete_post_link() supports custom post types with custom _edit_link.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_custom_post_type() {
		register_post_type(
			'cpt_delete_test',
			array(
				'public'     => true,
				'_edit_link' => 'post.php?post=%d',
			)
		);

		$cpt_post_id = self::factory()->post->create(
			array(
				'post_type'   => 'cpt_delete_test',
				'post_author' => self::$admin_id,
			)
		);

		$expected = wp_nonce_url(
			admin_url( 'post.php?post=' . $cpt_post_id . '&amp;action=trash' ),
			'trash-post_' . $cpt_post_id
		);

		$actual = get_delete_post_link( $cpt_post_id );

		unregister_post_type( 'cpt_delete_test' );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that get_delete_post_link() triggers deprecation notice when deprecated second argument is passed.
	 *
	 * @ticket 65819
	 * @expectedDeprecated get_delete_post_link
	 */
	public function test_get_delete_post_link_triggers_deprecation_for_deprecated_argument() {
		$link = get_delete_post_link( self::$post_id, 'deprecated_argument_value' );

		$this->assertNotNull( $link, 'Link should still be generated when deprecated argument is passed.' );
	}

	/**
	 * Tests that get_delete_post_link applies the 'get_delete_post_link' filter.
	 *
	 * @ticket 65819
	 */
	public function test_get_delete_post_link_applies_filter() {
		$filter_args = array();

		$filter_callback = static function ( $link, $post_id, $force_delete ) use ( &$filter_args ) {
			$filter_args = array(
				'link'         => $link,
				'post_id'      => $post_id,
				'force_delete' => $force_delete,
			);

			return 'https://example.org/custom-delete-handler?post=' . $post_id;
		};

		add_filter( 'get_delete_post_link', $filter_callback, 10, 3 );
		$actual = get_delete_post_link( self::$post_id, '', true );
		remove_filter( 'get_delete_post_link', $filter_callback, 10 );

		$this->assertSame( 'https://example.org/custom-delete-handler?post=' . self::$post_id, $actual );
		$this->assertSame( self::$post_id, $filter_args['post_id'] );
		$this->assertTrue( $filter_args['force_delete'] );
		$this->assertStringContainsString( 'action=delete', $filter_args['link'] );
	}
}
