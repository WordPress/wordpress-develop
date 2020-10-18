<?php

/**
 * @group date
 * @group datetime
 * @group post
 */
class Tests_Date_Get_Post_Time extends WP_UnitTestCase {

	/**
	 * @ticket 28310
	 */
	public function test_get_post_time_returns_correct_time_with_post_id() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2014-03-01 16:35:00' ) );

		$this->assertSame( '16:35:00', get_post_time( 'H:i:s', false, $post_id ) );
	}

	/**
	 * @ticket 28310
	 */
	public function test_get_post_time_returns_false_with_null_or_non_existing_post() {
		$this->assertFalse( get_post_time() );
		$this->assertFalse( get_post_time( 'h:i:s' ) );
		$this->assertFalse( get_post_time( '', false, 9 ) );
		$this->assertFalse( get_post_time( 'h:i:s', false, 9 ) );
	}

	/**
	 * @ticket 28310
	 */
	public function test_get_post_modified_time_returns_correct_time_with_post_id() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2014-03-01 16:35:00' ) );

		$this->assertSame( '16:35:00', get_post_modified_time( 'H:i:s', false, $post_id ) );
	}

	/**
	 * @ticket 28310
	 */
	public function test_get_post_modified_time_returns_false_with_null_or_non_existing_post() {
		$this->assertFalse( get_post_modified_time() );
		$this->assertFalse( get_post_modified_time( 'h:i:s' ) );
		$this->assertFalse( get_post_modified_time( '', false, 9 ) );
		$this->assertFalse( get_post_modified_time( 'h:i:s', false, 9 ) );
	}

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

		$this->assertSame( $wp_timestamp, get_post_time( 'U', false, $post_id ) );
		$this->assertSame( $wp_timestamp, get_post_time( 'G', false, $post_id ) );
		$this->assertSame( $timestamp, get_post_time( 'U', true, $post_id ) );
		$this->assertSame( $timestamp, get_post_time( 'G', true, $post_id ) );
		$this->assertSame( $wp_timestamp, get_post_modified_time( 'U', false, $post_id ) );
		$this->assertSame( $wp_timestamp, get_post_modified_time( 'G', false, $post_id ) );
		$this->assertSame( $timestamp, get_post_modified_time( 'U', true, $post_id ) );
		$this->assertSame( $timestamp, get_post_modified_time( 'G', true, $post_id ) );
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

		$this->assertSame( $rfc3339, get_post_time( DATE_RFC3339, false, $post_id ) );
		$this->assertSame( $rfc3339_utc, get_post_time( DATE_RFC3339, true, $post_id ) );
		$this->assertSame( $rfc3339, get_post_time( DATE_RFC3339, false, $post_id, true ) );
		$this->assertSame( $rfc3339_utc, get_post_time( DATE_RFC3339, true, $post_id, true ) );
		$this->assertSame( $rfc3339, get_post_modified_time( DATE_RFC3339, false, $post_id ) );
		$this->assertSame( $rfc3339_utc, get_post_modified_time( DATE_RFC3339, true, $post_id ) );
		$this->assertSame( $rfc3339, get_post_modified_time( DATE_RFC3339, false, $post_id, true ) );
		$this->assertSame( $rfc3339_utc, get_post_modified_time( DATE_RFC3339, true, $post_id, true ) );
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

		$this->assertSame( $rfc3339, get_post_time( DATE_RFC3339, true, $post_id ) );
		$this->assertSame( $rfc3339, get_post_modified_time( DATE_RFC3339, true, $post_id ) );
	}
}
