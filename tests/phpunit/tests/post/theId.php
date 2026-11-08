<?php

/**
 * Tests for the_ID() and get_the_ID().
 *
 * @group post
 *
 * @covers ::the_ID
 * @covers ::get_the_ID
 */
class Tests_Post_TheId extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Sets up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Tests that get_the_ID() returns false when no post is in scope.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_id_returns_false_when_no_post() {
		$this->assertFalse( get_the_ID() );
	}

	/**
	 * Tests that the_ID() displays the ID of the current post in the loop.
	 *
	 * @ticket 65819
	 */
	public function test_the_id_displays_current_post_id() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$this->expectOutputString( (string) self::$post_id );
		the_ID();
	}

	/**
	 * Tests that the_ID() produces no output when no post is in scope.
	 *
	 * @ticket 65819
	 */
	public function test_the_id_produces_no_output_when_no_post() {
		$this->expectOutputString( '' );
		the_ID();
	}
}
