<?php

/**
 * @group date
 * @group datetime
 * @group feed
 */
class Tests_Date_Get_Feed_Build_Date extends WP_UnitTestCase {

	function tearDown() {
		global $wp_query;

		update_option( 'timezone_string', 'UTC' );

		unset( $wp_query );

		parent::tearDown();
	}

	/**
	 * @ticket 48675
	 */
	public function test_should_return_correct_feed_build_date() {
		global $wp_query;

		$timezone = 'America/Chicago';
		update_option( 'timezone_string', $timezone );

		$post_id = self::factory()->post->create(
			array(
				'post_date'     => '2018-07-22 21:13:23',
				'post_date_gmt' => '2018-07-23 03:13:23',
			)
		);

		$wp_query = new WP_Query( array( 'p' => $post_id ) );

		$this->assertEquals( '2018-07-23T03:13:23+00:00', get_feed_build_date( DATE_RFC3339 ) );
	}
}
