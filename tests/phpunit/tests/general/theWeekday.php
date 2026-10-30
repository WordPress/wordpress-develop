<?php

/**
 * Tests for `the_weekday()` and `the_weekday_date()`.
 *
 * @group general
 * @group template
 * @group datetime
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
	 *
	 * @covers ::the_weekday
	 */
	public function test_the_weekday_should_echo_localized_weekday() {
		global $post;

		$post = get_post( self::$post_id );

		ob_start();
		the_weekday();
		$output = ob_get_clean();

		$this->assertSame( 'Friday', $output );
	}

	/**
	 * Tests that the_weekday() returns early when there is no current post.
	 *
	 * @ticket 65819
	 *
	 * @covers ::the_weekday
	 */
	public function test_the_weekday_without_post_should_return_early() {
		global $post;

		$post = null;

		ob_start();
		the_weekday();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Tests that the 'the_weekday' filter is applied.
	 *
	 * @ticket 65819
	 *
	 * @covers ::the_weekday
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

		ob_start();
		the_weekday();
		$output = ob_get_clean();

		$this->assertSame( 'Custom Friday', $output );
	}

	/**
	 * Tests that the_weekday_date() outputs the weekday with before and after strings.
	 *
	 * @ticket 65819
	 *
	 * @covers ::the_weekday_date
	 */
	public function test_the_weekday_date_should_output_with_before_and_after() {
		global $post, $currentday, $previousweekday;

		$post            = get_post( self::$post_id );
		$currentday      = '15.05.26';
		$previousweekday = '';

		ob_start();
		the_weekday_date( '<span>', '</span>' );
		$output = ob_get_clean();

		$this->assertSame( '<span>Friday</span>', $output );
		$this->assertSame( $currentday, $previousweekday, '$previousweekday should be updated to $currentday.' );
	}

	/**
	 * Tests that the_weekday_date() does not output if called on the same weekday.
	 *
	 * @ticket 65819
	 *
	 * @covers ::the_weekday_date
	 */
	public function test_the_weekday_date_should_not_output_duplicate_on_same_day() {
		global $post, $currentday, $previousweekday;

		$post            = get_post( self::$post_id );
		$currentday      = '15.05.26';
		$previousweekday = '15.05.26';

		ob_start();
		the_weekday_date( '<span>', '</span>' );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Tests that the_weekday_date() returns early when there is no current post.
	 *
	 * @ticket 65819
	 *
	 * @covers ::the_weekday_date
	 */
	public function test_the_weekday_date_without_post_should_return_early() {
		global $post;

		$post = null;

		ob_start();
		the_weekday_date();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Tests that the 'the_weekday_date' filter is applied.
	 *
	 * @ticket 65819
	 *
	 * @covers ::the_weekday_date
	 */
	public function test_the_weekday_date_filter_should_apply() {
		global $post, $currentday, $previousweekday;

		$post            = get_post( self::$post_id );
		$currentday      = '15.05.26';
		$previousweekday = '';

		add_filter(
			'the_weekday_date',
			static function ( $weekday_date ) {
				return 'Prefix: ' . $weekday_date;
			}
		);

		ob_start();
		the_weekday_date( '<span>', '</span>' );
		$output = ob_get_clean();

		$this->assertSame( 'Prefix: <span>Friday</span>', $output );
	}
}
