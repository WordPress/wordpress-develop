<?php

/**
 * Tests for post_custom().
 *
 * @group post
 *
 * @covers ::post_custom
 */
class Tests_Post_PostCustom extends WP_UnitTestCase {

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

		add_post_meta( self::$post_id, 'single_key', 'single_value' );
		add_post_meta( self::$post_id, 'multi_key', 'first_value' );
		add_post_meta( self::$post_id, 'multi_key', 'second_value' );
	}

	/**
	 * Tests that post_custom() returns a single value when the key has one entry.
	 *
	 * @ticket 65819
	 */
	public function test_post_custom_returns_single_value() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$this->assertSame( 'single_value', post_custom( 'single_key' ) );
	}

	/**
	 * Tests that post_custom() returns an array when the key has multiple entries.
	 *
	 * @ticket 65819
	 */
	public function test_post_custom_returns_array_for_multiple_values() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$this->assertSame( array( 'first_value', 'second_value' ), post_custom( 'multi_key' ) );
	}

	/**
	 * Tests that post_custom() returns false for a non-existent key.
	 *
	 * @ticket 65819
	 */
	public function test_post_custom_returns_false_for_non_existent_key() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$this->assertFalse( post_custom( 'non_existent_key' ) );
	}
}
