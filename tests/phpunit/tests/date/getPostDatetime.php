<?php

/**
 * Tests for the `get_post_datetime()` and `get_post_timestamp()` functions.
 *
 * @group date
 * @group datetime
 * @group post
 */
class Tests_Date_GetPostDatetime extends WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * The local and UTC dates are deliberately out of sync, so the tests can
	 * tell which database field each result came from.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Original timezone_string option.
	 *
	 * @var string
	 */
	private $original_timezone_string;

	/**
	 * Original gmt_offset option.
	 *
	 * @var string|float
	 */
	private $original_gmt_offset;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		self::$post_id = $factory->post->create(
			array(
				'post_date'     => '2024-03-15 10:30:00',
				'post_date_gmt' => '2024-03-15 05:00:00',
			)
		);

		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => '2024-06-01 12:00:00',
				'post_modified_gmt' => '2024-06-01 06:00:00',
			),
			array( 'ID' => self::$post_id )
		);
		clean_post_cache( self::$post_id );
	}

	public function set_up() {
		parent::set_up();

		$this->original_timezone_string = get_option( 'timezone_string' );
		$this->original_gmt_offset      = get_option( 'gmt_offset' );

		update_option( 'timezone_string', 'Europe/Helsinki' );
	}

	public function tear_down() {
		update_option( 'timezone_string', $this->original_timezone_string );
		update_option( 'gmt_offset', $this->original_gmt_offset );

		parent::tear_down();
	}

	/**
	 * Tests the returned time for each database field and source.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_datetime
	 *
	 * @dataProvider data_get_post_datetime_fields_and_sources
	 *
	 * @param string $field    Field to read, 'date' or 'modified'.
	 * @param string $source   Source to read, 'local' or 'gmt'.
	 * @param string $expected Expected time in RFC 3339 format.
	 */
	public function test_get_post_datetime_fields_and_sources( $field, $source, $expected ) {
		$datetime = get_post_datetime( self::$post_id, $field, $source );

		$this->assertInstanceOf( 'DateTimeImmutable', $datetime );
		$this->assertSame( $expected, $datetime->format( DATE_RFC3339 ), 'The time should come from the requested field and source.' );
		$this->assertSame( 'Europe/Helsinki', $datetime->getTimezone()->getName(), 'The time should use the site timezone.' );
	}

	/**
	 * Data provider for test_get_post_datetime_fields_and_sources().
	 *
	 * @return array[]
	 */
	public static function data_get_post_datetime_fields_and_sources() {
		return array(
			'published, local' => array( 'date', 'local', '2024-03-15T10:30:00+02:00' ),
			'published, UTC'   => array( 'date', 'gmt', '2024-03-15T07:00:00+02:00' ),
			'modified, local'  => array( 'modified', 'local', '2024-06-01T12:00:00+03:00' ),
			'modified, UTC'    => array( 'modified', 'gmt', '2024-06-01T09:00:00+03:00' ),
		);
	}

	/**
	 * Tests that get_post_datetime() defaults to the published local time.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_datetime
	 */
	public function test_get_post_datetime_defaults_to_published_local_time() {
		$this->assertSame( '2024-03-15T10:30:00+02:00', get_post_datetime( self::$post_id )->format( DATE_RFC3339 ) );
	}

	/**
	 * Tests that get_post_datetime() accepts a post object and defaults to the global post.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_datetime
	 */
	public function test_get_post_datetime_accepts_post_object_and_global_post() {
		$post = get_post( self::$post_id );

		$this->assertSame( '2024-03-15T10:30:00+02:00', get_post_datetime( $post )->format( DATE_RFC3339 ), 'A post object should be accepted.' );

		$GLOBALS['post'] = $post;

		$this->assertSame( '2024-03-15T10:30:00+02:00', get_post_datetime()->format( DATE_RFC3339 ), 'The global post should be used by default.' );
	}

	/**
	 * Tests that a local time is interpreted in a fixed UTC offset timezone.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_datetime
	 */
	public function test_get_post_datetime_with_utc_offset_timezone() {
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', 5.5 );

		$this->assertSame( '2024-03-15T10:30:00+05:30', get_post_datetime( self::$post_id )->format( DATE_RFC3339 ) );
	}

	/**
	 * Tests that get_post_datetime() returns false for a missing post.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_datetime
	 */
	public function test_get_post_datetime_returns_false_for_missing_post() {
		$this->assertFalse( get_post_datetime( PHP_INT_MAX ), 'A missing post ID should return false.' );
		$this->assertFalse( get_post_datetime(), 'No global post should return false.' );
	}

	/**
	 * Tests that get_post_datetime() returns false for empty or invalid stored times.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_datetime
	 *
	 * @dataProvider data_get_post_datetime_invalid_times
	 *
	 * @param string $field  Field to read, 'date' or 'modified'.
	 * @param string $source Source to read, 'local' or 'gmt'.
	 * @param string $time   Stored time.
	 */
	public function test_get_post_datetime_returns_false_for_invalid_time( $field, $source, $time ) {
		$columns = array(
			'date'     => array(
				'local' => 'post_date',
				'gmt'   => 'post_date_gmt',
			),
			'modified' => array(
				'local' => 'post_modified',
				'gmt'   => 'post_modified_gmt',
			),
		);

		$post                                  = get_post( self::$post_id );
		$post->{$columns[ $field ][ $source ]} = $time;

		$this->assertFalse( get_post_datetime( $post, $field, $source ) );
	}

	/**
	 * Data provider for test_get_post_datetime_returns_false_for_invalid_time().
	 *
	 * @return array[]
	 */
	public static function data_get_post_datetime_invalid_times() {
		return array(
			'empty published local time'     => array( 'date', 'local', '' ),
			'zero published UTC time'        => array( 'date', 'gmt', '0000-00-00 00:00:00' ),
			'zero modified UTC time'         => array( 'modified', 'gmt', '0000-00-00 00:00:00' ),
			'unparsable modified local time' => array( 'modified', 'local', '2024-06-01' ),
		);
	}

	/**
	 * Tests that a zero UTC time does not affect the local time.
	 *
	 * Draft posts store a zero `post_date_gmt`.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_datetime
	 */
	public function test_get_post_datetime_local_time_ignores_zero_utc_time() {
		$post                = get_post( self::$post_id );
		$post->post_date_gmt = '0000-00-00 00:00:00';

		$this->assertSame( '2024-03-15T10:30:00+02:00', get_post_datetime( $post )->format( DATE_RFC3339 ) );
	}

	/**
	 * Tests that get_post_timestamp() returns the Unix timestamp of the local time.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_timestamp
	 *
	 * @dataProvider data_get_post_timestamp
	 *
	 * @param string $field    Field to read, 'date' or 'modified'.
	 * @param int    $expected Expected Unix timestamp.
	 */
	public function test_get_post_timestamp( $field, $expected ) {
		$this->assertSame( $expected, get_post_timestamp( self::$post_id, $field ) );
	}

	/**
	 * Data provider for test_get_post_timestamp().
	 *
	 * The UTC fields are out of sync, so these timestamps only match the local fields.
	 *
	 * @return array[]
	 */
	public static function data_get_post_timestamp() {
		return array(
			'published (2024-03-15 08:30:00 UTC)' => array( 'date', 1710491400 ),
			'modified (2024-06-01 09:00:00 UTC)'  => array( 'modified', 1717232400 ),
		);
	}

	/**
	 * Tests that get_post_timestamp() defaults to the published time and the global post.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_timestamp
	 */
	public function test_get_post_timestamp_defaults() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$this->assertSame( 1710491400, get_post_timestamp() );
	}

	/**
	 * Tests that get_post_timestamp() uses a fixed UTC offset timezone.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_timestamp
	 */
	public function test_get_post_timestamp_with_utc_offset_timezone() {
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', 5.5 );

		// 2024-03-15 10:30:00 at UTC+5:30 is 2024-03-15 05:00:00 UTC.
		$this->assertSame( 1710478800, get_post_timestamp( self::$post_id ) );
	}

	/**
	 * Tests that get_post_timestamp() returns false when there is no valid time.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_timestamp
	 */
	public function test_get_post_timestamp_returns_false_without_valid_time() {
		$post            = get_post( self::$post_id );
		$post->post_date = '';

		$this->assertFalse( get_post_timestamp( PHP_INT_MAX ), 'A missing post should return false.' );
		$this->assertFalse( get_post_timestamp( $post ), 'An empty time should return false.' );
	}
}
