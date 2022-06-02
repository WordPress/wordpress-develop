<?php

/**
 * @group date
 * @group datetime
 * @group comment
 * @covers ::get_comment_date
 */
class Tests_Date_GetCommentDate extends WP_UnitTestCase {

	/**
	 * @ticket 51184
	 */
	public function test_get_comment_date_returns_correct_time_with_comment_id() {
		$c = self::factory()->comment->create( array( 'comment_date' => '2020-08-29 01:51:00' ) );

		$this->assertSame( 'August 29, 2020', get_comment_date( 'F j, Y', $c ) );
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_comment_date_returns_correct_time_with_empty_format() {
		$c = self::factory()->comment->create( array( 'comment_date' => '2020-08-29 01:51:00' ) );

		$this->assertSame( 'August 29, 2020', get_comment_date( '', $c ) );
		$this->assertSame( 'August 29, 2020', get_comment_date( false, $c ) );
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_comment_time_returns_correct_time() {
		$c = self::factory()->comment->create( array( 'comment_date' => '2020-08-29 01:51:00' ) );

		$GLOBALS['comment'] = get_comment( $c );
		$this->assertSame( '1:51 am', get_comment_time( 'g:i a' ) );
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_comment_time_returns_correct_time_with_empty_format() {
		$c = self::factory()->comment->create( array( 'comment_date' => '2020-08-29 01:51:00' ) );

		$GLOBALS['comment'] = get_comment( $c );
		$this->assertSame( '1:51 am', get_comment_time( '' ) );
		$this->assertSame( '1:51 am', get_comment_time( false ) );
	}
}
