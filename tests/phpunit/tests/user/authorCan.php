<?php

/**
 * Tests for author_can().
 *
 * @group user
 * @group capabilities
 *
 * @covers ::author_can
 */
class Tests_User_AuthorCan extends WP_UnitTestCase {

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Contributor user ID.
	 *
	 * @var int
	 */
	protected static $contributor_id;

	/**
	 * Published post by the editor.
	 *
	 * @var WP_Post
	 */
	protected static $editor_post;

	/**
	 * Draft post by the contributor.
	 *
	 * @var WP_Post
	 */
	protected static $contributor_draft;

	/**
	 * Published post by the contributor.
	 *
	 * @var WP_Post
	 */
	protected static $contributor_published;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id      = $factory->user->create( array( 'role' => 'editor' ) );
		self::$contributor_id = $factory->user->create( array( 'role' => 'contributor' ) );

		self::$editor_post           = $factory->post->create_and_get( array( 'post_author' => self::$editor_id ) );
		self::$contributor_draft     = $factory->post->create_and_get(
			array(
				'post_author' => self::$contributor_id,
				'post_status' => 'draft',
			)
		);
		self::$contributor_published = $factory->post->create_and_get( array( 'post_author' => self::$contributor_id ) );
	}

	/**
	 * Tests that the capability is checked against the post's author.
	 *
	 * @ticket 65819
	 */
	public function test_checks_capability_of_post_author() {
		$this->assertTrue( author_can( self::$editor_post, 'edit_others_posts' ), 'An editor should be able to edit others\' posts.' );
		$this->assertFalse( author_can( self::$contributor_draft, 'edit_others_posts' ), 'A contributor should not be able to edit others\' posts.' );
	}

	/**
	 * Tests that a post ID is accepted as well as a post object.
	 *
	 * @ticket 65819
	 */
	public function test_accepts_post_id() {
		$this->assertTrue( author_can( self::$editor_post->ID, 'publish_posts' ), 'The editor\'s post ID should resolve to the editor.' );
		$this->assertFalse( author_can( self::$contributor_draft->ID, 'publish_posts' ), 'The contributor\'s post ID should resolve to the contributor.' );
	}

	/**
	 * Tests that the result does not depend on the current user.
	 *
	 * @ticket 65819
	 */
	public function test_ignores_current_user() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertFalse( author_can( self::$contributor_draft, 'edit_others_posts' ) );
	}

	/**
	 * Tests that extra arguments are passed on, so meta capabilities are checked for the given object.
	 *
	 * @ticket 65819
	 */
	public function test_passes_extra_arguments_for_meta_capabilities() {
		$this->assertTrue(
			author_can( self::$contributor_draft, 'edit_post', self::$contributor_draft->ID ),
			'A contributor should be able to edit their own draft.'
		);
		$this->assertFalse(
			author_can( self::$contributor_draft, 'edit_post', self::$contributor_published->ID ),
			'A contributor should not be able to edit their own published post.'
		);
		$this->assertFalse(
			author_can( self::$contributor_draft, 'edit_post', self::$editor_post->ID ),
			'A contributor should not be able to edit another user\'s post.'
		);
	}

	/**
	 * Tests that false is returned for a post that does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_missing_post() {
		$this->assertFalse( author_can( PHP_INT_MAX, 'read' ) );
	}

	/**
	 * Tests that false is returned when the post's author does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_missing_author() {
		$post = self::factory()->post->create_and_get( array( 'post_author' => PHP_INT_MAX ) );

		$this->assertFalse( author_can( $post, 'read' ) );
	}
}
