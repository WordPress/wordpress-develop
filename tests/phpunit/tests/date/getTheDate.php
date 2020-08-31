<?php

/**
 * @group date
 * @group datetime
 * @group post
 */
class Tests_Date_Get_The_Date extends WP_UnitTestCase {

	/**
	 * @ticket 13771
	 */
	function test_get_the_date_with_id_returns_correct_time() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2014-03-01 16:35:00' ) );
		$this->assertEquals( 'March 1, 2014', get_the_date( 'F j, Y', $post_id ) );
	}

	/**
	 * @ticket 28310
	 */
	function test_get_the_date_returns_false_with_null_or_non_existing_post() {
		$this->assertFalse( get_the_date() );
		$this->assertFalse( get_the_date( 'F j, Y h:i:s' ) );
		$this->assertFalse( get_the_date( '', 9 ) );
		$this->assertFalse( get_the_date( 'F j, Y h:i:s', 9 ) );
	}

	/**
	 * @ticket 28310
	 */
	function test_get_the_time_with_id_returns_correct_time() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2014-03-01 16:35:00' ) );
		$this->assertEquals( '16:35:00', get_the_time( 'H:i:s', $post_id ) );
	}

	/**
	 * @ticket 28310
	 */
	function test_get_the_time_returns_false_with_null_or_non_existing_post() {
		$this->assertFalse( get_the_time() );
		$this->assertFalse( get_the_time( 'h:i:s' ) );
		$this->assertFalse( get_the_time( '', 9 ) );
		$this->assertFalse( get_the_time( 'h:i:s', 9 ) );
	}
}
