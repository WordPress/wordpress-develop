<?php

/**
 * @group link
 *
 * @covers ::get_edit_tag_link
 */
class Tests_Link_GetEditTagLink extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Tag ID.
	 *
	 * @var int
	 */
	protected static $tag_id;

	/**
	 * Tag object.
	 *
	 * @var WP_Term
	 */
	protected static $tag_object;

	/**
	 * Custom taxonomy term ID.
	 *
	 * @var int
	 */
	protected static $custom_term_id;

	/**
	 * Set up before class.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		self::$tag_id     = $factory->tag->create( array( 'name' => 'Test Tag' ) );
		self::$tag_object = get_term( self::$tag_id, 'post_tag' );

		register_taxonomy( 'wptests_tag_tax', 'post' );
		self::$custom_term_id = $factory->term->create(
			array(
				'taxonomy' => 'wptests_tag_tax',
				'name'     => 'Custom Tax Tag',
			)
		);
	}

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
		register_taxonomy( 'wptests_tag_tax', 'post' );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		unregister_taxonomy( 'wptests_tag_tax' );
		parent::tear_down();
	}

	/**
	 * Tests get_edit_tag_link() with a tag ID.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_tag_link_with_tag_id() {
		$actual   = get_edit_tag_link( self::$tag_id );
		$expected = admin_url( 'term.php?taxonomy=post_tag&tag_ID=' . self::$tag_id . '&post_type=post' );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests get_edit_tag_link() with a tag object.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_tag_link_with_tag_object() {
		$actual   = get_edit_tag_link( self::$tag_object );
		$expected = admin_url( 'term.php?taxonomy=post_tag&tag_ID=' . self::$tag_id . '&post_type=post' );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests get_edit_tag_link() with a custom taxonomy.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_tag_link_with_custom_taxonomy() {
		$actual   = get_edit_tag_link( self::$custom_term_id, 'wptests_tag_tax' );
		$expected = admin_url( 'term.php?taxonomy=wptests_tag_tax&tag_ID=' . self::$custom_term_id . '&post_type=post' );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that get_edit_tag_link() returns null for a user without edit permissions.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_tag_link_unauthorized_user() {
		wp_set_current_user( self::$subscriber_id );

		$this->assertNull( get_edit_tag_link( self::$tag_id ) );
	}

	/**
	 * Tests that get_edit_tag_link() returns null for an invalid tag ID.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_tag_link_invalid_tag_id() {
		$this->assertNull( get_edit_tag_link( 999999 ) );
	}

	/**
	 * Tests that get_edit_tag_link() returns null for an empty tag.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_tag_link_empty_tag() {
		$this->assertNull( get_edit_tag_link( '' ) );
	}

	/**
	 * Tests the 'get_edit_tag_link' filter.
	 *
	 * @ticket 65819
	 */
	public function test_get_edit_tag_link_filter() {
		add_filter(
			'get_edit_tag_link',
			static function ( $link ) {
				return $link . '&custom_param=1';
			}
		);

		$actual   = get_edit_tag_link( self::$tag_id );
		$expected = admin_url( 'term.php?taxonomy=post_tag&tag_ID=' . self::$tag_id . '&post_type=post&custom_param=1' );

		$this->assertSame( $expected, $actual );
	}
}
