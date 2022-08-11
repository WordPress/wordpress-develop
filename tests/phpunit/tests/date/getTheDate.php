<?php

/**
 * @group date
 * @group datetime
 * @group post
 * @covers ::get_the_date
 */
class Tests_Date_GetTheDate extends WP_UnitTestCase {

	/**
	 * @ticket 13771
	 */
	public function test_get_the_date_returns_correct_time_with_post_id() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2014-03-01 16:35:00' ) );

		$this->assertSame( 'March 1, 2014', get_the_date( 'F j, Y', $post_id ) );
	}

	/**
	 * @ticket 28310
	 */
	public function test_get_the_date_returns_false_with_null_or_non_existing_post() {
		$this->assertFalse( get_the_date() );
		$this->assertFalse( get_the_date( 'F j, Y h:i:s' ) );
		$this->assertFalse( get_the_date( '', 9 ) );
		$this->assertFalse( get_the_date( 'F j, Y h:i:s', 9 ) );
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_the_date_returns_correct_time_with_empty_format() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2020-08-29 01:51:00' ) );

		$this->assertSame( 'August 29, 2020', get_the_date( '', $post_id ) );
		$this->assertSame( 'August 29, 2020', get_the_date( false, $post_id ) );
	}

	/**
	 * @ticket 28310
	 */
	public function test_get_the_time_returns_correct_time_with_post_id() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2014-03-01 16:35:00' ) );

		$this->assertSame( '16:35:00', get_the_time( 'H:i:s', $post_id ) );
	}

	/**
	 * @ticket 28310
	 */
	public function test_get_the_time_returns_false_with_null_or_non_existing_post() {
		$this->assertFalse( get_the_time() );
		$this->assertFalse( get_the_time( 'h:i:s' ) );
		$this->assertFalse( get_the_time( '', 9 ) );
		$this->assertFalse( get_the_time( 'h:i:s', 9 ) );
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_the_time_returns_correct_time_with_empty_format() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2020-08-29 01:51:00' ) );

		$this->assertSame( '1:51 am', get_the_time( '', $post_id ) );
		$this->assertSame( '1:51 am', get_the_time( false, $post_id ) );
	}
}
