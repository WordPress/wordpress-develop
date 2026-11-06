<?php

/**
 * Tests for check_comment_flood_db().
 *
 * @group comment
 *
 * @covers ::check_comment_flood_db
 */
class Tests_Comment_CheckCommentFloodDb extends WP_UnitTestCase {

	/**
	 * Tests that the function adds wp_check_comment_flood() to the `wp_is_comment_flood` filter.
	 *
	 * @ticket 66494
	 */
	public function test_should_add_wp_check_comment_flood_to_wp_is_comment_flood() {
		remove_filter( 'wp_is_comment_flood', 'wp_check_comment_flood', 10 );
		$this->assertFalse( has_filter( 'wp_is_comment_flood', 'wp_check_comment_flood' ), 'The filter should not be added yet.' );

		check_comment_flood_db();

		$this->assertSame( 10, has_filter( 'wp_is_comment_flood', 'wp_check_comment_flood' ) );
	}

	/**
	 * Tests that the function runs when the `check_comment_flood` action fires.
	 *
	 * @ticket 66494
	 */
	public function test_should_be_hooked_to_check_comment_flood_by_default() {
		$this->assertSame( 10, has_action( 'check_comment_flood', 'check_comment_flood_db' ) );

		remove_filter( 'wp_is_comment_flood', 'wp_check_comment_flood', 10 );

		do_action( 'check_comment_flood', '192.0.2.1', 'bob@example.org', gmdate( 'Y-m-d H:i:s' ), false );

		$this->assertSame( 10, has_filter( 'wp_is_comment_flood', 'wp_check_comment_flood' ) );
	}

	/**
	 * Tests that the check can be removed by unhooking the function from the action.
	 *
	 * @ticket 66494
	 */
	public function test_should_not_add_the_filter_when_unhooked_from_check_comment_flood() {
		remove_filter( 'wp_is_comment_flood', 'wp_check_comment_flood', 10 );
		remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		do_action( 'check_comment_flood', '192.0.2.1', 'bob@example.org', gmdate( 'Y-m-d H:i:s' ), false );

		$this->assertFalse( has_filter( 'wp_is_comment_flood', 'wp_check_comment_flood' ) );
	}
}
