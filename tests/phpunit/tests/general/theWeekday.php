<?php

/**
 * Tests for `the_weekday()`.
 *
 * @group general
 * @group template
 * @group datetime
 *
 * @covers ::the_weekday
 */
class Tests_General_TheWeekday extends WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// 2026-05-15 was a Friday.
		self::$post_id = $factory->post->create(
			array(
				'post_date' => '2026-05-15 12:00:00',
			)
		);
	}

	/**
	 * Tests that the_weekday() outputs the localized weekday for the post.
	 *
	 * @ticket 65819
	 */
	public function test_the_weekday_should_echo_localized_weekday() {
		global $post;

		$post = get_post( self::$post_id );

		$this->expectOutputString( 'Friday' );
		the_weekday();
	}

	/**
	 * Tests that the_weekday() returns early when there is no current post.
	 *
	 * @ticket 65819
	 */
	public function test_the_weekday_without_post_should_return_early() {
		global $post;

		$post = null;

		$this->expectOutputString( '' );
		the_weekday();
	}

	/**
	 * Tests that the 'the_weekday' filter is applied.
	 *
	 * @ticket 65819
	 */
	public function test_the_weekday_filter_should_apply() {
		global $post;

		$post = get_post( self::$post_id );

		add_filter(
			'the_weekday',
			static function ( $weekday ) {
				return 'Custom ' . $weekday;
			}
		);

		$this->expectOutputString( 'Custom Friday' );
		the_weekday();
	}
}
