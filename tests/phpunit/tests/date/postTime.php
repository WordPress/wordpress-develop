<?php

/**
 * @group date
 * @group datetime
 */
class Tests_Date_Post_Time extends WP_UnitTestCase {

	/**
	 * @ticket 25002
	 */
	public function test_should_return_wp_timestamp() {
		$timezone = 'Europe/Kiev';
		update_option( 'timezone_string', $timezone );

		$datetime     = new DateTimeImmutable( 'now', new DateTimeZone( $timezone ) );
		$mysql        = $datetime->format( 'Y-m-d H:i:s' );
		$timestamp    = $datetime->getTimestamp();
		$wp_timestamp = $datetime->getTimestamp() + $datetime->getOffset();

		$post_id = self::factory()->post->create(
			array(
				'post_date'     => $mysql,
				'post_modified' => $mysql,
			)
		);

		$this->assertEquals( $wp_timestamp, get_post_time( 'U', false, $post_id ) );
		$this->assertEquals( $wp_timestamp, get_post_time( 'G', false, $post_id ) );
		$this->assertEquals( $timestamp, get_post_time( 'U', true, $post_id ) );
		$this->assertEquals( $timestamp, get_post_time( 'G', true, $post_id ) );
		$this->assertEquals( $wp_timestamp, get_post_modified_time( 'U', false, $post_id ) );
		$this->assertEquals( $wp_timestamp, get_post_modified_time( 'G', false, $post_id ) );
		$this->assertEquals( $timestamp, get_post_modified_time( 'U', true, $post_id ) );
		$this->assertEquals( $timestamp, get_post_modified_time( 'G', true, $post_id ) );
	}

	/**
	 * @ticket 25002
	 */
	public function test_should_return_time() {
		$timezone = 'Europe/Kiev';
		update_option( 'timezone_string', $timezone );

		$datetime    = new DateTimeImmutable( 'now', new DateTimeZone( $timezone ) );
		$mysql       = $datetime->format( 'Y-m-d H:i:s' );
		$rfc3339     = $datetime->format( DATE_RFC3339 );
		$rfc3339_utc = $datetime->setTimezone( new DateTimeZone( 'UTC' ) )->format( DATE_RFC3339 );
		$post_id     = self::factory()->post->create(
			array(
				'post_date'     => $mysql,
				'post_modified' => $mysql,
			)
		);

		$this->assertEquals( $rfc3339, get_post_time( DATE_RFC3339, false, $post_id ) );
		$this->assertEquals( $rfc3339_utc, get_post_time( DATE_RFC3339, true, $post_id ) );
		$this->assertEquals( $rfc3339, get_post_time( DATE_RFC3339, false, $post_id, true ) );
		$this->assertEquals( $rfc3339_utc, get_post_time( DATE_RFC3339, true, $post_id, true ) );
		$this->assertEquals( $rfc3339, get_post_modified_time( DATE_RFC3339, false, $post_id ) );
		$this->assertEquals( $rfc3339_utc, get_post_modified_time( DATE_RFC3339, true, $post_id ) );
		$this->assertEquals( $rfc3339, get_post_modified_time( DATE_RFC3339, false, $post_id, true ) );
		$this->assertEquals( $rfc3339_utc, get_post_modified_time( DATE_RFC3339, true, $post_id, true ) );
	}

	/**
	 * @ticket 48384
	 */
	public function test_should_keep_utc_time_on_timezone_change() {
		$timezone = 'UTC';
		update_option( 'timezone_string', $timezone );

		$datetime = new DateTimeImmutable( 'now', new DateTimeZone( $timezone ) );
		$mysql    = $datetime->format( 'Y-m-d H:i:s' );
		$rfc3339  = $datetime->format( DATE_RFC3339 );
		$post_id  = self::factory()->post->create(
			array(
				'post_date'     => $mysql,
				'post_modified' => $mysql,
			)
		);

		update_option( 'timezone_string', 'Europe/Kiev' );

		$this->assertEquals( $rfc3339, get_post_time( DATE_RFC3339, true, $post_id ) );
		$this->assertEquals( $rfc3339, get_post_modified_time( DATE_RFC3339, true, $post_id ) );
	}
}
