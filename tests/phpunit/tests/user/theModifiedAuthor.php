<?php

/**
 * Tests for the_modified_author().
 *
 * @group author
 * @group user
 *
 * @covers ::the_modified_author
 */
class Tests_User_TheModifiedAuthor extends WP_UnitTestCase {

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	protected static $author_id = 0;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	protected static $editor_id = 0;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected static $post_id = 0;

	/**
	 * Sets up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'initial_author',
				'display_name' => 'Initial Author',
			)
		);

		self::$editor_id = $factory->user->create(
			array(
				'role'         => 'editor',
				'user_login'   => 'last_editor',
				'display_name' => 'Last Editor',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$author_id,
				'post_status' => 'publish',
				'post_title'  => 'Test Post for Modified Author',
			)
		);

		add_post_meta( self::$post_id, '_edit_last', self::$editor_id );
	}

	/**
	 * Sets up global post before each test.
	 */
	public function set_up() {
		parent::set_up();
		$GLOBALS['post'] = get_post( self::$post_id );
	}

	/**
	 * Tears down global post after each test.
	 */
	public function tear_down() {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * Tests that the_modified_author() displays the display name of the last editor.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_author_displays_editor_display_name() {
		$this->expectOutputString( 'Last Editor' );
		the_modified_author();
	}

	/**
	 * Tests that the_modified_author() outputs nothing when post has no _edit_last meta.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_author_without_edit_last_meta() {
		delete_post_meta( self::$post_id, '_edit_last' );

		$this->expectOutputString( '' );
		the_modified_author();

		add_post_meta( self::$post_id, '_edit_last', self::$editor_id );
	}

	/**
	 * Tests that the 'the_modified_author' filter can modify the displayed output.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_author_filter_modifies_output() {
		$custom_filter = static function ( $display_name ) {
			return 'Edited by ' . $display_name;
		};

		add_filter( 'the_modified_author', $custom_filter );

		$this->expectOutputString( 'Edited by Last Editor' );
		the_modified_author();

		remove_filter( 'the_modified_author', $custom_filter );
	}
}
