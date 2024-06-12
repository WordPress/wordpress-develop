<?php

/**
 * @group date
 * @group datetime
 * @group post
 * @covers ::get_the_modified_date
 */
class Tests_Date_GetTheModifiedDate extends WP_UnitTestCase {

	/**
	 * Test get_the_modified_time with post_id parameter.
	 *
	 * @ticket 37059
	 *
	 * @since 4.6.0
	 */
	public function test_get_the_modified_date_with_post_id() {
		$details  = array(
			'post_date'     => '2016-01-21 15:34:36',
			'post_date_gmt' => '2016-01-21 15:34:36',
		);
		$post_id  = self::factory()->post->create( $details );
		$format   = 'Y-m-d';
		$expected = '2016-01-21';
		$actual   = get_the_modified_date( $format, $post_id );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test get_the_modified_date
	 *
	 * @ticket 37059
	 *
	 * @since 4.6.0
	 */
	public function test_get_the_modified_date_default() {
		$details = array(
			'post_date'     => '2016-01-21 15:34:36',
			'post_date_gmt' => '2016-01-21 15:34:36',
		);
		$post_id = self::factory()->post->create( $details );
		$post    = get_post( $post_id );

		$GLOBALS['post'] = $post;

		$expected = '2016-01-21';
		$format   = 'Y-m-d';
		$actual   = get_the_modified_date( $format );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test get_the_modified_date failures are filtered
	 *
	 * @ticket 37059
	 *
	 * @since 4.6.0
	 */
	public function test_get_the_modified_date_failures_are_filtered() {
		// Remove global post object.
		$GLOBALS['post'] = null;

		$expected = 'filtered modified date failure result';
		add_filter( 'get_the_modified_date', array( $this, '_filter_get_the_modified_date_failure' ) );
		$actual = get_the_modified_date();
		$this->assertSame( $expected, $actual );
		remove_filter( 'get_the_modified_date', array( $this, '_filter_get_the_modified_date_failure' ) );
	}

	public function _filter_get_the_modified_date_failure( $the_date ) {
		$expected = false;
		$actual   = $the_date;
		$this->assertSame( $expected, $actual );

		if ( false === $the_date ) {
			return 'filtered modified date failure result';
		}
		return $the_date;
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_the_modified_date_returns_false_with_null_or_non_existing_post() {
		$this->assertFalse( get_the_modified_date() );
		$this->assertFalse( get_the_modified_date( 'F j, Y h:i:s' ) );
		$this->assertFalse( get_the_modified_date( '', 9 ) );
		$this->assertFalse( get_the_modified_date( 'F j, Y h:i:s', 9 ) );
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_the_modified_date_returns_correct_time_with_empty_format() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2020-08-31 23:14:00' ) );

		$this->assertSame( 'August 31, 2020', get_the_modified_date( '', $post_id ) );
		$this->assertSame( 'August 31, 2020', get_the_modified_date( false, $post_id ) );
	}

	/**
	 * Test get_the_modified_time with post_id parameter.
	 *
	 * @ticket 37059
	 *
	 * @since 4.6.0
	 */
	public function test_get_the_modified_time_with_post_id() {
		$details  = array(
			'post_date'     => '2016-01-21 15:34:36',
			'post_date_gmt' => '2016-01-21 15:34:36',
		);
		$post_id  = self::factory()->post->create( $details );
		$format   = 'G';
		$expected = 1453390476;
		$actual   = get_the_modified_time( $format, $post_id );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test get_the_modified_time
	 *
	 * @ticket 37059
	 *
	 * @since 4.6.0
	 */
	public function test_get_the_modified_time_default() {
		$details = array(
			'post_date'     => '2016-01-21 15:34:36',
			'post_date_gmt' => '2016-01-21 15:34:36',
		);
		$post_id = self::factory()->post->create( $details );
		$post    = get_post( $post_id );

		$GLOBALS['post'] = $post;

		$expected = 1453390476;
		$format   = 'G';
		$actual   = get_the_modified_time( $format );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test get_the_modified_time failures are filtered
	 *
	 * @ticket 37059
	 *
	 * @since 4.6.0
	 */
	public function test_get_the_modified_time_failures_are_filtered() {
		// Remove global post object.
		$GLOBALS['post'] = null;

		$expected = 'filtered modified time failure result';
		add_filter( 'get_the_modified_time', array( $this, '_filter_get_the_modified_time_failure' ) );
		$actual = get_the_modified_time();
		$this->assertSame( $expected, $actual );
		remove_filter( 'get_the_modified_time', array( $this, '_filter_get_the_modified_time_failure' ) );
	}

	public function _filter_get_the_modified_time_failure( $the_time ) {
		$expected = false;
		$actual   = $the_time;
		$this->assertSame( $expected, $actual );

		if ( false === $the_time ) {
			return 'filtered modified time failure result';
		}
		return $the_time;
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_the_modified_time_returns_false_with_null_or_non_existing_post() {
		$this->assertFalse( get_the_modified_time() );
		$this->assertFalse( get_the_modified_time( 'h:i:s' ) );
		$this->assertFalse( get_the_modified_time( '', 9 ) );
		$this->assertFalse( get_the_modified_time( 'h:i:s', 9 ) );
	}

	/**
	 * @ticket 51184
	 */
	public function test_get_the_modified_time_returns_correct_time_with_empty_format() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2020-08-31 23:14:00' ) );

		$this->assertSame( '11:14 pm', get_the_modified_time( '', $post_id ) );
		$this->assertSame( '11:14 pm', get_the_modified_time( false, $post_id ) );
	}
}
