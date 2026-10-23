<?php

/**
 * Tests for the_author().
 *
 * @group author
 * @group user
 *
 * @covers ::the_author
 */
class Tests_User_TheAuthor extends WP_UnitTestCase {

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	private static $author_id = 0;

	/**
	 * Post ID authored by self::$author_id.
	 *
	 * @var int
	 */
	private static $post_id = 0;

	/**
	 * Creates shared author and post fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'test_author',
				'display_name' => 'Test Author',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$author_id,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Sets up the global post data before each test.
	 */
	public function set_up() {
		parent::set_up();

		setup_postdata( get_post( self::$post_id ) );
	}

	/**
	 * Tests that the_author() outputs and returns the current post author's display name.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_and_returns_author_display_name() {
		ob_start();
		$returned = the_author();
		$output   = ob_get_clean();

		$this->assertSame( 'Test Author', $returned );
		$this->assertSame( 'Test Author', $output );
	}

	/**
	 * Tests that passing a non-empty first argument triggers a deprecated argument notice.
	 *
	 * @ticket 65819
	 *
	 * @expectedDeprecated the_author
	 */
	public function test_triggers_deprecated_argument_for_first_parameter() {
		$output = get_echo( 'the_author', array( 'deprecated_value' ) );

		$this->assertSame( 'Test Author', $output );
	}

	/**
	 * Tests that passing false for the second argument triggers a deprecated argument notice
	 * and returns the display name without echoing it.
	 *
	 * @ticket 65819
	 *
	 * @expectedDeprecated the_author
	 */
	public function test_returns_without_echoing_when_deprecated_echo_is_false() {
		ob_start();
		$returned = the_author( '', false );
		$output   = ob_get_clean();

		$this->assertSame( 'Test Author', $returned );
		$this->assertSame( '', $output );
	}
}
